<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use App\Models\City;
use App\Models\Dish_category;
use App\Models\DishRecipe;
use App\Models\Level;
use App\Models\MenuCycle;
use App\Models\Mine;
use App\Models\Serviceable;
use App\Models\Structure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MenuCycleController extends Controller
{
    /**
     * Columnas del listado de ciclos. Se excluye `cycle_data` a proposito: son ~100 MB de JSON
     * repartidos en miles de filas y hidratarlo entero reventaba el limite de memoria de PHP al
     * abrir /cycles. El detalle de un ciclo se pide bajo demanda en show().
     */
    public const LIST_COLUMNS = ['id', 'serviceable_id', 'name', 'days', 'created_at', 'updated_at'];

    public function index()
    {
        return Inertia::render('cycles/Index', [
            'mines'          => Mine::with(['units', 'units.cafes', 'units.cafes.services'])->get(),
            'structures'     => Structure::with('costs')->get(),
            'dishCategories' => Dish_category::all(),
            'levels'         => Level::all(),
            // Ciudades con precios y la ciudad por defecto para comedores sin ciudad de abastecimiento.
            'cities'         => City::withPrices(),
            'defaultCityId'  => City::defaultSupplyCityId(),
        ]);
    }

    /**
     * Listado de ciclos guardados bajo demanda. Son miles (8k+), asi que ya no viajan como prop del
     * indice: el modal "Ajustes de Ciclos" pide paginas filtradas y la carga de un servicio pide solo
     * los ciclos de ese servicio (`all=1`, sin paginar porque por servicio son pocos).
     */
    public function list(Request $request)
    {
        $validated = $request->validate([
            'search'         => 'nullable|string|max:255',
            'serviceable_id' => 'nullable|integer',
            'mine_id'        => 'nullable|integer',
            'unit_id'        => 'nullable|integer',
            'cafe_id'        => 'nullable|integer',
            'date_from'      => 'nullable|date',
            'date_to'        => 'nullable|date',
            'per_page'       => 'nullable|integer|min:5|max:50',
            'all'            => 'nullable|boolean',
        ]);

        $query = MenuCycle::select(self::LIST_COLUMNS)
            ->when($validated['serviceable_id'] ?? null, fn ($q, $id) => $q->where('serviceable_id', $id))
            // Mina / unidad / comedor: los ciclos cuelgan de servicios de comedor (serviceable de Cafe).
            ->when(
                ($validated['mine_id'] ?? null) || ($validated['unit_id'] ?? null) || ($validated['cafe_id'] ?? null),
                fn ($q) => $q->whereIn('serviceable_id', DB::table('serviceables')
                    ->join('cafes', 'cafes.id', '=', 'serviceables.serviceable_id')
                    ->join('units', 'units.id', '=', 'cafes.unit_id')
                    ->where('serviceables.serviceable_type', Cafe::class)
                    ->when($validated['mine_id'] ?? null, fn ($s, $id) => $s->where('units.mine_id', $id))
                    ->when($validated['unit_id'] ?? null, fn ($s, $id) => $s->where('cafes.unit_id', $id))
                    ->when($validated['cafe_id'] ?? null, fn ($s, $id) => $s->where('cafes.id', $id))
                    ->select('serviceables.id'))
            )
            ->when(trim($validated['search'] ?? ''), function ($q, $term) {
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', '%' . $term . '%');
                    if (ctype_digit($term)) {
                        $q->orWhere('id', (int) $term);
                    }
                });
            })
            ->when($validated['date_from'] ?? null, fn ($q, $date) => $q->whereDate('updated_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($q, $date) => $q->whereDate('updated_at', '<=', $date))
            ->orderBy('id', 'desc');

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json(array_merge(
            $query->paginate($validated['per_page'] ?? 10)->toArray(),
            ['grand_total' => MenuCycle::count()],
        ));
    }

    /**
     * Devuelve un unico ciclo con su `cycle_data`, para las pantallas que cargan/comparan un ciclo
     * concreto (Ciclos y Planificacion) sin traerse el resto.
     *
     * Los costos se expresan en la ciudad de abastecimiento: la que pida la pantalla (`city_id`,
     * p. ej. al copiar el ciclo a un servicio de otra ciudad) o la del comedor del propio ciclo.
     * `recalculate=1` vuelve a costear todos los dias con los precios actuales de Proveedores.
     */
    public function show(Request $request, $id)
    {
        $cycle = MenuCycle::findOrFail($id);

        $cityId = $request->integer('city_id') ?: $this->cycleCityId($cycle);

        return response()->json([
            'id'             => $cycle->id,
            'serviceable_id' => $cycle->serviceable_id,
            'name'           => $cycle->name,
            'days'           => $cycle->days,
            'city_id'        => $cityId,
            'cycle_data'     => $this->refreshTotals($cycle->cycle_data, $cityId, $request->boolean('recalculate')),
        ]);
    }

    /** Ciudad de abastecimiento del comedor/unidad del servicio del ciclo, o la por defecto. */
    private function cycleCityId(MenuCycle $cycle): ?int
    {
        return Serviceable::with('serviceable')->find($cycle->serviceable_id)?->supplyCityId()
            ?: City::defaultSupplyCityId();
    }

    /**
     * `cycle_data` es una foto congelada al guardar y no se reescribe. Se vuelve a costear un dia
     * con la receta actual (mismo calculo que Quebrados) cuando:
     *
     *  - su precio o calorias estan en 0 (el recetario migrado tenia total_cost y total_calories
     *    en 0, asi que los ciclos guardados antes quedaron en S/ 0.00 y 0 kcal);
     *  - el precio se tomo en otra ciudad, o en ninguna (fotos anteriores a la ciudad de
     *    abastecimiento): el mismo quebrado cuesta distinto en Lima que en Huancayo;
     *  - se pide recalcular todo ($force).
     *
     * Cada dia recosteado guarda city_id, missing_prices y borrowed_prices para avisar en la celda.
     */
    private function refreshTotals($cycleData, ?int $cityId, bool $force = false)
    {
        if (!is_array($cycleData)) {
            return $cycleData;
        }

        $isMissing = fn ($day, $field) => !(float) ($day[$field] ?? 0);
        $needsPrice = fn ($day) => $force || $isMissing($day, 'price') || (int) ($day['city_id'] ?? 0) !== (int) $cityId;
        $needsCalories = fn ($day) => $force || $isMissing($day, 'calories');

        $pending = [];
        foreach ($cycleData as $row) {
            foreach (($row['days'] ?? []) as $day) {
                if (is_array($day) && !empty($day['dish_id']) && !empty($day['level_id'])
                    && ($needsPrice($day) || $needsCalories($day))) {
                    $pending[$day['dish_id'] . '-' . $day['level_id']] = [$day['dish_id'], $day['level_id']];
                }
            }
        }

        if (!$pending) {
            return $cycleData;
        }

        $totals = $this->recipeTotals(array_values($pending), $cityId);

        foreach ($cycleData as &$row) {
            foreach (($row['days'] ?? []) as $dayIndex => $day) {
                $key = is_array($day) ? ($day['dish_id'] ?? '') . '-' . ($day['level_id'] ?? '') : null;
                if (!$key || !isset($totals[$key])) {
                    continue;
                }
                if ($needsPrice($day)) {
                    $row['days'][$dayIndex]['price'] = $totals[$key]['price'];
                    $row['days'][$dayIndex]['city_id'] = $cityId;
                    $row['days'][$dayIndex]['missing_prices'] = $totals[$key]['missing_prices'];
                    $row['days'][$dayIndex]['borrowed_prices'] = $totals[$key]['borrowed_prices'];
                }
                if ($needsCalories($day)) {
                    $row['days'][$dayIndex]['calories'] = $totals[$key]['calories'];
                }
            }
        }
        unset($row);

        return $cycleData;
    }

    /**
     * Costo y calorias actuales de cada receta (plato + nivel) en una ciudad, indexados por
     * "dish_id-level_id". Mismo calculo que Quebrados (DishRecipe::applyLiveTotals).
     *
     * @param  array<int, array{0: int, 1: int}>  $pairs  [dish_id, level_id]
     */
    private function recipeTotals(array $pairs, ?int $cityId)
    {
        $wanted = collect($pairs)->mapWithKeys(fn ($p) => [$p[0] . '-' . $p[1] => true]);

        return DishRecipe::whereIn('dish_id', array_unique(array_column($pairs, 0)))
            ->whereIn('level_id', array_unique(array_column($pairs, 1)))
            ->with([
                'ingredients.assignments',
                'ingredients.dosification',
                'ingredients.atwaterFactor',
                'ingredients.nutritionalFactors',
            ])
            ->get()
            ->filter(fn ($recipe) => isset($wanted[$recipe->dish_id . '-' . $recipe->level_id]))
            ->mapWithKeys(function ($recipe) use ($cityId) {
                foreach ($recipe->ingredients as $ingredient) {
                    $ingredient->gross_weight = $ingredient->pivot->gross_weight;
                    $ingredient->final_product = $ingredient->pivot->net_weight;
                }
                $recipe->applyLiveTotals($cityId);

                return [$recipe->dish_id . '-' . $recipe->level_id => [
                    'price'           => round($recipe->total_cost, 2),
                    'calories'        => round($recipe->total_calories, 2),
                    'missing_prices'  => $recipe->missing_prices,
                    'borrowed_prices' => $recipe->borrowed_prices,
                ]];
            });
    }

    /**
     * "Recalcular costos" de la pantalla de Ciclos: costo y calorias actuales, en la ciudad de
     * abastecimiento del servicio, de los platos que hay en la grilla (guardada o no).
     */
    public function prices(Request $request)
    {
        $validated = $request->validate([
            'city_id'           => 'nullable|integer|exists:cities,id',
            'items'             => 'required|array|max:2000',
            'items.*.dish_id'   => 'required|integer',
            'items.*.level_id'  => 'required|integer',
        ]);

        $cityId = $validated['city_id'] ?? City::defaultSupplyCityId();
        $pairs = collect($validated['items'])
            ->map(fn ($item) => [(int) $item['dish_id'], (int) $item['level_id']])
            ->unique(fn ($p) => $p[0] . '-' . $p[1])
            ->values()
            ->all();

        return response()->json([
            'city_id' => $cityId,
            'totals'  => $this->recipeTotals($pairs, $cityId),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|exists:menu_cycles,id',
            'serviceable_id' => 'required',
            'name' => 'nullable|string|max:255',
            'days' => 'required|integer|min:1|max:31',
            'cycle_data' => 'required|array',
        ]);

        // Quita los días vacíos (null) de cada fila conservando el número de día como clave, para que
        // `days` no se guarde como lista [null, {...}] que luego rompe la tabla al cargar el ciclo.
        $validated['cycle_data'] = array_map(function ($row) {
            if (is_array($row) && isset($row['days']) && is_array($row['days'])) {
                $row['days'] = array_filter($row['days'], fn ($day) => !empty($day));
            }
            return $row;
        }, $validated['cycle_data']);

        try {
            DB::beginTransaction();

            if ($request->id) {
                $menuCycle = MenuCycle::findOrFail($request->id);
                $menuCycle->update([
                    'serviceable_id' => $validated['serviceable_id'],
                    'name' => $validated['name'],
                    'days' => $validated['days'],
                    'cycle_data' => $validated['cycle_data'],
                ]);
            } else {
                $menuCycle = MenuCycle::create([
                    'serviceable_id' => $validated['serviceable_id'],
                    'name' => $validated['name'],
                    'days' => $validated['days'],
                    'cycle_data' => $validated['cycle_data'],
                ]);
            }

            DB::commit();

            return redirect()->back()->with('success', 'Ciclo guardado correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Error al guardar el ciclo: ' . $e->getMessage()]);
        }
    }

    public function export(Request $request, $id)
    {
        $cycle = MenuCycle::findOrFail($id);
        $hideKcal = $request->query('hide_kcal') === 'true' || $request->query('hide_kcal') === '1';
        // Mismos costos que muestra la pantalla: en la ciudad de abastecimiento del comedor.
        $cycle->cycle_data = $this->refreshTotals($cycle->cycle_data, $this->cycleCityId($cycle));
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\CycleExport($cycle, $hideKcal), 'Ciclo_' . $cycle->name . '.xlsx');
    }
}
