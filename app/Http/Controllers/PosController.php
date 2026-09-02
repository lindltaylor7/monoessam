<?php

namespace App\Http\Controllers;

use App\Exports\PosInventoryReportExport;
use App\Exports\PosSalesReportExport;
use App\Models\Mercantil;
use App\Models\MercantilSale;
use App\Models\Product;
use App\Models\Sale_type;
use App\Models\Subdealership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PosController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $mercantiles = Mercantil::whereHas('unit', fn($q) => $q->where('mine_id', $user->mine_id))
            ->with([
                'unit:id,name',
                'products' => fn($q) => $q->where('is_active', true)
                    ->orderBy('category')
                    ->orderBy('name'),
            ])
            ->get(['id', 'name', 'unit_id']);

        // Subdealerships de la mina del usuario — se elige antes de registrar el DNI en una
        // venta al crédito, mismo criterio de scoping que ya usa DinnerController::index().
        $subdealerships = Subdealership::whereHas('mines', fn($q) => $q->where('mines.id', $user->mine_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('pos/Index', [
            'mercantiles'    => $mercantiles,
            'sale_types'     => Sale_type::all(),
            'subdealerships' => $subdealerships,
        ]);
    }

    public function store(Request $request)
    {
        // "Valorizado" es el único método de pago válido al crédito, y viceversa — se factura a
        // la subdealership en vez de cobrarse en el momento, así que no puede mezclarse con
        // Efectivo/Yape/Plin/Tarjeta/Transferencia (esos son cobro inmediato, propios de contado).
        $isCredito = $request->input('payment_condition') === 'credito';

        $data = $request->validate([
            'mercantil_id'      => 'required|exists:mercantiles,id',
            'sale_type_id'      => 'nullable|exists:sale_types,id',
            'payment_method'    => ['nullable', 'string', $isCredito ? Rule::in(['valorizado']) : Rule::notIn(['valorizado'])],
            'payment_condition' => 'nullable|string|in:contado,credito',
            // Solo obligatorio cuando la venta es al crédito — queda registrado a quién se le fía.
            'buyer_dni'         => ['required_if:payment_condition,credito', 'nullable', 'string', 'regex:/^[A-Za-z0-9]{8,12}$/'],
            'subdealership_id'  => 'nullable|exists:subdealerships,id',
            // Comensal vinculado si el DNI coincidió con uno existente o se registró uno nuevo;
            // se admite null (venta al crédito sin comensal vinculado en la tabla dinners).
            'dinner_id'         => 'nullable|exists:dinners,id',
            'date'              => 'required|date',
            'products'          => 'required|string',
        ], [
            'buyer_dni.required_if' => 'El documento del comprador es obligatorio para ventas al crédito.',
            'buyer_dni.regex'       => 'El documento debe tener entre 8 y 12 caracteres alfanuméricos (DNI o Carné de Extranjería).',
            'payment_method.in'     => 'Al crédito, el único método de pago permitido es "Valorizado".',
            'payment_method.not_in' => 'El método de pago "Valorizado" solo aplica a ventas al crédito.',
        ]);

        $items = json_decode($data['products'], true);

        if (!is_array($items) || count($items) === 0) {
            return response()->json(['message' => 'El carrito está vacío.'], 422);
        }

        $mercantil = Mercantil::with('unit:id,mine_id')->findOrFail($data['mercantil_id']);

        // Solo se puede vender en un mercantil de la mina del usuario (mismo criterio que
        // ProductController::scopedMercantilIds()). Se desactiva para perfiles sin mina.
        if (Auth::user()->mine_id && optional($mercantil->unit)->mine_id !== Auth::user()->mine_id) {
            abort(403, 'El mercantil seleccionado no pertenece a su mina.');
        }

        // El precio de cada línea sale del maestro de productos, no del carrito del cliente:
        // se resuelve aquí y con él se recalcula subtotal/igv. Un ítem sin productId
        // (venta libre) conserva el unit_price enviado.
        $productPrices = Product::whereIn('id', collect($items)->pluck('productId')->filter()->all())
            ->pluck('price', 'id');

        $resolved = [];
        foreach ($items as $item) {
            $quantity  = (int) ($item['quantity'] ?? 0);
            $productId = $item['productId'] ?? null;
            $unitPrice = $productId && $productPrices->has($productId)
                ? (float) $productPrices->get($productId)
                : (float) ($item['unit_price'] ?? 0);
            $resolved[] = [
                'raw'        => $item,
                'productId'  => $productId,
                'quantity'   => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($unitPrice * $quantity, 2),
            ];
        }

        $subtotal = round(array_sum(array_column($resolved, 'line_total')), 2);
        $igv      = round($subtotal * 0.18, 2);

        try {
            $sale = DB::transaction(function () use ($data, $mercantil, $resolved, $subtotal, $igv) {
                $sale = MercantilSale::create([
                    'mercantil_id'      => $mercantil->id,
                    'unit_id'           => $mercantil->unit_id,
                    'user_id'           => Auth::id(),
                    'sale_type_id'      => $data['sale_type_id'] ?? null,
                    'payment_method'    => $data['payment_method'] ?? 'efectivo',
                    'payment_condition' => $data['payment_condition'] ?? 'contado',
                    'buyer_dni'         => $data['buyer_dni'] ?? null,
                    'subdealership_id'  => $data['subdealership_id'] ?? null,
                    'dinner_id'         => $data['dinner_id'] ?? null,
                    'date'              => $data['date'],
                    'subtotal'          => $subtotal,
                    'igv'               => $igv,
                    'total'             => $subtotal,
                ]);

                foreach ($resolved as $line) {
                    if ($line['productId']) {
                        // Bloqueo de fila + comprobación de disponibilidad: el stock nunca
                        // debe quedar negativo (antes se hacía decrement() a ciegas).
                        $product = Product::lockForUpdate()->find($line['productId']);
                        if (!$product || $product->stock < $line['quantity']) {
                            $available = $product->stock ?? 0;
                            throw ValidationException::withMessages([
                                'products' => "Stock insuficiente para \"{$line['raw']['name']}\": disponible {$available}, solicitado {$line['quantity']}.",
                            ]);
                        }
                    }

                    $sale->details()->create([
                        'product_id'   => $line['productId'],
                        'product_name' => $line['raw']['name'] ?? '',
                        'category'     => $line['raw']['category'] ?? null,
                        'quantity'     => $line['quantity'],
                        'unit_price'   => $line['unit_price'],
                        'subtotal'     => $line['line_total'],
                    ]);

                    if ($line['productId']) {
                        Product::where('id', $line['productId'])->decrement('stock', $line['quantity']);
                    }
                }

                return $sale;
            });
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
        }

        return response()->json($sale->load('details'), 201);
    }

    public function report(Request $request)
    {
        $data = $request->validate([
            'from'              => 'required|date',
            'to'                => 'required|date',
            'mercantil_id'      => 'nullable',
            'payment_method'    => 'nullable|string',
            'subdealership_id'  => 'nullable',
        ]);

        $from = $data['from'];
        $to   = $data['to'];
        $mercantilId     = $data['mercantil_id'] ?? null;
        $paymentMethod   = $data['payment_method'] ?? null;
        $subdealershipId = $data['subdealership_id'] ?? null;

        $query = MercantilSale::with([
            'mercantil:id,name',
            'saleType:id,name',
            'user:id,name',
            'subdealership:id,name',
            'dinner:id,name,dni',
            'details',
        ])
        ->whereBetween('date', [$from, $to]);

        if (!empty($mercantilId) && $mercantilId !== 'all') {
            $query->where('mercantil_id', $mercantilId);
        }

        if (!empty($paymentMethod) && $paymentMethod !== 'all') {
            $query->where('payment_method', $paymentMethod);
        }

        if (!empty($subdealershipId) && $subdealershipId !== 'all') {
            $query->where('subdealership_id', $subdealershipId);
        }

        $sales = $query->orderBy('date', 'desc')->orderBy('created_at', 'desc')->get();

        $totalMoney      = (float) $sales->sum('total');
        $totalSubtotal   = (float) $sales->sum('subtotal');
        $totalIgv        = (float) $sales->sum('igv');
        $totalSalesCount = $sales->count();
        $totalItemsCount = (int) $sales->sum(fn($s) => $s->details->sum('quantity'));

        return response()->json([
            'sales'             => $sales,
            'total_money'       => round($totalMoney, 2),
            'total_subtotal'    => round($totalSubtotal, 2),
            'total_igv'         => round($totalIgv, 2),
            'total_sales_count' => $totalSalesCount,
            'total_items_count' => $totalItemsCount,
        ]);
    }

    public function exportReport(Request $request)
    {
        $data = $request->validate([
            'from'             => 'required|date',
            'to'               => 'required|date',
            'mercantil_id'     => 'nullable',
            'payment_method'   => 'nullable|string',
            'subdealership_id' => 'nullable',
        ]);

        $from        = $data['from'];
        $to          = $data['to'];
        $mercantilId     = $data['mercantil_id'] ?? null;
        $paymentMethod   = $data['payment_method'] ?? null;
        $subdealershipId = $data['subdealership_id'] ?? null;

        $fileName = "reporte_ventas_pos_{$from}_a_{$to}.xlsx";

        return Excel::download(new PosSalesReportExport($from, $to, $mercantilId, $paymentMethod, $subdealershipId), $fileName);
    }

    public function exportInventory(Request $request)
    {
        $user = Auth::user();
        $mercantilIds = Mercantil::whereHas('unit', fn($q) => $q->where('mine_id', $user->mine_id))->pluck('id')->all();

        $data = $request->validate([
            'mercantil_id' => 'nullable',
        ]);

        $fileName = 'reporte_inventario_' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new PosInventoryReportExport($mercantilIds, $data['mercantil_id'] ?? null), $fileName);
    }
}


