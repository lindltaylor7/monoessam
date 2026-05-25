<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Cafe;
use App\Models\Mine;
use App\Models\Dealership;
use App\Models\Dinner;
use App\Models\Receipt_type;
use App\Models\Sale;
use App\Models\Sale_type;
use App\Models\Service;
use App\Models\Subdealership;
use App\Models\Ticket;
use App\Models\Ticket_detail;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf as FacadePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Barryvdh\DomPDF\Facades\Pdf;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;

class SaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();

        // Cargar relaciones básicas
        $user->load(['areas.cafe.services', 'units.cafes.services']);

        // Obtener cafés de todas las unidades del usuario
        $cafes = $user->units->flatMap->cafes->unique('id')->values();
        $cafeIds = $cafes->pluck('id');

        // Obtener ventas paginadas por separado
        $todaySales = Sale::whereIn('cafe_id', $cafeIds)
            ->where('date', date('Y-m-d'))
            ->orderBy('id', 'desc')
            ->with(['tickets.dinner', 'cafe'])
            ->paginate(10);

        return Inertia::render('sales/Index', [
            'dinners' => Dinner::with(['subdealership', 'mine'])->get(),
            'services' => Service::all(),
            'units' => $user->units()->with('cafes')->get(),
            'cafes' => $cafes,
            'todaySales' => $todaySales,
            'sale_types' => Sale_type::all(),
            'receipt_types' => Receipt_type::all(),
            'subdealerships' => Subdealership::all(),
            'dealerships' => Dealership::all(),
            'mines' => Mine::all(),
            'businesses' => Business::all(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $services = json_decode($request->services, true) ?? [];

        $dinner = Dinner::where('dni', $request->dni)
            ->with(['mine', 'subdealership'])
            ->first();

        if (!$dinner) {
            return response()->json([
                'message' => 'No se encontró un comensal con ese DNI.',
            ], 404);
        }

        // Validate mine and subdealership access
        $user       = Auth::user();
        $user->loadMissing('business');
        $userMineId = $user->mine_id;

        if ($userMineId) {
            if ($dinner->mine_id && $dinner->mine_id !== $userMineId) {
                return response()->json([
                    'message' => 'Este comensal pertenece a otra mina y no puede ser atendido en esta instalación.',
                    'dinner'  => $dinner->only(['id', 'name', 'dni']),
                ], 403);
            }

            if ($dinner->subdealership_id) {
                $linked = \Illuminate\Support\Facades\DB::table('mine_subdealerships')
                    ->where('mine_id', $userMineId)
                    ->where('subdealership_id', $dinner->subdealership_id)
                    ->exists();

                if (!$linked) {
                    return response()->json([
                        'message' => 'La subconcesionaria de este comensal no está asociada a la mina en la que usted opera.',
                        'dinner'  => $dinner->only(['id', 'name', 'dni']),
                    ], 403);
                }
            }
        }

        $cafe = Cafe::find($request->cafe_id);

        if (!$cafe) {
            return response()->json([
                'message' => 'Cafetería no encontrada.',
            ], 404);
        }

        // Duplicate service check — must run before Sale::create (bypass with force=true)
        if (!$request->boolean('force')) {
            $newCodes = collect($services)->pluck('code')->toArray();

            $conflicts = Sale::with('cafe')
                ->where('dinner_id', $dinner->id)
                ->where('date', $request->date)
                ->whereHas('tickets.ticket_details', fn($q) => $q->whereIn('code', $newCodes))
                ->with(['tickets' => fn($q) => $q->with(['ticket_details' => fn($q2) => $q2->whereIn('code', $newCodes)])])
                ->get()
                ->flatMap(fn($s) => $s->tickets->flatMap(
                    fn($ticket) => $ticket->ticket_details->map(fn($td) => [
                        'service_name' => $td->service_name,
                        'service_code' => $td->code,
                        'cafe_name'    => $s->cafe?->name ?? 'Cafetería desconocida',
                    ])
                ))
                ->unique(fn($c) => $c['service_code'] . '|' . $c['cafe_name'])
                ->values();

            if ($conflicts->isNotEmpty()) {
                return response()->json([
                    'duplicate' => true,
                    'message'   => 'Este comensal ya consumió uno o más de estos servicios hoy.',
                    'dinner'    => $dinner->only(['id', 'name', 'dni']),
                    'conflicts' => $conflicts,
                ], 409);
            }
        }

        $total = array_reduce($services, function ($carry, $service) {
            return $carry + ($service['price'] ?? 0);
        }, 0);

        if ($request->double_price === 'true') {
            $total *= 2;
        }

        $sale = Sale::create([
            'dinner_id'                    => $dinner->id,
            'cafe_id'                      => $cafe->id,
            'date'                         => $request->date,
            'sale_type_id'                 => $request->sale_type_id,
            'payment_method_id'            => null,
            'business_id'                  => $user->business_id,
            'business_name'                => $user->business?->name,
            'cafe_name'                    => $cafe->name,
            'user_id'                      => $user->id,
            'total_discounts'              => 0.0,
            'total_non_taxable_operations' => 0.0,
            'total_taxable_operations'     => 0.0,
            'total_unaffected_operations'  => 0.0,
            'total_exonerated_operations'  => 0.0,
            'total_exported_operations'    => 0.0,
            'total_igv'                    => $total * 0.18,
            'total_icsc'                   => 0.0,
            'total_other_taxes'            => 0.0,
            'total_other_charges'          => 0.0,
            'total'                        => $total,
            'status'                       => 1,
        ]);

        $subdealership = $dinner->subdealership;

        $ticket = Ticket::create([
            'sale_id'            => $sale->id,
            'dinner_id'          => $dinner->id,
            'dinner_name'        => $dinner->name,
            'dni'                => $dinner->dni,
            'subdealership_name' => $subdealership?->name ?? '',
            'serial_number'      => 'T00',
            'subdealership_ruc'  => $subdealership?->ruc ?? '',
            'price_value'        => $sale->total,
            'igv'                => $sale->total_igv,
            'status'             => 1,
        ]);

        foreach ($services as $service) {
            Ticket_detail::create([
                'ticket_id'    => $ticket->id,
                'service_id'   => $service['serviceID'],
                'code'         => $service['code'],
                'service_name' => $service['name'],
                'amount'       => $service['quantity'] ?? 1,
                'um'           => 'UNI',
                'service_type' => $service['serviceID'],
                'description'  => '',
                'unit_value'   => $service['price'],
                'unit_price'   => $service['unit_price'] ?? $service['price'],
                'sale_value'   => $service['price'],
                'igv'          => $service['price'] * 0.18,
                'total'        => $service['price'],
            ]);
        }

        $ticket->load('ticket_details');

        $recentSales = Sale::with(['tickets', 'tickets.ticket_details', 'tickets.dinner', 'sale_details'])
            ->where('cafe_id', $cafe->id)
            ->where('date', $sale->date)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'dinner'  => $dinner,
            'ticket'  => $ticket,
            'message' => 'Venta registrada correctamente.',
            'sales'   => $recentSales,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $dinner = Dinner::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'dni' => 'required|string|max:8|unique:dinners,dni,' . $dinner->id,
            'phone' => 'nullable|string|max:15',
            'subdealership_id' => 'required|exists:subdealerships,id',
            'cafe_id' => 'required|exists:cafes,id',
        ]);

        $dinner->update($request->all());

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $dinner = Dinner::findOrFail($id);
        $dinner->delete();

        return redirect()->back();
    }

    public function storeVisitor(Request $request)
    {
        $cafe = Cafe::find($request->cafe_id);
        if (!$cafe) {
            return response()->json(['message' => 'Cafetería no encontrada.'], 404);
        }

        $service = Service::find($request->service_id);
        if (!$service) {
            return response()->json(['message' => 'Servicio no encontrado.'], 404);
        }

        $user     = Auth::user();
        $price    = floatval($request->price);
        $business = Business::find($request->business_id);

        $sale = Sale::create([
            'dinner_id'    => null,
            'cafe_id'      => $cafe->id,
            'date'         => $request->date,
            'sale_type_id' => $request->sale_type_id,
            'business_id'  => $request->business_id,
            'business_name' => $business?->name,
            'cafe_name'    => $cafe->name,
            'user_id'      => $user->id,
            'mine_id'      => $request->mine_id ?: null,
            'is_visitor'   => true,
            'total_igv'    => $price * 0.18,
            'total'        => $price,
            'status'       => 1,
        ]);

        $ticket = Ticket::create([
            'sale_id'            => $sale->id,
            'dinner_id'          => null,
            'dinner_name'        => $request->name,
            'dni'                => $request->dni,
            'subdealership_name' => '',
            'serial_number'      => 'T00-VIS',
            'subdealership_ruc'  => '',
            'price_value'        => $price,
            'igv'                => $price * 0.18,
            'status'             => 1,
        ]);

        Ticket_detail::create([
            'ticket_id'    => $ticket->id,
            'service_id'   => $service->id,
            'code'         => $service->code,
            'service_name' => $service->name,
            'amount'       => 1,
            'um'           => 'UNI',
            'service_type' => $service->id,
            'description'  => '',
            'unit_value'   => $price,
            'unit_price'   => $price,
            'sale_value'   => $price,
            'igv'          => $price * 0.18,
            'total'        => $price,
        ]);

        $recentSales = Sale::with(['tickets', 'tickets.ticket_details', 'tickets.dinner'])
            ->where('cafe_id', $cafe->id)
            ->where('date', $sale->date)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'message' => 'Venta de visitante registrada correctamente.',
            'sales'   => $recentSales,
        ], 200);
    }

    public function byDate(Request $request)
    {
        $sales = Sale::with(['tickets', 'tickets.ticket_details', 'tickets.dinner'])
            ->where('cafe_id', $request->cafe_id)
            ->where('date', $request->date)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($sales);
    }

    public function report(Request $request, string $startDate, string $endDate)
    {
        $user = Auth::user();
        $user->load(['units.cafes']);
        $cafeIds = $user->units->flatMap->cafes->pluck('id');

        $sales = Sale::whereIn('cafe_id', $cafeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->when($user->business_id, fn($q) => $q->where('business_id', $user->business_id))
            ->when($request->filled('cafe_id'), fn($q) => $q->where('cafe_id', $request->cafe_id))
            ->when($request->filled('sale_type_id'), fn($q) => $q->where('sale_type_id', $request->sale_type_id))
            ->with(['dinner.subdealership', 'tickets.ticket_details'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($sales);
    }

    public function search(string $word, string $id)
    {
        $dinners = Dinner::where(function ($query) use ($word) {
            $query->where('name', 'like', '%' . $word . '%')
                ->orWhere('dni', 'like', '%' . $word . '%');
        })
            ->where('cafe_id', $id)
            ->with(['cafe', 'cafe.unit', 'subdealership'])
            ->take(8)
            ->get();

        $this->printTest();

        return $dinners;
    }

    public function excel(Request $request)
    {

        if ($request->hasFile('file')) {

            $fileName = time() . '.' . $request->file->getClientOriginalExtension();
            $fileSaved = $request->file->move(public_path('files'), $fileName);

            Excel::import(new DinnersImport, $fileSaved);

            return redirect()->back()->with('success', 'Archivo subido correctamente');
        }

        return redirect()->back()->with('error', 'No se pudo subir el archivo');
    }

    public function printTest()
    {
        try {
            $nombreImpresora = "EPSON TM-T20II Receipt";

            $connector = new WindowsPrintConnector($nombreImpresora);

            $printer = new Printer($connector);

            $printer->text("Hello World\n");
            $printer->text("SOY DIEGO\n");
            $printer->text("SOY DIEGO\n");
            $printer->text("SOY DIEGO\n");
            $printer->text("SOY DIEGO\n");
            $printer->text("SOY DIEGO\n");
            $printer->text("SOY DIEGO\n");
            $printer->text("SOY DIEGO\n");
            $printer->text("SOY DIEGO\n");
            $printer->cut();
            $printer->close();

            return response()->json(['success' => 'Ticket impreso correctamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al imprimir: ' . $e->getMessage()]);
        }
    }
}
