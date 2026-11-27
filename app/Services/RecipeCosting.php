<?php

namespace App\Services;

use App\Models\DishRecipe;
use Illuminate\Support\Collection;

/**
 * Costo y calorías actuales de recetas (plato + nivel) en una ciudad de abastecimiento. Es el mismo
 * cálculo que Quebrados (DishRecipe::applyLiveTotals) y lo comparten Ciclos y Programación, para que
 * el costo que se ve al armar el ciclo sea el mismo que se ve al programarlo.
 */
class RecipeCosting
{
    /**
     * @param  array<int, array{0: int, 1: int}>  $pairs  [dish_id, level_id]
     * @return Collection<string, array{price: float, calories: float, missing_prices: int, borrowed_prices: int}>
     *         indexado por "dish_id-level_id"
     */
    public function totals(array $pairs, ?int $cityId): Collection
    {
        if (!$pairs) {
            return collect();
        }

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
}
