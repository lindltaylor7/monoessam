<?php

namespace App\Http\Controllers;

use App\Imports\DinnersImport;
use App\Models\Business;
use App\Models\Cafe;
use App\Models\Dealership;
use App\Models\Dinner;
use App\Models\Mine;
use App\Models\Receipt_type;
use App\Models\Sale;
use App\Models\Sale_type;
use App\Models\Service;
use App\Models\Subdealership;
use App\Models\Ticket;
use App\Models\Ticket_detail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

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

        $lockKey = 'sale_lock_' . $dinner->id . '_' . $request->date;
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            return response()->json([
                'message' => 'Se está procesando una venta para este comensal. Por favor espere.',
            ], 429);
        }

        try {
            return DB::transaction(function () use ($request, $services, $dinner, $cafe, $user) {
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
                    'total_isc'                    => 0.0,
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

                $serviceTypeMap = Service::whereIn('id', collect($services)->pluck('serviceID')->filter()->unique()->toArray())
                    ->pluck('type', 'id');

                foreach ($services as $service) {
                    Ticket_detail::create([
                        'ticket_id'    => $ticket->id,
                        'service_id'   => $service['serviceID'],
                        'code'         => $service['code'],
                        'service_name' => $service['name'],
                        'amount'       => $service['quantity'] ?? 1,
                        'um'           => 'UNI',
                        'service_type' => $serviceTypeMap[$service['serviceID']] ?? $service['serviceID'],
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
            });
        } finally {
            optional($lock)->release();
        }
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

    public function storeVisitor(Request $request)
    {
        $request->validate([
            'cafe_id'          => 'required|exists:cafes,id',
            'service_id'       => 'required|exists:services,id',
            'name'             => 'required|string|max:255',
            'dni'              => 'required|string|regex:/^[A-Za-z0-9]{8,12}$/',
            'date'             => 'required|date',
            'business_id'      => 'nullable|exists:businesses,id',
            'subdealership_id' => 'nullable|exists:subdealerships,id',
        ]);

        $cafe = Cafe::with('unit')->find($request->cafe_id);
        if (!$cafe) {
            return response()->json(['message' => 'Cafetería no encontrada.'], 404);
        }

        $service = Service::find($request->service_id);
        if (!$service) {
            return response()->json(['message' => 'Servicio no encontrado.'], 404);
        }

        $user = Auth::user();

        // Scoping por mina: si el cajero está asignado a una mina, solo puede vender en
        // comedores de esa mina (se desactiva para perfiles sin mina, como administración).
        if ($user->mine_id && optional($cafe->unit)->mine_id && $cafe->unit->mine_id !== $user->mine_id) {
            return response()->json(['message' => 'Este comedor pertenece a otra mina.'], 403);
        }

        // El precio lo fija el sistema desde el pivote servicio×comedor, nunca el cliente:
        // antes se tomaba floatval($request->price) sin contrastar contra nada.
        $servicePivot = $cafe->services()->where('services.id', $service->id)->first();
        $price = $servicePivot ? (float) $servicePivot->pivot->price : null;

        if ($price === null || $price <= 0) {
            return response()->json([
                'message' => 'El servicio seleccionado no tiene un precio configurado para esta cafetería.',
            ], 422);
        }

        $business       = $request->business_id ? Business::find($request->business_id) : null;
        $subdealership  = $request->subdealership_id ? Subdealership::find($request->subdealership_id) : null;

        // Lock + control de duplicados equivalente al de la venta a comensal, pero por DNI
        // (el visitante no está en el padrón).
        $lockKey = 'visitor_sale_lock_' . $request->dni . '_' . $request->date;
        $lock    = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            return response()->json([
                'message' => 'Se está procesando una venta para este visitante. Por favor espere.',
            ], 429);
        }

        try {
            if (!$request->boolean('force')) {
                $already = Sale::where('is_visitor', true)
                    ->where('date', $request->date)
                    ->whereHas('tickets', fn($q) => $q->where('dni', $request->dni)
                        ->whereHas('ticket_details', fn($td) => $td->where('code', $service->code)))
                    ->exists();

                if ($already) {
                    return response()->json([
                        'duplicate' => true,
                        'message'   => 'Este visitante ya consumió este servicio hoy.',
                    ], 409);
                }
            }

            return DB::transaction(function () use ($request, $cafe, $service, $user, $price, $business, $subdealership) {
            $sale = Sale::create([
                'dinner_id'    => null,
                'cafe_id'      => $cafe->id,
                'date'         => $request->date,
                'sale_type_id' => $request->sale_type_id,
                'business_id'  => $request->business_id,
                'business_name' => $business?->name,
                'cafe_name'    => $cafe->name,
                'user_id'      => $user->id,
                // La mina sale del comedor (o del usuario), no del payload del cliente.
                'mine_id'      => optional($cafe->unit)->mine_id ?: $user->mine_id,
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
                'subdealership_name' => $subdealership?->name ?? '',
                'serial_number'      => 'T00-VIS',
                'subdealership_ruc'  => $subdealership?->ruc ?? '',
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
                'service_type' => $service->type,
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
            });
        } finally {
            optional($lock)->release();
        }
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
        return Dinner::where(function ($query) use ($word) {
            $query->where('name', 'like', '%' . $word . '%')
                ->orWhere('dni', 'like', '%' . $word . '%');
        })
            ->where('mine_id', $id)
            ->with(['mine', 'subdealership'])
            ->take(8)
            ->get();
    }

    public function excel(Request $request)
    {
        if ($request->hasFile('file')) {
            $fileName = time() . '.' . $request->file->getClientOriginalExtension();
            $fileSaved = $request->file->move(public_path('files'), $fileName);

            $subdealershipId = $request->input('subdealership_id') && $request->input('subdealership_id') !== 'none'
                ? (int) $request->input('subdealership_id')
                : null;
            $cafeId = $request->input('cafe_id') && $request->input('cafe_id') !== 'none'
                ? (int) $request->input('cafe_id')
                : null;

            $import = new DinnersImport($subdealershipId, $cafeId);
            Excel::import($import, $fileSaved);

            $importedCount = $import->getImportedCount();
            $duplicates    = $import->getDuplicates();

            return redirect()->back()->with('importResults', [
                'imported'   => $importedCount,
                'duplicates' => $duplicates,
            ]);
        }

        return redirect()->back()->with('error', 'No se pudo subir el archivo');
    }

    public function printTest()
    {
        try {
            $connector = new WindowsPrintConnector("EPSON TM-T20II Receipt");
            $printer   = new Printer($connector);

            $printer->text("--- Test de impresora ---\n");
            $printer->cut();
            $printer->close();

            return response()->json(['success' => 'Ticket impreso correctamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al imprimir: ' . $e->getMessage()]);
        }
    }
}
