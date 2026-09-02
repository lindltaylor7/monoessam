<?php

namespace App\Http\Controllers;

use App\Models\WeeklyProgram;
use App\Models\WeeklyProgramItem;
use App\Models\DailyPortion;
use App\Models\Cafe;
use App\Models\City;
use App\Models\Dish_category;
use App\Models\DishRecipe;
use App\Models\Ingredient_city_provider;
use App\Models\Level;
use App\Models\MenuStructure;
use App\Models\Serviceable;
use App\Models\Service;
use App\Services\QuebradosService;
use App\Exports\WeeklyPurchaseOrderExport;
use App\Exports\WeeklyMenuExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
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
        $menuCycles = \App\Models\MenuCycle::orderBy('id', 'desc')->get();

        // Batch-fetch serviceables/services instead of querying inside the map() loop.
        $serviceableIds = $menuCycles->pluck('serviceable_id')->filter()->unique();
        $serviceables = Serviceable::whereIn('id', $serviceableIds)->get()->keyBy('id');
        $serviceIds = $serviceables->pluck('service_id')->filter()->unique();
        $services = Service::whereIn('id', $serviceIds)->get()->keyBy('id');

        $menuCycles = $menuCycles->map(function ($cycle) use ($serviceables, $services) {
            $serviceable = $serviceables->get($cycle->serviceable_id);
            $mealType = 'N/A';
            $cafeId = null;
            if ($serviceable) {
                $service = $services->get($serviceable->service_id);
                if ($service) {
                    $mealType = $service->name;
                }
                if ($serviceable->serviceable_type === \App\Models\Cafe::class) {
                    $cafeId = $serviceable->serviceable_id;
                }
            }
            $cycle->meal_type = $mealType;
            $cycle->cafe_id = $cafeId;
            return $cycle;
        });

        // Servicios (meal_type) de cada programación: se derivan de sus items, no hay columna propia.
        $servicesByProgram = WeeklyProgramItem::select('weekly_program_id', 'meal_type')
            ->distinct()
            ->get()
            ->groupBy('weekly_program_id');

        $programs = WeeklyProgram::with(['cafe.unit.mine', 'structure'])
            ->orderByDesc('created_at')
            ->get()
            ->each(function ($program) use ($servicesByProgram) {
                $program->setAttribute(
                    'services',
                    ($servicesByProgram->get($program->id) ?? collect())->pluck('meal_type')->filter()->unique()->values()->all()
                );
            });

        return Inertia::render('planning/Index', [
            'cafes' => Cafe::all(),
            'programs' => $programs,
            'dish_categories' => Dish_category::all(),
            'menu_structure' => MenuStructure::with('dish_category')->get(),
            'structures' => \App\Models\Structure::with('costs')->get(),
            'menu_cycles' => $menuCycles,
            'mines' => \App\Models\Mine::with(['units', 'units.cafes', 'units.cafes.services'])->get(),
            'levels' => Level::orderBy('name')->get(),
            'cities' => City::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cafe_id' => 'required|exists:cafes,id',
            'structure_id' => 'nullable|exists:structures,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'items' => 'required|array',
            'items.*.percentage' => 'nullable|numeric|min:0|max:100',
            'portions' => 'required|array',
        ]);

        $program = WeeklyProgram::create([
            'cafe_id' => $validated['cafe_id'],
            'structure_id' => $validated['structure_id'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'user_id' => Auth::id(),
            'status' => 'borrador',
        ]);

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
                'percentage' => $item['percentage'] ?? 100,
            ]);
        }

        foreach ($validated['portions'] as $portion) {
            DailyPortion::create([
                'weekly_program_id' => $program->id,
                'date' => $portion['date'],
                'meal_type' => $portion['meal_type'],
                'portions_count' => $portion['portions_count'],
            ]);
        }

        return redirect()->route('planning.index')->with('success', 'Plan guardado correctamente');
    }

    public function generatePurchaseOrder($id)
    {
        $program = WeeklyProgram::findOrFail($id);
        $order = $this->quebradosService->generatePurchaseOrder($program);

        return redirect()->route('purchase_orders.show', $order->id)->with('success', 'Pedido (Quebrado) generado con éxito');
    }

    /**
     * Todos los reportes de este módulo se generan sobre una o varias programaciones marcadas en
     * la pestaña "Programaciones Guardadas" (program_ids[]), igual que el Menú Semanal. Este helper
     * resuelve esas programaciones y precarga en un solo golpe sus items y las recetas del nivel
     * elegido para todos los platos involucrados.
     *
     * @return array{0: \Illuminate\Database\Eloquent\Collection, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection}
     */
    private function loadReportData(array $programIds, Level $level, array $recipeWith): array
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

        $recipes = DishRecipe::where('level_id', $level->id)
            ->whereIn('dish_id', $items->pluck('dish_id')->unique()->values())
            ->with($recipeWith)
            ->get()
            ->keyBy('dish_id');

        return [$programs, $items->groupBy('weekly_program_id'), $recipes];
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
            'level_id' => 'required|exists:levels,id',
        ]);

        $level = Level::findOrFail($validated['level_id']);
        [$programs, $itemsByProgram, $recipes] = $this->loadReportData($validated['program_ids'], $level, ['ingredients']);

        // The original paper "Quebrado" is printed one sheet per date+servicio, so that's the
        // page unit here too — a flat list rather than nested days→meals, so each page carries
        // its own full masthead, matching the real document.
        $pages = collect();

        foreach ($programs as $program) {
            $portions = $program->portions->keyBy(fn ($p) => $p->date . '_' . $p->meal_type);
            $baseChain = $this->baseChainFor($program);
            $unitName = strtoupper($program->cafe->unit->name ?? '—');
            $items = $itemsByProgram->get($program->id) ?? collect();

            foreach ($items->groupBy('date') as $date => $dayItems) {
                foreach ($dayItems->groupBy('meal_type') as $mealType => $mealItems) {
                    $portionsCount = optional($portions->get($date . '_' . $mealType))->portions_count ?? 0;

                    $dishes = $mealItems->values()->map(function ($item) use ($portionsCount, $recipes) {
                        $recipe = $recipes->get($item->dish_id);
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
            'level_id' => 'required|exists:levels,id',
        ]);

        $level = Level::findOrFail($validated['level_id']);
        [$programs, $itemsByProgram, $recipes] = $this->loadReportData(
            $validated['program_ids'],
            $level,
            ['ingredients.ingredient_category']
        );

        $flat = collect();
        $hasMatchingRecipes = false;
        $hasAnyPortions = false;

        foreach ($programs as $program) {
            $portions = $program->portions->keyBy(fn ($p) => $p->date . '_' . $p->meal_type);

            foreach ($itemsByProgram->get($program->id) ?? collect() as $item) {
                $recipe = $recipes->get($item->dish_id);
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
            'unit' => $programs->map(fn ($p) => $p->cafe->unit->name ?? null)->filter()->unique()->implode(' / ') ?: '—',
            'base' => $programs->map(fn ($p) => $this->baseChainFor($p))->filter()->unique()->implode('  ·  ') ?: '—',
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
     * ración (17 nutrientes) plus a totals row for the plato.
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
            'level_id' => 'required|exists:levels,id',
        ]);

        $level = Level::findOrFail($validated['level_id']);
        [$programs, $itemsByProgram, $recipes] = $this->loadReportData(
            $validated['program_ids'],
            $level,
            ['ingredients.dosification']
        );

        // Columnas nutricionales del reporte, en el orden de la plantilla, mapeadas a la columna
        // de `dosifications`. "CARB" cae a carbohydrate_available cuando no hay carbohydrate.
        $nutrients = [
            'ENERG' => 'energy',
            'AGUA'  => 'water',
            'PROT'  => 'protein',
            'LIPID' => 'lipid',
            'CARB'  => 'carbohydrate',
            'FIBRA' => 'fiber',
            'CENIZ' => 'ash',
            'CALC'  => 'calcium',
            'FOSF'  => 'phosphorus',
            'HIERR' => 'iron',
            'RETIN' => 'retinol',
            'TIAMI' => 'thiamine',
            'RIBOF' => 'riboflavin',
            'NIACI' => 'niacin',
            'A ASC' => 'a_asc',
            'Na'    => 'sodium',
            'K'     => 'potassium',
        ];

        $pages = collect();

        foreach ($programs as $program) {
            $portions = $program->portions->keyBy(fn ($p) => $p->date . '_' . $p->meal_type);
            $baseChain = $this->baseChainFor($program);
            $unitName = strtoupper($program->cafe->unit->name ?? '—');

            foreach (($itemsByProgram->get($program->id) ?? collect())->groupBy('date') as $date => $dayItems) {
                foreach ($dayItems->groupBy('meal_type') as $mealType => $mealItems) {
                    $portionsCount = optional($portions->get($date . '_' . $mealType))->portions_count ?? 0;

                    $categoryCounters = [];

                    $dishes = $mealItems->values()->map(function ($item, $idx) use ($portionsCount, $recipes, $nutrients, &$categoryCounters) {
                        $recipe = $recipes->get($item->dish_id);
                        // Raciones del plato = raciones del servicio * % de comensales que lo toman.
                        $dishPortions = $item->effectivePortions($portionsCount);

                        $categoryName = $item->dish_category->name ?? 'Sin categoría';
                        $categoryCounters[$categoryName] = ($categoryCounters[$categoryName] ?? 0) + 1;

                        $totals = array_fill_keys(array_keys($nutrients), 0.0);

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
                                foreach ($nutrients as $label => $column) {
                                    $per100 = 0.0;
                                    if ($dosification) {
                                        $raw = $dosification->{$column};
                                        if (($raw === null || $raw === '') && $column === 'carbohydrate') {
                                            $raw = $dosification->carbohydrate_available;
                                        }
                                        $per100 = (float) ($raw ?? 0);
                                    }
                                    $amount = $per100 * $factor;
                                    $values[$label] = $amount;
                                    $totals[$label] += $amount;
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
     * needs a city, because there's no relation from Mina/Unidad/Comedor to City in this schema —
     * ingredient prices only exist per (ingredient, city, provider) — so the city is also chosen
     * manually here. When an ingredient has no registered price for the chosen city, its row is
     * flagged instead of silently counted as zero, and the grand total excludes it.
     */
    public function purchaseOrderExcel(Request $request)
    {
        $validated = $request->validate([
            'program_ids' => 'required|array|min:1',
            'program_ids.*' => 'integer|exists:weekly_programs,id',
            'level_id' => 'required|exists:levels,id',
            'city_id' => 'required|exists:cities,id',
        ]);

        $level = Level::findOrFail($validated['level_id']);
        $city = City::findOrFail($validated['city_id']);
        [$programs, $itemsByProgram, $recipes] = $this->loadReportData(
            $validated['program_ids'],
            $level,
            ['ingredients.ingredient_category']
        );

        // Aggregate total grams needed per insumo across every date/servicio of every selected program.
        $totals = collect();

        foreach ($programs as $program) {
            $portions = $program->portions->keyBy(fn ($p) => $p->date . '_' . $p->meal_type);

            foreach ($itemsByProgram->get($program->id) ?? collect() as $item) {
                $recipe = $recipes->get($item->dish_id);
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
            ->map(fn ($rows) => (float) $rows->min('cost_price'));

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
}
