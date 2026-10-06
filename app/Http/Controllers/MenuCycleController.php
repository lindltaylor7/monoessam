<?php

namespace App\Http\Controllers;

use App\Models\Dish_category;
use App\Models\DishRecipe;
use App\Models\Level;
use App\Models\MenuCycle;
use App\Models\Mine;
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
            'savedCycles'    => MenuCycle::select(self::LIST_COLUMNS)->orderBy('id', 'desc')->get(),
            'dishCategories' => Dish_category::all(),
            'levels'         => Level::all(),
        ]);
    }

    /**
     * Devuelve un unico ciclo con su `cycle_data`, para las pantallas que cargan/comparan un ciclo
     * concreto (Ciclos y Planificacion) sin traerse el resto.
     */
    public function show($id)
    {
        $cycle = MenuCycle::findOrFail($id);

        return response()->json([
            'id'             => $cycle->id,
            'serviceable_id' => $cycle->serviceable_id,
            'name'           => $cycle->name,
            'days'           => $cycle->days,
            'cycle_data'     => $this->fillMissingTotals($cycle->cycle_data),
        ]);
    }

    /**
     * `cycle_data` es una foto congelada al guardar y no se reescribe, pero el recetario migrado
     * tenia total_cost y total_calories en 0, asi que los ciclos guardados antes quedaron con
     * todos los dias en S/ 0.00 y 0 kcal. Solo los valores en 0 se completan con los de la receta
     * actual (mismo calculo que Quebrados); un valor distinto de 0 se respeta tal cual.
     */
    private function fillMissingTotals($cycleData)
    {
        if (!is_array($cycleData)) {
            return $cycleData;
        }

        $isMissing = fn ($day, $field) => !(float) ($day[$field] ?? 0);

        $pending = [];
        foreach ($cycleData as $row) {
            foreach (($row['days'] ?? []) as $day) {
                if (is_array($day) && !empty($day['dish_id']) && !empty($day['level_id'])
                    && ($isMissing($day, 'price') || $isMissing($day, 'calories'))) {
                    $pending[$day['dish_id'] . '-' . $day['level_id']] = [$day['dish_id'], $day['level_id']];
                }
            }
        }

        if (!$pending) {
            return $cycleData;
        }

        $totals = DishRecipe::whereIn('dish_id', array_unique(array_column($pending, 0)))
            ->whereIn('level_id', array_unique(array_column($pending, 1)))
            ->with([
                'ingredients.assignments',
                'ingredients.dosification',
                'ingredients.atwaterFactor',
                'ingredients.nutritionalFactors',
            ])
            ->get()
            ->mapWithKeys(function ($recipe) {
                foreach ($recipe->ingredients as $ingredient) {
                    $ingredient->gross_weight = $ingredient->pivot->gross_weight;
                    $ingredient->final_product = $ingredient->pivot->net_weight;
                    $ingredient->cost = $ingredient->pivot->cost;
                    $ingredient->unit_price = $ingredient->pivot->unit_price;
                }
                $recipe->applyLiveTotals();

                return [$recipe->dish_id . '-' . $recipe->level_id => [
                    'price'    => round($recipe->total_cost, 2),
                    'calories' => round($recipe->total_calories, 2),
                ]];
            });

        foreach ($cycleData as &$row) {
            foreach (($row['days'] ?? []) as $dayIndex => $day) {
                $key = is_array($day) ? ($day['dish_id'] ?? '') . '-' . ($day['level_id'] ?? '') : null;
                if (!$key || !isset($totals[$key])) {
                    continue;
                }
                foreach (['price', 'calories'] as $field) {
                    if ($isMissing($day, $field)) {
                        $row['days'][$dayIndex][$field] = $totals[$key][$field];
                    }
                }
            }
        }
        unset($row);

        return $cycleData;
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
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\CycleExport($cycle, $hideKcal), 'Ciclo_' . $cycle->name . '.xlsx');
    }
}
