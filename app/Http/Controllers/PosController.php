<?php

namespace App\Http\Controllers;

use App\Exports\PosSalesReportExport;
use App\Models\Mercantil;
use App\Models\MercantilSale;
use App\Models\Product;
use App\Models\Sale_type;
use App\Models\Subdealership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $data = $request->validate([
            'mercantil_id'      => 'required|exists:mercantiles,id',
            'sale_type_id'      => 'nullable|exists:sale_types,id',
            'payment_method'    => 'nullable|string',
            'payment_condition' => 'nullable|string|in:contado,credito',
            // Solo obligatorio cuando la venta es al crédito — queda registrado a quién se le fía.
            'buyer_dni'         => 'required_if:payment_condition,credito|nullable|digits:8',
            'subdealership_id'  => 'nullable|exists:subdealerships,id',
            // Comensal vinculado si el DNI coincidió con uno existente o se registró uno nuevo;
            // se admite null (venta al crédito sin comensal vinculado en la tabla dinners).
            'dinner_id'         => 'nullable|exists:dinners,id',
            'date'              => 'required|date',
            'products'          => 'required|string',
        ], [
            'buyer_dni.required_if' => 'El DNI del comprador es obligatorio para ventas al crédito.',
            'buyer_dni.digits'      => 'El DNI debe tener 8 dígitos.',
        ]);

        $items = json_decode($data['products'], true);

        if (!is_array($items) || count($items) === 0) {
            return response()->json(['message' => 'El carrito está vacío.'], 422);
        }

        $mercantil = Mercantil::findOrFail($data['mercantil_id']);

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += (float) ($item['total'] ?? (($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0)));
        }
        $subtotal = round($subtotal, 2);
        $igv      = round($subtotal * 0.18, 2);

        $sale = DB::transaction(function () use ($data, $mercantil, $items, $subtotal, $igv) {
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

            foreach ($items as $item) {
                $quantity  = (int) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $lineTotal = (float) ($item['total'] ?? ($quantity * $unitPrice));

                $sale->details()->create([
                    'product_id'   => $item['productId'] ?? null,
                    'product_name' => $item['name'] ?? '',
                    'category'     => $item['category'] ?? null,
                    'quantity'     => $quantity,
                    'unit_price'   => $unitPrice,
                    'subtotal'     => $lineTotal,
                ]);

                if (!empty($item['productId'])) {
                    Product::where('id', $item['productId'])->decrement('stock', $quantity);
                }
            }

            return $sale;
        });

        return response()->json($sale->load('details'), 201);
    }

    public function report(Request $request)
    {
        $data = $request->validate([
            'from'         => 'required|date',
            'to'           => 'required|date',
            'mercantil_id' => 'nullable',
        ]);

        $from = $data['from'];
        $to   = $data['to'];
        $mercantilId = $data['mercantil_id'] ?? null;

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
            'from'         => 'required|date',
            'to'           => 'required|date',
            'mercantil_id' => 'nullable',
        ]);

        $from        = $data['from'];
        $to          = $data['to'];
        $mercantilId = $data['mercantil_id'] ?? null;

        $fileName = "reporte_ventas_pos_{$from}_a_{$to}.xlsx";

        return Excel::download(new PosSalesReportExport($from, $to, $mercantilId), $fileName);
    }
}


