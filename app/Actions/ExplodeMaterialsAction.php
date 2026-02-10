<?php

namespace App\Actions;

use App\Models\Action;
use App\Models\DailyPortion;
use App\Models\WeeklyProgram;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Recipe;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExplodeMaterialsAction
{
    /**
     * Generate purchase orders based on scheduled menus.
     * 
     * @param Carbon $start
     * @param Carbon $end
     * @param int $cafeId
     * @return PurchaseOrder
     */
    public function execute(Carbon $start, Carbon $end, int $cafeId): PurchaseOrder
    {
        // 1. Get Scheduled Portions
        $programs = WeeklyProgram::where('cafe_id', $cafeId)
            ->whereBetween('start_date', [$start, $end])
            ->with(['items.dish.recipes.ingredient', 'items.dish.recipes.unit', 'daily_portions'])
            ->get();

        $requirements = collect();

        foreach ($programs as $program) {
            foreach ($program->items as $item) {
                // Find portion count for this day/meal
                $portionData = $program->daily_portions()
                    ->where('date', $item->date)
                    ->where('meal_type', $item->meal_type)
                    ->first();

                $portions = $portionData ? $portionData->portions_count : 0;

                if ($portions <= 0 || !$item->dish) continue;

                foreach ($item->dish->recipes as $recipe) {
                    $ingredientId = $recipe->ingredient_id;
                    $unit = $recipe->unit->name; // Prefer standardized unit
                    $quantity = $recipe->quantity * $portions;

                    if ($requirements->has($ingredientId)) {
                        $current = $requirements->get($ingredientId);
                        $current['quantity'] += $quantity;
                        $requirements->put($ingredientId, $current);
                    } else {
                        $requirements->put($ingredientId, [
                            'ingredient_id' => $ingredientId,
                            'quantity' => $quantity,
                            'unit' => $unit,
                            'name' => $recipe->ingredient->name,
                            'cost' => $recipe->ingredient->cost ?? 0 // Retrieve cost from Ingredient or Pivot
                        ]);
                    }
                }
            }
        }

        // 2. Create PO
        $po = DB::transaction(function () use ($requirements, $start, $cafeId) {
            $po = PurchaseOrder::create([
                'weekly_program_id' => null, // Or generic link
                'status' => 'draft',
                'notes' => "Materials for {$start->format('Y-m-d')} - Cafe {$cafeId}",
                'cafe_id' => $cafeId // Assumes PO table updated to support standalone or cafe link
            ]);

            foreach ($requirements as $req) {
                $po->items()->create([
                    'ingredient_id' => $req['ingredient_id'],
                    'total_amount' => $req['quantity'],
                    'unit' => $req['unit'],
                    'estimated_cost' => $req['quantity'] * $req['cost']
                ]);
            }
            return $po;
        });

        return $po;
    }
}
