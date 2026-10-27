<?php

namespace App\Services;

use App\Models\DishRecipe;
use App\Models\Level;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\WeeklyProgram;
use App\Models\WeeklyProgramItem;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuebradosService
{
    /**
     * Genera una Orden de Compra a partir de una o varias Programaciones Semanales (proceso
     * "Quebrados"). Con varias, los insumos se consolidan en una sola orden.
     *
     * Antes recorría Dish::ingredients() (tabla dish_ingredient_levels + gross_weights, la
     * generación 2 del recetario, con 0 filas en la BD), así que SIEMPRE producía una orden
     * sin líneas mientras informaba éxito. Ahora usa DishRecipe + dish_recipe_ingredients
     * (la generación vigente), el mismo camino que los tres reportes del módulo, y por eso
     * necesita el nivel de receta.
     *
     * @param  WeeklyProgram|Collection<int, WeeklyProgram>  $programs
     */
    public function generatePurchaseOrder(WeeklyProgram|Collection $programs, Level $level, ?int $cityId = null): PurchaseOrder
    {
        $programs = new EloquentCollection($programs instanceof WeeklyProgram ? [$programs] : $programs->values()->all());
        $programs->loadMissing(['portions', 'cafe.unit']);

        return DB::transaction(function () use ($programs, $level, $cityId) {
            $order = PurchaseOrder::create([
                // La primera programación queda como referencia; la lista completa va al pivote.
                'weekly_program_id' => $programs->first()->id,
                // Ciudad de abastecimiento: define proveedor y precio del detalle de la orden.
                'city_id'           => $cityId ?? $programs->first()->supplyCityId(),
                'status'            => 'pendiente',
            ]);
            $order->programs()->attach($programs->pluck('id'));

            $itemsByProgram = WeeklyProgramItem::whereIn('weekly_program_id', $programs->pluck('id'))
                ->get()
                ->groupBy('weekly_program_id');

            $recipes = DishRecipe::where('level_id', $level->id)
                ->whereIn('dish_id', $itemsByProgram->flatten()->pluck('dish_id')->unique()->values())
                ->with('ingredients')
                ->get()
                ->keyBy('dish_id');

            // [ingredient_id => grams] acumulado en todas las programaciones.
            $requirements = [];

            foreach ($programs as $program) {
                $portions = $program->portions->groupBy(fn ($p) => $p->date . '_' . $p->meal_type);

                foreach ($itemsByProgram->get($program->id, collect()) as $item) {
                    $recipe = $recipes->get($item->dish_id);
                    if (!$recipe) {
                        continue;
                    }

                    $key = $item->date . '_' . $item->meal_type;
                    $servicePortions = isset($portions[$key]) ? (int) $portions[$key]->first()->portions_count : 0;
                    // Raciones del plato = raciones del servicio * % de comensales que lo toman.
                    $portionCount = $item->effectivePortions($servicePortions);

                    if ($portionCount <= 0) {
                        continue;
                    }

                    foreach ($recipe->ingredients as $ingredient) {
                        $qtyPerRation = (float) $ingredient->pivot->gross_weight;
                        if ($qtyPerRation <= 0) {
                            continue;
                        }

                        $requirements[$ingredient->id] = ($requirements[$ingredient->id] ?? 0) + $qtyPerRation * $portionCount;
                    }
                }
            }

            foreach ($requirements as $ingredientId => $grams) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'ingredient_id'     => $ingredientId,
                    // gross_weight del quebrado está en gramos; se guarda en Kg como el resto
                    // del módulo (Excel "Orden de Pedido Semanal").
                    'total_amount'      => round($grams / 1000, 4),
                    'unit'              => 'Kg.',
                ]);
            }

            return $order;
        });
    }

    /**
     * Igual que generatePurchaseOrder, pero separando las programaciones por la ciudad desde la que
     * se abastece su comedor: cada ciudad compra a sus propios proveedores, así que un quebrado
     * que junta comedores de Lima y de Huancayo se convierte en una orden por ciudad.
     *
     * @param  Collection<int, WeeklyProgram>  $programs
     * @return EloquentCollection<int, PurchaseOrder>
     */
    public function generatePurchaseOrdersByCity(Collection $programs, Level $level): EloquentCollection
    {
        $programs = new EloquentCollection($programs->values()->all());
        $programs->loadMissing('cafe.unit');

        return new EloquentCollection($programs
            ->groupBy(fn (WeeklyProgram $program) => $program->supplyCityId() ?? 0)
            ->map(fn ($group, $cityId) => $this->generatePurchaseOrder($group, $level, $cityId ?: null))
            ->values()
            ->all());
    }
}
