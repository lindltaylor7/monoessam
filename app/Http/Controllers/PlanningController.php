<?php

namespace App\Http\Controllers;

use App\Models\WeeklyProgram;
use App\Models\WeeklyProgramItem;
use App\Models\DailyPortion;
use App\Models\Cafe;
use App\Models\City;
use App\Models\Dish_category;
use App\Models\DishRecipe;
use App\Models\Ingredient;
use App\Models\Ingredient_city_provider;
use App\Models\InventoryStock;
use App\Models\Level;
use App\Models\MenuStructure;
use App\Models\Serviceable;
use App\Models\Service;
use App\Models\Unit;
use App\Services\ProgramRecipes;
use App\Services\QuebradosService;
use App\Services\RecipeCosting;
use App\Exports\WeeklyPurchaseOrderExport;
use App\Exports\WeeklyMenuExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class PlanningController extends Controller
{
    protected $quebradosService;

    public function __construct(QuebradosService $quebradosService)
    {
        $this->quebradosService = $quebradosService;
    }

    public function index()
    {
        return Inertia::render('planning/Index', [
            'cafes' => Cafe::all(),
            // El listado de programaciones guardadas se pide paginado a planning/programs: con
            // miles de filas, mandarlas todas en la carga inicial volvía lenta la pantalla.
            'programs_total' => WeeklyProgram::count(),
            'dish_categories' => Dish_category::all(),
            'menu_structure' => MenuStructure::with('dish_category')->get(),
            'mines' => \App\Models\Mine::with(['units', 'units.cafes', 'units.cafes.services'])->get(),
            'levels' => Level::orderBy('name')->get(),
            'cities' => City::orderBy('name')->get(),
        ]);
    }

    /**
     * Listado paginado de programaciones guardadas para la pestaña "Programaciones Guardadas".
     * Los filtros (mina/unidad/comedor/fecha de guardado) se aplican en la consulta.
     */
    public function programs(Request $request)
    {
        $validated = $request->validate([
            'mine_id' => 'nullable|integer',
            'unit_id' => 'nullable|integer',
            'cafe_id' => 'nullable|integer',
            'saved_from' => 'nullable|date',
            'saved_to' => 'nullable|date',
            // Periodo planificado: programaciones cuyo rango [start_date, end_date] se cruza con él.
            'period_from' => 'nullable|date',
            'period_to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $programs = WeeklyProgram::with(['cafe.unit.mine', 'structure'])
            // Platos sin nivel de receta (programaciones anteriores): solo para ellos el front pide
            // un nivel al generar reportes.
            ->withCount(['items as items_without_level' => fn ($q) => $q->whereNull('level_id')])
            ->when($validated['cafe_id'] ?? null, fn($q, $id) => $q->where('cafe_id', $id))
            ->when($validated['unit_id'] ?? null, fn($q, $id) => $q->whereIn('cafe_id', Cafe::where('unit_id', $id)->select('id')))
            ->when($validated['mine_id'] ?? null, fn($q, $id) => $q->whereIn(
                'cafe_id',
                Cafe::whereIn('unit_id', Unit::where('mine_id', $id)->select('id'))->select('id'),
            ))
            ->when($validated['saved_from'] ?? null, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($validated['saved_to'] ?? null, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($validated['period_from'] ?? null, fn($q, $d) => $q->whereDate('end_date', '>=', $d))
            ->when($validated['period_to'] ?? null, fn($q, $d) => $q->whereDate('start_date', '<=', $d))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 25);

        // El servicio al que pertenece cada programación es único. Se guarda en weekly_programs.meal_type;
        // para programaciones antiguas sin ese dato, se cae al meal_type predominante de sus items
        // (solo para las de esta página).
        $missing = $programs->getCollection()->whereNull('meal_type')->pluck('id');
        $fallbackService = $missing->isEmpty() ? collect() : WeeklyProgramItem::select('weekly_program_id', 'meal_type', DB::raw('COUNT(*) as c'))
            ->whereIn('weekly_program_id', $missing)
            ->groupBy('weekly_program_id', 'meal_type')
            ->orderByDesc('c')
            ->get()
            ->groupBy('weekly_program_id')
            ->map(fn($rows) => $rows->first()->meal_type);

        $programs->getCollection()->each(function ($program) use ($fallbackService) {
            $program->setAttribute('service', $program->meal_type ?: $fallbackService->get($program->id));
            // Para preseleccionar la ciudad de precios de los reportes (Orden de Pedido / Compras).
            $program->setAttribute('supply_city_id', $program->cafe?->supplyCityId());
        });

        return response()->json($programs);
    }

    public function store(Request $request)
    {
        $validated = $this->validateProgram($request);

        $program = DB::transaction(function () use ($validated) {
            $program = WeeklyProgram::create([
                'cafe_id' => $validated['cafe_id'],
                'structure_id' => $validated['structure_id'] ?? null,
                'meal_type' => $this->resolveMealType($validated),
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'user_id' => Auth::id(),
                'status' => 'borrador',
            ]);

            $this->syncProgramLines($program, $validated);

            return $program;
        });

        // La pantalla usa el id para quedarse editando esta programación: así un segundo "Guardar"
        // la actualiza en vez de crear otra.
        return redirect()->route('planning.index')
            ->with('success', 'Plan guardado correctamente')
            ->with('created_program_id', $program->id);
    }

    /**
     * Detalle de una programación para reabrirla en la matriz (pestaña Planificar).
     */
    public function show($id)
    {
        $program = WeeklyProgram::with([
            'cafe.unit.mine',
            'items' => fn ($q) => $q->orderBy('id'),
            'items.dish:id,name',
            'items.dish_category:id,name',
            'portions',
        ])->findOrFail($id);

        // Programaciones antiguas sin meal_type: el servicio predominante de sus items.
        $service = $program->meal_type
            ?: $program->items->groupBy('meal_type')->sortByDesc(fn ($rows) => $rows->count())->keys()->first();

        return response()->json([
            'id' => $program->id,
            'cafe_id' => $program->cafe_id,
            'cafe' => $program->cafe?->name,
            'unit_id' => $program->cafe?->unit_id,
            'mine_id' => $program->cafe?->unit?->mine_id,
            'service' => $service,
            'start_date' => \Carbon\Carbon::parse($program->start_date)->toDateString(),
            'end_date' => \Carbon\Carbon::parse($program->end_date)->toDateString(),
            'status' => $program->status,
            'purchase_orders_count' => $this->purchaseOrdersCount($program),
            'items' => $program->items->map(fn ($item) => [
                'date' => \Carbon\Carbon::parse($item->date)->toDateString(),
                'meal_type' => $item->meal_type,
                'dish_category_id' => $item->dish_category_id,
                'dish_category' => $item->dish_category?->name,
                'dish_id' => $item->dish_id,
                'dish' => $item->dish?->name,
                'level_id' => $item->level_id,
                'percentage' => $item->percentage ?? 100,
            ])->values(),
            'portions' => $program->portions->map(fn ($portion) => [
                'date' => \Carbon\Carbon::parse($portion->date)->toDateString(),
                'meal_type' => $portion->meal_type,
                'portions_count' => (int) $portion->portions_count,
            ])->values(),
        ]);
    }

    /**
     * Reemplaza la cabecera, los platos y las raciones de una programación existente. Conserva su
     * id, autor y estado, así que no deja una copia duplicada en "Programaciones Guardadas".
     */
    public function update(Request $request, $id)
    {
        $program = WeeklyProgram::findOrFail($id);
        $validated = $this->validateProgram($request);

        DB::transaction(function () use ($program, $validated) {
            $program->update([
                'cafe_id' => $validated['cafe_id'],
                'structure_id' => $validated['structure_id'] ?? $program->structure_id,
                'meal_type' => $this->resolveMealType($validated),
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ]);

            $program->items()->delete();
            $program->portions()->delete();
            $this->syncProgramLines($program, $validated);
        });

        return back()->with('success', 'Programación actualizada correctamente');
    }

    /**
     * Elimina una programación. Si ya generó órdenes de compra no se borra: la FK en cascada se
     * llevaría esas órdenes sin aviso, así que primero hay que eliminarlas en Órdenes de Compra.
     */
    public function destroy($id)
    {
        $program = WeeklyProgram::findOrFail($id);

        $orders = $this->purchaseOrdersCount($program);
        if ($orders > 0) {
            return response()->json([
                'message' => "Esta programación tiene {$orders} orden(es) de compra generada(s). Elimínelas primero en Órdenes de Compra.",
            ], 422);
        }

        $program->delete();

        return response()->json(['deleted' => true]);
    }

    private function purchaseOrdersCount(WeeklyProgram $program): int
    {
        return \App\Models\PurchaseOrder::where('weekly_program_id', $program->id)
            ->orWhereHas('programs', fn ($q) => $q->where('weekly_programs.id', $program->id))
            ->count();
    }

    private function validateProgram(Request $request): array
    {
        return $request->validate([
            'cafe_id' => 'required|exists:cafes,id',
            'structure_id' => 'nullable|exists:structures,id',
            'meal_type' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            // Cada campo anidado necesita su regla: con una regla `items.*.x`, validated() solo
            // devuelve las claves anidadas declaradas. Con solo `percentage` se perdían dish_id,
            // date y categoría, y la programación se guardaba sin ningún plato.
            'items' => 'required|array',
            'items.*.date' => 'required|date',
            'items.*.meal_type' => 'required|string|max:255',
            'items.*.dish_category_id' => 'nullable|integer|exists:dish_categories,id',
            'items.*.dish_id' => 'nullable|integer|exists:dishes,id',
            'items.*.level_id' => 'nullable|integer|exists:levels,id',
            'items.*.percentage' => 'nullable|numeric|min:0|max:100',
            'portions' => 'required|array',
            'portions.*.date' => 'required|date',
            'portions.*.meal_type' => 'required|string|max:255',
            'portions.*.portions_count' => 'nullable|integer|min:0',
        ]);
    }

    /**
     * Una programación pertenece a un único servicio (el activo en la matriz). Si el front no lo
     * envía, se infiere del meal_type de los items con plato asignado.
     */
    private function resolveMealType(array $validated): ?string
    {
        return $validated['meal_type']
            ?? collect($validated['items'])->firstWhere(fn($i) => !empty($i['dish_id']))['meal_type']
            ?? null;
    }

    private function syncProgramLines(WeeklyProgram $program, array $validated): void
    {
        foreach ($validated['items'] as $item) {
            // Empty grid cells (no dish assigned yet) are sent too, since the form allows
            // saving a plan before every cell is filled in — skip them rather than inserting
            // a row with a null dish_id, which the column doesn't allow.
            if (empty($item['dish_id'])) {
                continue;
            }

            WeeklyProgramItem::create([
                'weekly_program_id' => $program->id,
                'date' => $item['date'],
                'meal_type' => $item['meal_type'],
                'dish_category_id' => $item['dish_category_id'],
                'dish_id' => $item['dish_id'],
                'level_id' => $item['level_id'] ?? null,
                'percentage' => $item['percentage'] ?? 100,
            ]);
        }

        foreach ($validated['portions'] as $portion) {
            DailyPortion::create([
                'weekly_program_id' => $program->id,
                'date' => $portion['date'],
                'meal_type' => $portion['meal_type'],
                'portions_count' => (int) ($portion['portions_count'] ?? 0),
            ]);
        }
    }

    /**
     * Ciclos de menú de un comedor+servicio (serviceable_id = pivot café↔servicio), para el
     * selector de la matriz. Solo metadatos: el cycle_data se pide al elegir uno (cycles/{id}).
     * Antes la página recibía los ~8 mil ciclos de todos los comedores en cada carga.
     */
    public function cycles(Request $request)
    {
        $validated = $request->validate([
            'serviceable_id' => 'required|integer',
        ]);

        return response()->json(
            \App\Models\MenuCycle::select(['id', 'name', 'days', 'legacy_cycle_number', 'is_current', 'updated_at'])
                ->where('serviceable_id', $validated['serviceable_id'])
                ->orderByDesc('is_current')
                ->orderByRaw('legacy_cycle_number is null')
                ->orderBy('legacy_cycle_number')
                ->orderByDesc('id')
                ->get()
        );
    }

    /**
     * Costo y calorías de los platos de la matriz (por plato + nivel) en la ciudad de abastecimiento
     * del comedor, más los límites L.Inf/L.Sup por categoría de la estructura del servicio. Mismo
     * cálculo que "Recalcular costos" de Ciclos, bajo el segmento `planning` para que un usuario de
     * Planificación lo use sin el permiso de Ciclos.
     */
    public function prices(Request $request, RecipeCosting $costing)
    {
        $validated = $request->validate([
            'cafe_id'           => 'required|integer|exists:cafes,id',
            'serviceable_id'    => 'nullable|integer',
            'items'             => 'present|array|max:2000',
            'items.*.dish_id'   => 'required|integer',
            'items.*.level_id'  => 'required|integer',
        ]);

        $cityId = Cafe::with('unit')->find($validated['cafe_id'])?->supplyCityId() ?: City::defaultSupplyCityId();

        $pairs = collect($validated['items'])
            ->map(fn ($item) => [(int) $item['dish_id'], (int) $item['level_id']])
            ->unique(fn ($p) => $p[0] . '-' . $p[1])
            ->values()
            ->all();

        // La estructura más reciente del servicio: sus techos de costo por categoría.
        $structure = empty($validated['serviceable_id']) ? null : \App\Models\Structure::with('costs')
            ->where('serviceable_id', $validated['serviceable_id'])
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'city_id' => $cityId,
            'totals'  => $costing->totals($pairs, $cityId),
            'structure' => $structure ? [
                'id' => $structure->id,
                'name' => $structure->name,
                'selling_price' => $structure->selling_price !== null ? (float) $structure->selling_price : null,
                'limits' => $structure->costs
                    ->filter(fn ($cost) => $cost->dish_category_id)
                    ->mapWithKeys(fn ($cost) => [$cost->dish_category_id => [
                        'min' => $cost->total_cost !== null ? (float) $cost->total_cost : null,
                        'max' => $cost->total_cost_superior !== null ? (float) $cost->total_cost_superior : null,
                    ]]),
            ] : null,
        ]);
    }

    public function generatePurchaseOrder(Request $request, $id)
    {
        $validated = $request->validate([
            'level_id' => 'nullable|exists:levels,id',
        ]);

        $program = WeeklyProgram::with('portions')->findOrFail($id);

        return $this->purchaseOrderResponse(collect([$program]), $this->fallbackLevel($validated, [$program->id]));
    }

    /**
     * Quebrado (PO) consolidado de varias programaciones marcadas en "Programaciones Guardadas":
     * una sola orden con la suma de insumos de todas.
     */
    public function generatePurchaseOrderMultiple(Request $request)
    {
        $validated = $request->validate([
            'level_id' => 'nullable|exists:levels,id',
            'program_ids' => 'required|array|min:1',
            'program_ids.*' => 'integer|exists:weekly_programs,id',
        ]);

        $programs = WeeklyProgram::with('portions')
            ->whereIn('id', array_unique($validated['program_ids']))
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        return $this->purchaseOrderResponse($programs, $this->fallbackLevel($validated, $programs->pluck('id')->all()));
    }

    private function purchaseOrderResponse($programs, ?Level $level)
    {
        $orders = $this->quebradosService->generatePurchaseOrdersByCity($programs, $level);

        if ($orders->count() > 1) {
            $orders->load('city');
            $summary = $orders->map(fn ($o) => "#{$o->id} " . ($o->city?->name ?? 'sin ciudad'))->implode(', ');

            return redirect()->route('purchase_orders.index')
                ->with('success', "Las programaciones se abastecen desde ciudades distintas: se generó una orden por ciudad ({$summary}).");
        }

        $order = $orders->first();

        if ($order->items()->count() === 0) {
            $which = $programs->count() > 1 ? 'estas programaciones' : 'esta programación';

            return redirect()->route('purchase_orders.show', $order->id)
                ->with('error', "La orden se creó sin ítems: los platos de {$which} no tienen receta en su nivel, o no hay raciones cargadas.");
        }

        $message = $programs->count() > 1
            ? "Pedido (Quebrado) consolidado de {$programs->count()} programaciones generado con éxito"
            : 'Pedido (Quebrado) generado con éxito';

        return redirect()->route('purchase_orders.show', $order->id)->with('success', $message);
    }

    /**
     * Todos los reportes de este módulo se generan sobre una o varias programaciones marcadas en
     * la pestaña "Programaciones Guardadas" (program_ids[]), igual que el Menú Semanal. Este helper
     * resuelve esas programaciones, sus items y la receta de cada item: la de su propio nivel
     * (heredado del ciclo), o la del nivel de respaldo elegido en el reporte si el item no lo tiene.
     *
     * @return array{0: \Illuminate\Database\Eloquent\Collection, 1: \Illuminate\Support\Collection, 2: ProgramRecipes, 3: object}
     */
    private function loadReportData(array $programIds, ?Level $fallback, array $recipeWith): array
    {
        $programs = WeeklyProgram::with(['cafe.unit.mine', 'portions'])
            ->whereIn('id', $programIds)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        $items = WeeklyProgramItem::whereIn('weekly_program_id', $programs->pluck('id'))
            ->with(['dish', 'dish_category'])
            ->orderBy('date')
            ->orderBy('meal_type')
            ->get();

        $recipes = ProgramRecipes::load($items, $fallback, $recipeWith);

        // Las vistas PDF solo leen ->name para la cabecera "Nivel:".
        $levelLabel = (object) ['name' => $recipes->label($items)];

        return [$programs, $items->groupBy('weekly_program_id'), $recipes, $levelLabel];
    }

    /**
     * Nivel de respaldo para los items sin nivel propio. Solo es obligatorio cuando alguno de los
     * items de las programaciones elegidas no lo tiene: si no, cada plato usa el suyo.
     */
    private function fallbackLevel(array $validated, array $programIds): ?Level
    {
        if (!empty($validated['level_id'])) {
            return Level::findOrFail($validated['level_id']);
        }

        $withoutLevel = WeeklyProgramItem::whereIn('weekly_program_id', $programIds)->whereNull('level_id')->exists();
        if ($withoutLevel) {
            throw ValidationException::withMessages([
                'level_id' => 'Hay platos programados sin nivel de receta: elija un nivel para ellos.',
            ]);
        }

        return null;
    }

    /** Cadena "Mina - Unidad - Comedor" de una programación, como se muestra en el resto de la app. */
    private function baseChainFor(WeeklyProgram $program): string
    {
        return collect([
            optional(optional($program->cafe->unit)->mine)->name,
            optional($program->cafe->unit)->name,
            optional($program->cafe)->name,
        ])->filter()->implode(' - ');
    }

    /**
     * Builds the "Quebrado Semanal" PDF: for every dish assigned across the selected programs, its
     * ingredient breakdown (scaled by that date+meal's effective rations) grouped by day. Each
     * program contributes its own set of day/servicio pages, each with its own masthead
     * (Unidad/Base/Período/Fecha), so several programs simply flow one after another.
     *
     * The planning grid never records which recipe "nivel" (Master/Staff/Empleado/Obrero) a
     * dish was assigned under — only dish_id — so the level is chosen by the user when they
     * request this PDF, and used to resolve every dish's DishRecipe uniformly.
     */
    public function quebradoPdf(Request $request)
    {
        $validated = $request->validate([
            'program_ids' => 'required|array|min:1',
            'program_ids.*' => 'integer|exists:weekly_programs,id',
            'level_id' => 'nullable|exists:levels,id',
        ]);

        [$programs, $itemsByProgram, $recipes, $level] = $this->loadReportData(
            $validated['program_ids'],
            $this->fallbackLevel($validated, $validated['program_ids']),
            ['ingredients']
        );

        // The original paper "Quebrado" is printed one sheet per date+servicio, so that's the
        // page unit here too — a flat list rather than nested days→meals, so each page carries
        // its own full masthead, matching the real document.
        $pages = collect();

        foreach ($programs as $program) {
            $portions = $program->portions->keyBy(fn($p) => $p->date . '_' . $p->meal_type);
            $baseChain = $this->baseChainFor($program);
            $unitName = strtoupper($program->cafe->unit->name ?? '—');
            $items = $itemsByProgram->get($program->id) ?? collect();

            foreach ($items->groupBy('date') as $date => $dayItems) {
                foreach ($dayItems->groupBy('meal_type') as $mealType => $mealItems) {
                    $portionsCount = optional($portions->get($date . '_' . $mealType))->portions_count ?? 0;

                    $dishes = $mealItems->values()->map(function ($item) use ($portionsCount, $recipes) {
                        $recipe = $recipes->for($item);
                        // Raciones del plato = raciones del servicio * % de comensales que lo toman.
                        $dishPortions = $item->effectivePortions($portionsCount);

                        $ingredients = $recipe
                            ? $recipe->ingredients->map(function ($ingredient) use ($dishPortions) {
                                $qtyPerRation = (float) $ingredient->pivot->gross_weight;
                                $totalRequired = $qtyPerRation * $dishPortions;
                                return [
                                    'code' => $ingredient->id,
                                    'name' => $ingredient->name,
                                    'qty_per_ration' => $qtyPerRation,
                                    'total_required' => $totalRequired,
                                    'total_rounded' => ceil($totalRequired),
                                ];
                            })->values()
                            : collect();

                        return [
                            'category_id' => $item->dish_category_id,
                            'category' => $item->dish_category->name ?? 'Sin categoría',
                            'dish_id' => $item->dish_id,
                            'dish_name' => $item->dish->name ?? 'Plato eliminado',
                            'portions' => $dishPortions,
                            'percentage' => (float) ($item->percentage ?? 100),
                            'ingredients' => $ingredients,
                            'has_recipe' => (bool) $recipe,
                            'level_name' => $recipes->levelNameFor($item),
                        ];
                    });

                    $pages->push([
                        'date' => $date,
                        'meal_type' => $mealType,
                        'portions' => $portionsCount,
                        'program_id' => $program->id,
                        'unit' => $unitName,
                        'base' => $baseChain,
                        'dishes' => $dishes,
                    ]);
                }
            }
        }

        $pdf = Pdf::loadView('pdf.weekly_quebrado', [
            'level' => $level,
            'pages' => $pages,
            'programCount' => $programs->count(),
        ]);

        return $pdf->setPaper('a4', 'portrait')->stream('Quebrado_Semanal_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * Builds the "Requerimiento x Producto" PDF: the inverse view of quebradoPdf() — instead of
     * grouping by day/servicio/plato, it groups by ingredient_category → insumo, listing every
     * date/servicio/plato across the selected programs that insumo is needed for, with quantities.
     * Con varias programaciones el listado es consolidado: los insumos se suman entre todas.
     * Same per-level caveat as quebradoPdf(): the planning grid doesn't record a recipe nivel
     * per dish, so it's chosen once here and applied to every dish.
     */
    public function requerimientoPdf(Request $request)
    {
        $validated = $request->validate([
            'program_ids' => 'required|array|min:1',
            'program_ids.*' => 'integer|exists:weekly_programs,id',
            'level_id' => 'nullable|exists:levels,id',
        ]);

        [$programs, $itemsByProgram, $recipes, $level] = $this->loadReportData(
            $validated['program_ids'],
            $this->fallbackLevel($validated, $validated['program_ids']),
            ['ingredients.ingredient_category']
        );

        $flat = collect();
        $hasMatchingRecipes = false;
        $hasAnyPortions = false;

        foreach ($programs as $program) {
            $portions = $program->portions->keyBy(fn($p) => $p->date . '_' . $p->meal_type);

            foreach ($itemsByProgram->get($program->id) ?? collect() as $item) {
                $recipe = $recipes->for($item);
                if ($recipe) {
                    $hasMatchingRecipes = true;
                }

                $servicePortions = optional($portions->get($item->date . '_' . $item->meal_type))->portions_count ?? 0;
                if ($servicePortions > 0) {
                    $hasAnyPortions = true;
                }

                // Raciones del plato = raciones del servicio * % de comensales que lo toman.
                $portionsCount = $item->effectivePortions($servicePortions);

                if (!$recipe || $portionsCount <= 0) {
                    continue;
                }

                foreach ($recipe->ingredients as $ingredient) {
                    $qtyPerRation = (float) $ingredient->pivot->gross_weight;
                    if ($qtyPerRation <= 0) {
                        continue;
                    }
                    $totalRequired = $qtyPerRation * $portionsCount;

                    $flat->push([
                        'category_name' => optional($ingredient->ingredient_category)->name ?? 'Sin Categoría',
                        'ingredient_name' => $ingredient->name,
                        'date' => $item->date,
                        'meal_type' => $item->meal_type,
                        'dish_id' => $item->dish_id,
                        'dish_name' => $item->dish->name ?? 'Plato eliminado',
                        'qty_per_ration' => $qtyPerRation,
                        'portions' => $portionsCount,
                        'total_required' => $totalRequired,
                        'total_kg' => $totalRequired / 1000,
                    ]);
                }
            }
        }

        $categories = $flat->groupBy('category_name')->sortKeys()->map(function ($rows, $categoryName) {
            $ingredients = $rows->groupBy('ingredient_name')->sortKeys()->map(function ($ingRows, $ingredientName) {
                $sorted = $ingRows->sortBy('date')->values();
                return [
                    'name' => $ingredientName,
                    'rows' => $sorted,
                    'total_kg' => $sorted->sum('total_kg'),
                ];
            })->values();

            return [
                'name' => $categoryName,
                'ingredients' => $ingredients,
            ];
        })->values();

        $first = $programs->first();
        $meta = [
            'unit' => $programs->map(fn($p) => $p->cafe->unit->name ?? null)->filter()->unique()->implode(' / ') ?: '—',
            'base' => $programs->map(fn($p) => $this->baseChainFor($p))->filter()->unique()->implode('  ·  ') ?: '—',
            'year' => $first ? \Carbon\Carbon::parse($first->start_date)->year : now()->year,
            'month' => $first ? ucfirst(\Carbon\Carbon::parse($first->start_date)->locale('es')->translatedFormat('F')) : '',
            'week' => $first ? \Carbon\Carbon::parse($first->start_date)->isoWeek() : '',
            'orden' => $programs->pluck('id')->implode(', '),
        ];

        $pdf = Pdf::loadView('pdf.weekly_requirement', [
            'level' => $level,
            'categories' => $categories,
            'meta' => $meta,
            'hasMatchingRecipes' => $hasMatchingRecipes,
            'hasAnyPortions' => $hasAnyPortions,
        ]);

        return $pdf->setPaper('a4', 'portrait')->stream('Requerimiento_x_Producto_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * Builds the "Dosificación Nutricional" PDF: one page per date + servicio, and within it a
     * block per plato listing every insumo of its receta with the nutritional breakdown per
     * ración (22 columnas, en el orden de la Tabla Peruana de Composición de Alimentos) más
     * una fila de totales por plato.
     *
     * Cada valor nutricional del insumo = valor por 100 g (tabla `dosifications`) escalado por
     * el peso neto por ración del quebrado (`dish_recipe_ingredients.net_weight` / 100), igual
     * que el cálculo de calorías por insumo en el editor de quebrados (CalcPopover.vue). La
     * columna "IC" es el id del registro de dosificación usado (0 si el insumo no tiene una).
     *
     * Mismo caveat de nivel que quebradoPdf()/requerimientoPdf(): el grid de planificación no
     * guarda el nivel de receta por plato, así que se elige aquí y se aplica a toda la semana.
     */
    public function dosificacionPdf(Request $request)
    {
        $validated = $request->validate([
            'program_ids' => 'required|array|min:1',
            'program_ids.*' => 'integer|exists:weekly_programs,id',
            'level_id' => 'nullable|exists:levels,id',
        ]);

        [$programs, $itemsByProgram, $recipes, $level] = $this->loadReportData(
            $validated['program_ids'],
            $this->fallbackLevel($validated, $validated['program_ids']),
            ['ingredients.dosification']
        );

        // Columnas nutricionales del reporte, en el orden de la Tabla Peruana de Composición de
        // Alimentos: nombre en español (encabezado), tagname INFOODS y la columna de
        // `dosifications` de la que sale el valor. `retinol` se reutiliza como "Vitamina A
        // equivalentes totales" (VITA); `a_asc` es Vitamina C (VITC); `a_folic` es ácido fólico.
        $nutrients = [
            ['name' => 'Energía',                         'tag' => 'ENERC',  'column' => 'energy'],
            ['name' => 'Agua',                            'tag' => 'WATER',  'column' => 'water'],
            ['name' => 'Proteínas',                       'tag' => 'PROCNT', 'column' => 'protein'],
            ['name' => 'Grasa total',                     'tag' => 'FAT',    'column' => 'lipid'],
            ['name' => 'Carbohidratos totales',           'tag' => 'CHOCDF', 'column' => 'carbohydrate'],
            ['name' => 'Carbohidratos disponibles',       'tag' => 'CHOAVL', 'column' => 'carbohydrate_available'],
            ['name' => 'Fibra dietaria',                  'tag' => 'FIBTG',  'column' => 'fiber'],
            ['name' => 'Cenizas',                         'tag' => 'ASH',    'column' => 'ash'],
            ['name' => 'Calcio',                          'tag' => 'CA',     'column' => 'calcium'],
            ['name' => 'Fósforo',                         'tag' => 'P',      'column' => 'phosphorus'],
            ['name' => 'Zinc',                            'tag' => 'ZN',     'column' => 'zinc'],
            ['name' => 'Hierro',                          'tag' => 'FE',     'column' => 'iron'],
            ['name' => 'B caroteno equivalentes totales', 'tag' => 'CARTBQ', 'column' => 'carotene'],
            ['name' => 'Vitamina A equivalentes totales', 'tag' => 'VITA',   'column' => 'retinol'],
            ['name' => 'Tiamina',                         'tag' => 'THIA',   'column' => 'thiamine'],
            ['name' => 'Riboflavina',                     'tag' => 'RIBF',   'column' => 'riboflavin'],
            ['name' => 'Niacina',                         'tag' => 'NIA',    'column' => 'niacin'],
            ['name' => 'Vitamina C',                      'tag' => 'VITC',   'column' => 'a_asc'],
            ['name' => 'Ácido fólico',                    'tag' => 'FOLFD',  'column' => 'a_folic'],
            ['name' => 'Sodio',                           'tag' => 'NA',     'column' => 'sodium'],
            ['name' => 'Potasio',                         'tag' => 'K',      'column' => 'potassium'],
            ['name' => '% Alcohol',                       'tag' => 'ALC',    'column' => 'alcohol'],
        ];

        $pages = collect();

        foreach ($programs as $program) {
            $portions = $program->portions->keyBy(fn($p) => $p->date . '_' . $p->meal_type);
            $baseChain = $this->baseChainFor($program);
            $unitName = strtoupper($program->cafe->unit->name ?? '—');

            foreach (($itemsByProgram->get($program->id) ?? collect())->groupBy('date') as $date => $dayItems) {
                foreach ($dayItems->groupBy('meal_type') as $mealType => $mealItems) {
                    $portionsCount = optional($portions->get($date . '_' . $mealType))->portions_count ?? 0;

                    $categoryCounters = [];

                    $dishes = $mealItems->values()->map(function ($item, $idx) use ($portionsCount, $recipes, $nutrients, &$categoryCounters) {
                        $recipe = $recipes->for($item);
                        // Raciones del plato = raciones del servicio * % de comensales que lo toman.
                        $dishPortions = $item->effectivePortions($portionsCount);

                        $categoryName = $item->dish_category->name ?? 'Sin categoría';
                        $categoryCounters[$categoryName] = ($categoryCounters[$categoryName] ?? 0) + 1;

                        $totals = array_fill_keys(array_column($nutrients, 'column'), 0.0);

                        $ingredients = $recipe
                            ? $recipe->ingredients->map(function ($ingredient) use ($nutrients, &$totals) {
                                $dosification = $ingredient->dosification;

                                $grossWeight = (float) $ingredient->pivot->gross_weight;
                                $netWeight   = (float) $ingredient->pivot->net_weight;
                                if ($netWeight <= 0) {
                                    $netWeight = $grossWeight;
                                }
                                $factor = $netWeight / 100;

                                $values = [];
                                foreach ($nutrients as $nutrient) {
                                    $column = $nutrient['column'];
                                    $per100 = 0.0;
                                    if ($dosification) {
                                        $raw = $dosification->{$column};
                                        // CHOCDF (carbohidratos totales) cae a los disponibles cuando el total no está cargado.
                                        if (($raw === null || $raw === '') && $column === 'carbohydrate') {
                                            $raw = $dosification->carbohydrate_available;
                                        }
                                        $per100 = (float) ($raw ?? 0);
                                    }
                                    $amount = $per100 * $factor;
                                    $values[$column] = $amount;
                                    $totals[$column] += $amount;
                                }

                                return [
                                    'code'    => $ingredient->id,
                                    'name'    => $ingredient->name,
                                    'gramaje' => $grossWeight,
                                    'ic'      => $dosification?->id ?? 0,
                                    'values'  => $values,
                                ];
                            })->values()
                            : collect();

                        return [
                            'index'          => $idx + 1,
                            'category'       => $categoryName,
                            'category_index' => $categoryCounters[$categoryName],
                            'dish_code'      => $item->dish_id,
                            'dish_name'      => $item->dish->name ?? 'Plato eliminado',
                            'portions'       => $dishPortions,
                            'percentage'     => (float) ($item->percentage ?? 100),
                            'ingredients'    => $ingredients,
                            'totals'         => $totals,
                            'has_recipe'     => (bool) $recipe,
                            'level_name'     => $recipes->levelNameFor($item),
                        ];
                    });

                    $pages->push([
                        'date'       => $date,
                        'meal_type'  => $mealType,
                        'portions'   => $portionsCount,
                        'program_id' => $program->id,
                        'unit'       => $unitName,
                        'base'       => $baseChain,
                        'dishes'     => $dishes,
                    ]);
                }
            }
        }

        $pdf = Pdf::loadView('pdf.dosificacion_nutricional', [
            'level'        => $level,
            'pages'        => $pages,
            'nutrients'    => $nutrients,
            'programCount' => $programs->count(),
        ]);

        return $pdf->setPaper('a4', 'landscape')->stream('Dosificacion_Nutricional_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * Builds the "Menú Semanal" Excel: one sheet per programación seleccionada, con la grilla
     * de menú de la semana (opciones/categorías en filas, días en columnas). A diferencia del
     * resto de reportes de este módulo, se dispara sin un programa fijo: el usuario elige en el
     * front qué programaciones incluir y sus ids llegan en `program_ids[]`.
     */
    public function menuExcel(Request $request)
    {
        $validated = $request->validate([
            'program_ids'   => 'required|array|min:1',
            'program_ids.*' => 'integer|exists:weekly_programs,id',
        ]);

        $programs = WeeklyProgram::with([
            'cafe.unit.mine',
            'structure.costs',
            'items.dish',
            'items.dish_category',
            'portions',
        ])
            ->whereIn('id', $validated['program_ids'])
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        $menuStructure = MenuStructure::orderBy('sort_order')->get();

        $filename = 'Menu_Semanal_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new WeeklyMenuExport($programs, $menuStructure), $filename);
    }

    /**
     * Builds the "Orden de Pedido Semanal" Excel: total quantity needed per insumo across the
     * whole week (grouped by ingredient_category), priced and summed to a grand total.
     *
     * Same per-level caveat as the other two reports (level chosen manually). Price additionally
     * needs a city — ingredient prices only exist per (ingredient, city, provider). The screen
     * preselects the supply city of the selected programs' comedor (Unidad/Comedor
     * supply_city_id), but it can still be changed to compare. When an ingredient has no registered price for the chosen city, its row is
     * flagged instead of silently counted as zero, and the grand total excludes it.
     */
    public function purchaseOrderExcel(Request $request)
    {
        $validated = $request->validate([
            'program_ids' => 'required|array|min:1',
            'program_ids.*' => 'integer|exists:weekly_programs,id',
            'level_id' => 'nullable|exists:levels,id',
            'city_id' => 'required|exists:cities,id',
        ]);

        $city = City::findOrFail($validated['city_id']);
        [$programs, $itemsByProgram, $recipes, $level] = $this->loadReportData(
            $validated['program_ids'],
            $this->fallbackLevel($validated, $validated['program_ids']),
            ['ingredients.ingredient_category']
        );

        // Aggregate total grams needed per insumo across every date/servicio of every selected program.
        $totals = collect();

        foreach ($programs as $program) {
            $portions = $program->portions->keyBy(fn($p) => $p->date . '_' . $p->meal_type);

            foreach ($itemsByProgram->get($program->id) ?? collect() as $item) {
                $recipe = $recipes->for($item);
                if (!$recipe) {
                    continue;
                }

                $servicePortions = optional($portions->get($item->date . '_' . $item->meal_type))->portions_count ?? 0;
                // Raciones del plato = raciones del servicio * % de comensales que lo toman.
                $portionsCount = $item->effectivePortions($servicePortions);
                if ($portionsCount <= 0) {
                    continue;
                }

                foreach ($recipe->ingredients as $ingredient) {
                    $qtyPerRation = (float) $ingredient->pivot->gross_weight;
                    if ($qtyPerRation <= 0) {
                        continue;
                    }

                    if (!$totals->has($ingredient->id)) {
                        $totals->put($ingredient->id, [
                            'id' => $ingredient->id,
                            'name' => $ingredient->name,
                            'category' => optional($ingredient->ingredient_category)->name ?? 'Sin Categoría',
                            'grams' => 0.0,
                        ]);
                    }

                    // $totals[$id]['grams'] += ... no persiste: offsetGet() de Collection devuelve el
                    // array por valor, así que hay que leer, mutar y volver a guardar explícitamente.
                    $row = $totals->get($ingredient->id);
                    $row['grams'] += $qtyPerRation * $portionsCount;
                    $totals->put($ingredient->id, $row);
                }
            }
        }

        // Cheapest registered provider price per insumo, within the chosen city only.
        $prices = Ingredient_city_provider::where('city_id', $city->id)
            ->whereIn('ingredient_id', $totals->keys())
            ->get()
            ->groupBy('ingredient_id')
            ->map(fn($rows) => $rows->pluck('cost_price')->filter(fn($price) => $price !== null)->map(fn($price) => (float) $price)->min());

        $rows = $totals->map(function ($row) use ($prices) {
            $quantityKg = $row['grams'] / 1000;
            $price = $prices->has($row['id']) ? $prices->get($row['id']) : null;

            return [
                'code' => $row['id'],
                'name' => $row['name'],
                'category' => $row['category'],
                'unit' => 'Kg.',
                'price' => $price,
                'quantity' => $quantityKg,
                'subtotal' => $price !== null ? $quantityKg * $price : null,
            ];
        });

        $categories = $rows->groupBy('category')->sortKeys()->map(function ($group, $categoryName) {
            return [
                'name' => $categoryName,
                'rows' => $group->sortBy('name')->values(),
            ];
        })->values();

        $grandTotal = $rows->sum('subtotal');
        $missingPriceCount = $rows->whereNull('subtotal')->count();

        $export = new WeeklyPurchaseOrderExport($programs, $level, $city, $categories, $grandTotal, $missingPriceCount);

        return Excel::download($export, 'Orden_Pedido_Semanal_' . now()->format('Ymd_His') . '.xlsx');
    }

    /**
     * Interactive "Reporte de Compras Semanales": same recipe-explosion requirement as
     * purchaseOrderExcel, but pivoted by comedor de destino (one column per Cafe involved in the
     * selected programs) and priced against every registered provider for the chosen city
     * (cheapest first) instead of only the minimum. Mirrors the multi-proveedor Excel sheet the
     * mines use today, computed live instead of by hand.
     *
     * Same city handling as purchaseOrderExcel: preselected from the comedor's supply city,
     * changeable by the user generating the report.
     */
    public function purchaseReport(Request $request)
    {
        $validated = $request->validate([
            'program_ids' => 'required|array|min:1',
            'program_ids.*' => 'integer|exists:weekly_programs,id',
            'level_id' => 'nullable|exists:levels,id',
            'city_id' => 'required|exists:cities,id',
        ]);

        $city = City::findOrFail($validated['city_id']);
        [$programs, $itemsByProgram, $recipes, $level] = $this->loadReportData(
            $validated['program_ids'],
            $this->fallbackLevel($validated, $validated['program_ids']),
            ['ingredients.ingredient_category']
        );

        $destinations = $programs->pluck('cafe')->filter()->unique('id')->values();

        // grams[ingredient_id]['meta'] + grams[ingredient_id]['by_cafe'][cafe_id]
        $totals = collect();

        foreach ($programs as $program) {
            $cafeId = $program->cafe_id;
            $portions = $program->portions->keyBy(fn($p) => $p->date . '_' . $p->meal_type);

            foreach ($itemsByProgram->get($program->id) ?? collect() as $item) {
                $recipe = $recipes->for($item);
                if (!$recipe) {
                    continue;
                }

                $servicePortions = optional($portions->get($item->date . '_' . $item->meal_type))->portions_count ?? 0;
                $portionsCount = $item->effectivePortions($servicePortions);
                if ($portionsCount <= 0) {
                    continue;
                }

                foreach ($recipe->ingredients as $ingredient) {
                    $qtyPerRation = (float) $ingredient->pivot->gross_weight;
                    if ($qtyPerRation <= 0) {
                        continue;
                    }

                    if (!$totals->has($ingredient->id)) {
                        $totals->put($ingredient->id, [
                            'id' => $ingredient->id,
                            'name' => $ingredient->name,
                            'category' => optional($ingredient->ingredient_category)->name ?? 'Sin Categoría',
                            'by_cafe' => [],
                        ]);
                    }

                    $row = $totals->get($ingredient->id);
                    $row['by_cafe'][$cafeId] = ($row['by_cafe'][$cafeId] ?? 0) + $qtyPerRation * $portionsCount;
                    $totals->put($ingredient->id, $row);
                }
            }
        }

        // Todos los proveedores registrados para cada insumo en la ciudad elegida, más barato primero.
        $providersByIngredient = Ingredient_city_provider::where('city_id', $city->id)
            ->whereIn('ingredient_id', $totals->keys()->all())
            ->with('provider:id,name')
            ->get()
            ->groupBy('ingredient_id')
            ->map(fn($rows) => $rows->sortBy(fn($r) => $r->cost_price ?? INF)->values()->map(fn($r) => [
                'provider_id' => $r->provider_id,
                'provider_name' => optional($r->provider)->name ?? 'N/A',
                'price' => $r->cost_price !== null ? (float) $r->cost_price : null,
            ])->values());

        // Stock actual del insumo en los comedores destino (cuando se registra vía InventoryStock).
        $stockByIngredient = InventoryStock::where('stockable_type', Ingredient::class)
            ->whereIn('stockable_id', $totals->keys()->all())
            ->whereIn('cafe_id', $destinations->pluck('id'))
            ->get()
            ->groupBy('stockable_id')
            ->map(fn($rows) => (float) $rows->sum('quantity'));

        $rows = $totals->map(function ($row) use ($providersByIngredient, $stockByIngredient, $destinations) {
            $totalGrams = array_sum($row['by_cafe']);
            $totalKg = $totalGrams / 1000;
            $providers = $providersByIngredient->get($row['id'], collect());
            $bestProvider = $providers->first(fn($p) => $p['price'] !== null);
            $bestPrice = $bestProvider['price'] ?? null;

            return [
                'id' => $row['id'],
                'name' => $row['name'],
                'category' => $row['category'],
                'unit' => 'Kg.',
                'by_cafe' => $destinations->mapWithKeys(fn($cafe) => [
                    $cafe->id => round(($row['by_cafe'][$cafe->id] ?? 0) / 1000, 3),
                ]),
                'total_kg' => round($totalKg, 3),
                'stock_kg' => round($stockByIngredient->get($row['id'], 0), 3),
                'providers' => $providers,
                'best_price' => $bestPrice,
                'subtotal' => $bestPrice !== null ? round($totalKg * $bestPrice, 2) : null,
            ];
        });

        $categories = $rows->groupBy('category')->sortKeys()->map(function ($group, $categoryName) {
            return [
                'name' => $categoryName,
                'rows' => $group->sortBy('name')->values(),
            ];
        })->values();

        return Inertia::render('planning/PurchaseReport', [
            'programs' => $programs->map(fn($p) => [
                'id' => $p->id,
                'cafe' => optional($p->cafe)->name,
                'chain' => $this->baseChainFor($p),
                'start_date' => $p->start_date,
                'end_date' => $p->end_date,
            ])->values(),
            'destinations' => $destinations->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'level' => ['name' => $level->name],
            'city' => ['id' => $city->id, 'name' => $city->name],
            'categories' => $categories,
            'max_providers' => (int) ($rows->map(fn($r) => $r['providers']->count())->max() ?? 0),
            'grand_total' => round((float) $rows->sum('subtotal'), 2),
            'missing_price_count' => $rows->whereNull('subtotal')->count(),
            'generated_at' => now()->toDateTimeString(),
        ]);
    }
}
