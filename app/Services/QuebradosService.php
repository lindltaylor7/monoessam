<?php

namespace App\Services;

use App\Models\WeeklyProgram;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Ingredient;
use Illuminate\Support\Facades\DB;

class QuebradosService
{
    /**
     * Generates a Purchase Order based on a Weekly Program.
     * This is the "Quebrados" process.
     */
    public function generatePurchaseOrder(WeeklyProgram $program): PurchaseOrder
    {
        return DB::transaction(function () use ($program) {
            // 1. Create Purchase Order header
            $order = PurchaseOrder::create([
                'weekly_program_id' => $program->id,
                'status' => 'pendiente',
            ]);

            // 2. Get all dishes planned in the week
            $items = $program->items()->with(['dish.ingredients.gross_weight', 'dish.ingredients.net_weight'])->get();

            // 3. Get portions count per date and meal type
            $portions = $program->portions->groupBy(function ($item) {
                return $item->date . '_' . $item->meal_type;
            });

            $requirements = [];

            foreach ($items as $item) {
                $key = $item->date . '_' . $item->meal_type;
                $portionCount = isset($portions[$key]) ? $portions[$key]->first()->portions_count : 0;

                if ($portionCount <= 0) continue;

                foreach ($item->dish->ingredients as $ingredient) {
                    $pivot = $ingredient->pivot; // This is a Dish_ingredient_level instance
                    $grossWeight = $pivot->gross_weight;

                    $amountPerPortion = $grossWeight ? $grossWeight->amount : 0;

                    $unitModel = \App\Models\Measurement_unit::find($grossWeight ? $grossWeight->unit_measurement_id : null);
                    $unitName = $unitModel ? $unitModel->abbreviation : 'UN';

                    if ($amountPerPortion <= 0) continue;

                    $totalNeeded = $amountPerPortion * $portionCount;

                    if (!isset($requirements[$ingredient->id])) {
                        $requirements[$ingredient->id] = [
                            'amount' => 0,
                            'unit' => $unitName,
                        ];
                    }

                    $requirements[$ingredient->id]['amount'] += $totalNeeded;
                }
            }

            // 4. Save consolidated requirements
            foreach ($requirements as $ingredientId => $data) {
                // Here we would ideally convert to purchase units if different from recipe units.
                // For now, we consolidate as is.
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'ingredient_id' => $ingredientId,
                    'total_amount' => $data['amount'],
                    'unit' => $data['unit'], // This should be the abbreviation or name
                ]);
            }

            return $order;
        });
    }
}
