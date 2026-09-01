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

        return Inertia::render('planning/Index', [
            'cafes' => Cafe::all(),
            'programs' => WeeklyProgram::with(['cafe.unit', 'structure'])->get(),
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
     * Builds the "Quebrado Semanal" PDF: for every dish assigned in the week, its ingredient
     * breakdown (scaled by that date+meal's portions_count) grouped by day.
     *
     * The planning grid never records which recipe "nivel" (Master/Staff/Empleado/Obrero) a
     * dish was assigned under — only dish_id — so the level is chosen by the user when they
     * request this PDF, and used to resolve every dish's DishRecipe uniformly across the week.
     */
    public function quebradoPdf(Request $request, string $id)
    {
        $validated = $request->validate([
            'level_id' => 'required|exists:levels,id',
        ]);

        $program = WeeklyProgram::with(['cafe.unit.mine'])->findOrFail($id);
        $level = Level::findOrFail($validated['level_id']);

        $items = $program->items()->with('dish', 'dish_category')->orderBy('date')->orderBy('meal_type')->get();
        $portions = $program->portions->keyBy(fn ($p) => $p->date . '_' . $p->meal_type);

        $dishIds = $items->pluck('dish_id')->unique()->values();
        $recipes = DishRecipe::where('level_id', $level->id)
            ->whereIn('dish_id', $dishIds)
            ->with('ingredients')
            ->get()
            ->keyBy('dish_id');

        // The original paper "Quebrado" is printed one sheet per date+servicio, so that's the
        // page unit here too — a flat list rather than nested days→meals, so each page carries
        // its own full masthead (Unidad/Base/Período/Fecha), matching the real document.
        $pages = collect();

        foreach ($items->groupBy('date') as $date => $dayItems) {
            foreach ($dayItems->groupBy('meal_type') as $mealType => $mealItems) {
                $portionsCount = optional($portions->get($date . '_' . $mealType))->portions_count ?? 0;

                $dishes = $mealItems->values()->map(function ($item) use ($portionsCount, $recipes) {
                    $recipe = $recipes->get($item->dish_id);

                    $ingredients = $recipe
                        ? $recipe->ingredients->map(function ($ingredient) use ($portionsCount) {
                            $qtyPerRation = (float) $ingredient->pivot->gross_weight;
                            $totalRequired = $qtyPerRation * $portionsCount;
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
                        'ingredients' => $ingredients,
                        'has_recipe' => (bool) $recipe,
                    ];
                });

                $pages->push([
                    'date' => $date,
                    'meal_type' => $mealType,
                    'portions' => $portionsCount,
                    'dishes' => $dishes,
                ]);
            }
        }

        // "Base" mirrors the mine/unit/cafe chain shown elsewhere in the app (see MenuDisplay's
        // service labels): it doesn't change per page, so it's built once here.
        $baseChain = collect([
            optional(optional($program->cafe->unit)->mine)->name,
            optional($program->cafe->unit)->name,
            optional($program->cafe)->name,
        ])->filter()->implode(' - ');

        $pdf = Pdf::loadView('pdf.weekly_quebrado', [
            'program' => $program,
            'level' => $level,
            'pages' => $pages,
            'baseChain' => $baseChain,
        ]);

        return $pdf->setPaper('a4', 'portrait')->stream("Quebrado_Semanal_{$program->id}.pdf");
    }

    /**
     * Builds the "Requerimiento x Producto" PDF: the inverse view of quebradoPdf() — instead of
     * grouping by day/servicio/plato, it groups by ingredient_category → insumo, listing every
     * date/servicio/plato across the whole week that insumo is needed for, with quantities.
     * Same per-level caveat as quebradoPdf(): the planning grid doesn't record a recipe nivel
     * per dish, so it's chosen once here and applied to every dish in the week.
     */
    public function requerimientoPdf(Request $request, string $id)
    {
        $validated = $request->validate([
            'level_id' => 'required|exists:levels,id',
        ]);

        $program = WeeklyProgram::with(['cafe.unit.mine'])->findOrFail($id);
        $level = Level::findOrFail($validated['level_id']);

        $items = $program->items()->with('dish')->orderBy('date')->orderBy('meal_type')->get();
        $portions = $program->portions->keyBy(fn ($p) => $p->date . '_' . $p->meal_type);

        $dishIds = $items->pluck('dish_id')->unique()->values();
        $recipes = DishRecipe::where('level_id', $level->id)
            ->whereIn('dish_id', $dishIds)
            ->with('ingredients.ingredient_category')
            ->get()
            ->keyBy('dish_id');

        $flat = collect();
        $hasMatchingRecipes = false;
        $hasAnyPortions = false;

        foreach ($items as $item) {
            $recipe = $recipes->get($item->dish_id);
            if ($recipe) {
                $hasMatchingRecipes = true;
            }

            $portionsCount = optional($portions->get($item->date . '_' . $item->meal_type))->portions_count ?? 0;
            if ($portionsCount > 0) {
                $hasAnyPortions = true;
            }

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

        $baseChain = collect([
            optional(optional($program->cafe->unit)->mine)->name,
            optional($program->cafe->unit)->name,
            optional($program->cafe)->name,
        ])->filter()->implode(' - ');

        $pdf = Pdf::loadView('pdf.weekly_requirement', [
            'program' => $program,
            'level' => $level,
            'categories' => $categories,
            'baseChain' => $baseChain,
            'hasMatchingRecipes' => $hasMatchingRecipes,
            'hasAnyPortions' => $hasAnyPortions,
        ]);

        return $pdf->setPaper('a4', 'portrait')->stream("Requerimiento_x_Producto_{$program->id}.pdf");
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
    public function dosificacionPdf(Request $request, string $id)
    {
        $validated = $request->validate([
            'level_id' => 'required|exists:levels,id',
        ]);

        $program = WeeklyProgram::with(['cafe.unit.mine'])->findOrFail($id);
        $level = Level::findOrFail($validated['level_id']);

        $items = $program->items()->with('dish', 'dish_category')->orderBy('date')->orderBy('meal_type')->get();
        $portions = $program->portions->keyBy(fn ($p) => $p->date . '_' . $p->meal_type);

        $dishIds = $items->pluck('dish_id')->unique()->values();
        $recipes = DishRecipe::where('level_id', $level->id)
            ->whereIn('dish_id', $dishIds)
            ->with('ingredients.dosification')
            ->get()
            ->keyBy('dish_id');

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

        foreach ($items->groupBy('date') as $date => $dayItems) {
            foreach ($dayItems->groupBy('meal_type') as $mealType => $mealItems) {
                $portionsCount = optional($portions->get($date . '_' . $mealType))->portions_count ?? 0;

                $categoryCounters = [];

                $dishes = $mealItems->values()->map(function ($item, $idx) use ($portionsCount, $recipes, $nutrients, &$categoryCounters) {
                    $recipe = $recipes->get($item->dish_id);

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
                        'portions'       => $portionsCount,
                        'ingredients'    => $ingredients,
                        'totals'         => $totals,
                        'has_recipe'     => (bool) $recipe,
                    ];
                });

                $pages->push([
                    'date'      => $date,
                    'meal_type' => $mealType,
                    'portions'  => $portionsCount,
                    'dishes'    => $dishes,
                ]);
            }
        }

        $baseChain = collect([
            optional(optional($program->cafe->unit)->mine)->name,
            optional($program->cafe->unit)->name,
            optional($program->cafe)->name,
        ])->filter()->implode(' - ');

        $pdf = Pdf::loadView('pdf.dosificacion_nutricional', [
            'program'    => $program,
            'level'      => $level,
            'pages'      => $pages,
            'baseChain'  => $baseChain,
            'nutrients'  => $nutrients,
        ]);

        return $pdf->setPaper('a4', 'landscape')->stream("Dosificacion_Nutricional_{$program->id}.pdf");
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
    public function purchaseOrderExcel(Request $request, string $id)
    {
        $validated = $request->validate([
            'level_id' => 'required|exists:levels,id',
            'city_id' => 'required|exists:cities,id',
        ]);

        $program = WeeklyProgram::with(['cafe.unit.mine'])->findOrFail($id);
        $level = Level::findOrFail($validated['level_id']);
        $city = City::findOrFail($validated['city_id']);

        $items = $program->items()->with('dish')->get();
        $portions = $program->portions->keyBy(fn ($p) => $p->date . '_' . $p->meal_type);

        $dishIds = $items->pluck('dish_id')->unique()->values();
        $recipes = DishRecipe::where('level_id', $level->id)
            ->whereIn('dish_id', $dishIds)
            ->with('ingredients.ingredient_category')
            ->get()
            ->keyBy('dish_id');

        // Aggregate total grams needed per insumo across every date/servicio of the week.
        $totals = collect();

        foreach ($items as $item) {
            $recipe = $recipes->get($item->dish_id);
            if (!$recipe) {
                continue;
            }

            $portionsCount = optional($portions->get($item->date . '_' . $item->meal_type))->portions_count ?? 0;
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

        $export = new WeeklyPurchaseOrderExport($program, $level, $city, $categories, $grandTotal, $missingPriceCount);

        return Excel::download($export, "Orden_Pedido_Semanal_{$program->id}.xlsx");
    }
}
