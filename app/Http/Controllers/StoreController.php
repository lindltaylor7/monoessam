<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use App\Models\ComputerEquipment;
use App\Models\Epp;
use App\Models\EquipmentDispatch;
use App\Models\EquipmentStock;
use App\Models\Headquarter;
use App\Models\InventoryStock;
use App\Models\KitchenEquipment;
use App\Models\Staff;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StoreController extends Controller
{
    private const GUIDES_PER_PAGE = 10;
    private const STOCK_PER_PAGE  = 15;

    public function index(Request $request)
    {
        $mineId = Auth::user()?->mine_id;

        $cafes = Cafe::with('unit:id,name,mine_id', 'unit.mine:id,name')
            ->when($mineId, fn($q) => $q->whereHas('unit', fn($q) => $q->where('mine_id', $mineId)))
            ->orderBy('name')
            ->get(['id', 'name', 'unit_id']);

        $cafeIds = $cafes->pluck('id');

        // Unidades a las que se le puede enviar de forma general (sin especificar un café),
        // p. ej. una guía dirigida a "Southern" en vez de a un comedor puntual de esa unidad.
        $units = Unit::with('mine:id,name')
            ->when($mineId, fn($q) => $q->where('mine_id', $mineId))
            ->orderBy('name')
            ->get(['id', 'name', 'mine_id']);

        $unitIds = $units->pluck('id');

        // Destinations for the send modal
        $allCafes = Cafe::with('unit:id,name,mine_id', 'unit.mine:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'unit_id']);

        $headquarters = Headquarter::with('business:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'business_id']);

        // Stock real por café/unidad (ledger completo, sin paginar) — lo usa el modal "Nueva
        // Guía de Remisión" para calcular "Disponible", independiente de la tabla paginada.
        // Incluye el nombre del equipo directamente (no depende de que esté en la página
        // actualmente cargada de `dispatches`/`stock`, que sí están paginados).
        $stocksByLocation = function (string $locationCol, $ids) {
            return EquipmentStock::with('stockable:id,name,brand,model')
                ->whereIn($locationCol, $ids)
                ->get(['id', 'stockable_type', 'stockable_id', $locationCol, 'quantity'])
                ->groupBy($locationCol)
                ->map(fn($group) => $group->mapWithKeys(fn($r) => [
                    (str_contains($r->stockable_type, 'Computer') ? 'computer' : 'kitchen') . '-' . $r->stockable_id => [
                        'quantity' => $r->quantity,
                        'name'     => $r->stockable?->name ?? '—',
                        'brand'    => $r->stockable?->brand,
                        'model'    => $r->stockable?->model,
                    ],
                ]));
        };

        $cafeStocks = $stocksByLocation('cafe_id', $cafeIds);
        $unitStocks = $stocksByLocation('unit_id', $unitIds);

        // Stock de EPP por café — vive en InventoryStock (dimensionado por talla/color), no en el
        // ledger equipment_stocks (solo computer/kitchen). El modal "Nueva Guía de Remisión"
        // (sendDispatch) lo usa para poder enviar EPP desde un café hacia otro café o una sede,
        // igual que ya hace con equipos.
        $cafeEppStocks = InventoryStock::with(['stockable:id,name', 'color:id,name'])
            ->where('stockable_type', Epp::class)
            ->whereIn('cafe_id', $cafeIds)
            ->where('quantity', '>', 0)
            ->get(['id', 'stockable_type', 'stockable_id', 'cafe_id', 'quantity', 'size', 'color_id'])
            ->groupBy('cafe_id')
            ->map(fn($group) => $group->map(fn($r) => [
                'epp_id'     => $r->stockable_id,
                'name'       => $r->stockable?->name ?? '—',
                'quantity'   => $r->quantity,
                'size'       => $r->size,
                'color_id'   => $r->color_id,
                'color_name' => $r->color?->name,
            ])->values());

        // ── Ubicación y filtro seleccionados (llegan por query string) ──
        $locationType = $request->input('location_type', 'cafe') === 'unit' ? 'unit' : 'cafe';
        $locationId   = (int) $request->input('location_id', $cafes->first()?->id ?? 0);
        $typeFilter   = $request->input('type', 'all');

        $modelMap          = ['computer' => ComputerEquipment::class, 'kitchen' => KitchenEquipment::class, 'epp' => Epp::class];
        $modelClassFilter  = $modelMap[$typeFilter] ?? null;
        // "Insumos" (Ingredient) todavía no tiene guías/stock implementado en esta vista.
        $noResultsForType  = $typeFilter === 'supplies';

        // Query base de despachos relacionados a esta ubicación: si es un café, incluye tanto lo
        // que le llega (destino) como lo que sale de él hacia cualquier otro lado (origen) — así
        // la pestaña "Guías" muestra el movimiento completo, no solo lo recibido.
        $guideBase = EquipmentDispatch::query()->where('status', 'active');
        if ($locationId && $locationType === 'cafe') {
            $guideBase->where(function ($q) use ($locationId) {
                $q->where(function ($q2) use ($locationId) {
                    $q2->where('destination_type', 'cafe')->where('destination_id', $locationId);
                })->orWhere('origin_cafe_id', $locationId);
            });
        } elseif ($locationId) {
            $guideBase->where('destination_type', 'unit')->where('destination_id', $locationId);
        } else {
            $guideBase->whereRaw('1 = 0');
        }

        // Stats generales de la ubicación (no filtran por tipo — siempre reflejan el total).
        $statsRows = (clone $guideBase)->get(['id', 'equipable_type', 'received_at']);
        $stats = [
            'pending'          => $statsRows->whereNull('received_at')->count(),
            'received'         => $statsRows->whereNotNull('received_at')->count(),
            'computers'        => $statsRows->where('equipable_type', ComputerEquipment::class)->count(),
            'kitchen'          => $statsRows->where('equipable_type', KitchenEquipment::class)->count(),
            'epp'              => $statsRows->where('equipable_type', Epp::class)->count(),
            'pending_computer' => $statsRows->whereNull('received_at')->where('equipable_type', ComputerEquipment::class)->count(),
            'pending_kitchen'  => $statsRows->whereNull('received_at')->where('equipable_type', KitchenEquipment::class)->count(),
            'pending_epp'      => $statsRows->whereNull('received_at')->where('equipable_type', Epp::class)->count(),
        ];

        if ($modelClassFilter) {
            $guideBase->where('equipable_type', $modelClassFilter);
        } elseif ($noResultsForType) {
            $guideBase->whereRaw('1 = 0');
        }

        // ── Guías: se pagina por GUÍA (guide_number), no por fila individual, para que cada
        // página muestre grupos completos y no corte una guía a la mitad. ──
        $guidesPage = max(1, (int) $request->input('guides_page', 1));

        $guideGroups = (clone $guideBase)
            ->select('guide_number', DB::raw('MAX(id) as sort_id'))
            ->groupBy('guide_number')
            ->orderByDesc('sort_id')
            ->get();

        $totalGuides       = $guideGroups->count();
        $pagedGuideNumbers = $guideGroups->forPage($guidesPage, self::GUIDES_PER_PAGE)->pluck('guide_number');

        $dispatchesRaw = EquipmentDispatch::with(['equipable', 'origin', 'originCafe', 'dispatcher', 'receiver', 'color'])
            ->whereIn('guide_number', $pagedGuideNumbers)
            ->orderByDesc('id')
            ->get();

        // Resuelve el nombre del destino en bloque (no N+1) para las guías salientes.
        $destCafes = Cafe::whereIn('id', $dispatchesRaw->where('destination_type', 'cafe')->pluck('destination_id')->unique())
            ->select('id', 'name')->get()->keyBy('id');
        $destHeadquarters = Headquarter::whereIn('id', $dispatchesRaw->where('destination_type', 'headquarter')->pluck('destination_id')->unique())
            ->select('id', 'name')->get()->keyBy('id');
        $destUnits = Unit::whereIn('id', $dispatchesRaw->where('destination_type', 'unit')->pluck('destination_id')->unique())
            ->select('id', 'name')->get()->keyBy('id');

        $dispatchesTransformed = $dispatchesRaw->map(fn($d) => $this->transform($d, $destCafes, $destHeadquarters, $destUnits))->values();

        $dispatchesPaginator = new LengthAwarePaginator(
            $dispatchesTransformed,
            $totalGuides,
            self::GUIDES_PER_PAGE,
            $guidesPage,
            ['path' => $request->url(), 'pageName' => 'guides_page'],
        );
        $dispatchesPaginator->appends($request->query());

        // ── Stock: paginación estándar de Laravel, 15 por página ──
        // El stock de EPP vive en InventoryStock (dimensionado por talla/color), no en el ledger
        // equipment_stocks (solo computer/kitchen) — se consulta aparte cuando el tab activo es
        // "EPP". "Todos" sigue mostrando solo computer/kitchen (unificar ambas tablas en una sola
        // paginación requeriría un UNION; queda fuera de este alcance).
        if ($typeFilter === 'epp') {
            $stockQuery = InventoryStock::with(['stockable:id,name', 'color:id,name,hex_code'])
                ->where('stockable_type', Epp::class)
                ->where('quantity', '>', 0);
            if ($locationId && $locationType === 'cafe') {
                $stockQuery->where('cafe_id', $locationId);
            } elseif ($locationId) {
                $stockQuery->where('unit_id', $locationId);
            } else {
                $stockQuery->whereRaw('1 = 0');
            }

            $stockPaginator = $stockQuery->orderBy('id')->paginate(self::STOCK_PER_PAGE, ['*'], 'stock_page')->withQueryString();
            $stockPaginator->getCollection()->transform(fn($s) => [
                'id'                => $s->id,
                'equipable_id'      => $s->stockable_id,
                'equipable_type'    => 'epp',
                'equipment_name'    => $s->stockable?->name ?? '—',
                'equipment_brand'   => null,
                'equipment_model'   => null,
                'equipment_code'    => null,
                'equipment_series'  => null,
                'equipment_status'  => null,
                'size'              => $s->size,
                'color_name'        => $s->color?->name,
                'responsible_id'    => null,
                'responsible_name'  => null,
                'quantity'          => $s->quantity,
            ]);
        } else {
            $stockQuery = EquipmentStock::with('stockable.responsible:id,name')->where('quantity', '>', 0);
            if ($locationId && $locationType === 'cafe') {
                $stockQuery->where('cafe_id', $locationId);
            } elseif ($locationId) {
                $stockQuery->where('unit_id', $locationId);
            } else {
                $stockQuery->whereRaw('1 = 0');
            }
            if ($modelClassFilter) {
                $stockQuery->where('stockable_type', $modelClassFilter);
            } elseif ($noResultsForType) {
                $stockQuery->whereRaw('1 = 0');
            }

            $stockPaginator = $stockQuery->orderBy('id')->paginate(self::STOCK_PER_PAGE, ['*'], 'stock_page')->withQueryString();
            $stockPaginator->getCollection()->transform(fn($s) => [
                'id'                => $s->id,
                'equipable_id'      => $s->stockable_id,
                'equipable_type'    => str_contains($s->stockable_type, 'Computer') ? 'computer' : 'kitchen',
                'equipment_name'    => $s->stockable?->name ?? '—',
                'equipment_brand'   => $s->stockable?->brand,
                'equipment_model'   => $s->stockable?->model,
                'equipment_code'    => $s->stockable?->code,
                'equipment_series'  => $s->stockable?->series,
                'equipment_status'  => $s->stockable?->status,
                'responsible_id'    => $s->stockable?->responsible_id,
                'responsible_name'  => $s->stockable?->responsible?->name,
                'quantity'          => $s->quantity,
            ]);
        }

        // Personal (Staff) que pertenece a la Unidad del café/unidad seleccionado — para poder
        // asignar equipos del stock directamente desde acá.
        $staffUnitId = $locationType === 'unit' ? $locationId : $cafes->firstWhere('id', $locationId)?->unit_id;
        $staffOptions = $staffUnitId
            ? Staff::where('status', '!=', 0)
                ->whereHasMorph('staffable', [Cafe::class], fn($q) => $q->where('unit_id', $staffUnitId))
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
            : collect();

        // Pendientes por café/unidad, para los badges del sidebar (sin importar la selección actual).
        $pendingByCafe = EquipmentDispatch::where('status', 'active')->where('destination_type', 'cafe')
            ->whereIn('destination_id', $cafeIds)->whereNull('received_at')
            ->select('destination_id', DB::raw('count(*) as cnt'))->groupBy('destination_id')->pluck('cnt', 'destination_id');
        $pendingByUnit = EquipmentDispatch::where('status', 'active')->where('destination_type', 'unit')
            ->whereIn('destination_id', $unitIds)->whereNull('received_at')
            ->select('destination_id', DB::raw('count(*) as cnt'))->groupBy('destination_id')->pluck('cnt', 'destination_id');

        return Inertia::render('store/Index', [
            'dispatches'    => $dispatchesPaginator,
            'stock'         => $stockPaginator,
            'cafes'         => $cafes,
            'units'         => $units,
            'allCafes'      => $allCafes,
            'headquarters'  => $headquarters,
            'cafeStocks'    => $cafeStocks,
            'unitStocks'    => $unitStocks,
            'cafeEppStocks' => $cafeEppStocks,
            'staffOptions'  => $staffOptions,
            'stats'         => $stats,
            'pendingByCafe' => $pendingByCafe,
            'pendingByUnit' => $pendingByUnit,
            'filters'       => [
                'location_type' => $locationType,
                'location_id'   => $locationId,
                'type'          => $typeFilter,
            ],
        ]);
    }

    public function sendDispatch(Request $request)
    {
        $validated = $request->validate([
            'origin_cafe_id'   => 'required|exists:cafes,id',
            'destination_type' => 'required|in:headquarter,cafe',
            'destination_id'   => 'required|integer|min:1',
            'description'      => 'nullable|string|max:1000',
            'items'            => 'required|array|min:1',
            'items.*.equipable_type' => 'required|in:computer,kitchen,epp',
            'items.*.equipable_id'   => 'required|integer|min:1',
            'items.*.quantity'       => 'required|integer|min:1',
            'items.*.size'           => 'nullable|string|max:100',
            'items.*.color_id'       => 'nullable|exists:colors,id',
        ]);

        $modelMap = [
            'computer' => ComputerEquipment::class,
            'kitchen'  => KitchenEquipment::class,
            'epp'      => Epp::class,
        ];

        // El stock de EPP vive en InventoryStock (dimensionado por talla/color), no en el ledger
        // equipment_stocks (solo computer/kitchen) — se busca/descuenta la fila exacta del café
        // de origen, igual que el resto de este flujo.
        $eppStockQuery = fn (array $item) => InventoryStock::where([
            'stockable_type' => Epp::class,
            'stockable_id'   => $item['equipable_id'],
            'cafe_id'        => $item['origin_cafe_id'] ?? null,
            'headquarter_id' => null,
            'unit_id'        => null,
            'size'           => $item['size'] ?? null,
            'color_id'       => $item['color_id'] ?? null,
        ]);

        $guideSeq    = EquipmentDispatch::whereYear('created_at', now()->year)->whereNotNull('guide_number')->distinct('guide_number')->count() + 1;
        $guideNumber = 'GR-' . now()->year . '-' . str_pad($guideSeq, 4, '0', STR_PAD_LEFT);

        $created = DB::transaction(function () use ($validated, $modelMap, $eppStockQuery, $guideNumber) {
            $created = [];
            foreach ($validated['items'] as $item) {
                $item['origin_cafe_id'] = $validated['origin_cafe_id'];
                $modelClass = $modelMap[$item['equipable_type']];

                if ($item['equipable_type'] === 'epp') {
                    $stock = $eppStockQuery($item)->lockForUpdate()->first();
                } else {
                    // "Disponible" se lee directo del ledger de stock por café (equipment_stocks),
                    // no reconstruyéndolo desde el historial de guías. El lock + chequeo dentro de
                    // la transacción evita que dos envíos simultáneos sobrevendan el mismo stock.
                    $stock = EquipmentStock::where('stockable_type', $modelClass)
                        ->where('stockable_id', $item['equipable_id'])
                        ->where('cafe_id', $validated['origin_cafe_id'])
                        ->lockForUpdate()
                        ->first();
                }

                if (($stock->quantity ?? 0) < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => 'No hay suficiente stock disponible para uno de los ítems seleccionados.',
                    ]);
                }

                $stock->decrement('quantity', $item['quantity']);

                $seq            = EquipmentDispatch::whereYear('created_at', now()->year)->count() + 1;
                $dispatchNumber = 'DESP-' . now()->year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

                // La guía se crea con su propio `quantity` fijo — es un documento histórico
                // independiente, no se ve afectado por lo que pase después con el stock del café.
                EquipmentDispatch::create([
                    'equipable_type'        => $modelClass,
                    'equipable_id'          => $item['equipable_id'],
                    'quantity'              => $item['quantity'],
                    'size'                  => $item['size'] ?? null,
                    'color_id'              => $item['color_id'] ?? null,
                    'origin_headquarter_id' => null,
                    'origin_cafe_id'        => $validated['origin_cafe_id'],
                    'destination_type'      => $validated['destination_type'],
                    'destination_id'        => $validated['destination_id'],
                    'description'           => $validated['description'] ?? null,
                    'dispatch_number'       => $dispatchNumber,
                    'guide_number'          => $guideNumber,
                    'status'                => 'active',
                    'dispatched_at'         => now(),
                    'dispatched_by'         => Auth::id(),
                ]);

                $created[] = $dispatchNumber;
            }

            return $created;
        });

        $count = count($created);
        return back()->with('success', "Guía {$guideNumber} generada — {$count} ítem(s) enviado(s).");
    }

    private function transform(EquipmentDispatch $d, $destCafes = null, $destHeadquarters = null, $destUnits = null): array
    {
        $equipType = match (true) {
            str_contains($d->equipable_type, 'Computer') => 'computer',
            str_contains($d->equipable_type, 'Epp')      => 'epp',
            default                                       => 'kitchen',
        };

        $originName = $d->origin?->name ?? $d->originCafe?->name ?? '—';

        $destinationName = match ($d->destination_type) {
            'cafe'        => $destCafes?->get($d->destination_id)?->name,
            'headquarter' => $destHeadquarters?->get($d->destination_id)?->name,
            'unit'        => $destUnits?->get($d->destination_id)?->name,
            default       => null,
        } ?? '—';

        return [
            'id'              => $d->id,
            'dispatch_number' => $d->dispatch_number,
            'guide_number'    => $d->guide_number,
            'status'          => $d->status,
            'equipable_type'  => $equipType,
            'equipable_id'    => $d->equipable_id,
            'quantity'        => $d->quantity,
            'size'            => $d->size,
            'color_name'      => $d->color?->name,
            'equipment_name'  => $d->equipable?->name ?? '—',
            'equipment_brand' => $d->equipable?->brand,
            'equipment_model' => $d->equipable?->model,
            'equipment_code'  => $d->equipable?->code,
            'equipment_series' => $d->equipable?->series,
            'equipment_status' => $d->equipable?->status,
            'origin_cafe_id'  => $d->origin_cafe_id,
            'origin_name'     => $originName,
            'destination_type' => $d->destination_type,
            'destination_id'  => $d->destination_id,
            'destination_name' => $destinationName,
            'dispatched_by'   => $d->dispatcher?->name ?? '—',
            'dispatched_at'   => $d->dispatched_at?->format('d/m/Y H:i'),
            'received_at'     => $d->received_at?->format('d/m/Y H:i'),
            'received_by'     => $d->receiver?->name,
            'reception_notes' => $d->reception_notes,
        ];
    }
}
