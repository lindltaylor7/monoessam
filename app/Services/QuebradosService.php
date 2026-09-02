<?php

namespace App\Services;

use App\Models\DishRecipe;
use App\Models\Level;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\WeeklyProgram;
use App\Models\WeeklyProgramItem;
use Illuminate\Support\Facades\DB;

class QuebradosService
{
    /**
     * Genera una Orden de Compra a partir de una Programación Semanal (proceso "Quebrados").
     *
     * Antes recorría Dish::ingredients() (tabla dish_ingredient_levels + gross_weights, la
     * generación 2 del recetario, con 0 filas en la BD), así que SIEMPRE producía una orden
     * sin líneas mientras informaba éxito. Ahora usa DishRecipe + dish_recipe_ingredients
     * (la generación vigente), el mismo camino que los tres reportes del módulo, y por eso
     * necesita el nivel de receta.
     */
    public function generatePurchaseOrder(WeeklyProgram $program, Level $level): PurchaseOrder
    {
        return DB::transaction(function () use ($program, $level) {
            $order = PurchaseOrder::create([
                'weekly_program_id' => $program->id,
                'status'            => 'pendiente',
            ]);

            $items = WeeklyProgramItem::where('weekly_program_id', $program->id)->get();

            $portions = $program->portions->groupBy(fn ($p) => $p->date . '_' . $p->meal_type);

            $recipes = DishRecipe::where('level_id', $level->id)
                ->whereIn('dish_id', $items->pluck('dish_id')->unique()->values())
                ->with('ingredients')
                ->get()
                ->keyBy('dish_id');

            // [ingredient_id => grams] acumulado en toda la semana.
            $requirements = [];

            foreach ($items as $item) {
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
}
