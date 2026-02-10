<?php

namespace App\Services;

use App\Models\Dish;
use App\Models\Recipe;

class NutritionalCalculatorService
{
    /**
     * Calculate nutritional values for a given dish based on its recipe.
     * 
     * @param Dish $dish
     * @return array
     */
    public function calculate(Dish $dish): array
    {
        $totals = [
            'calories' => 0,
            'proteins' => 0,
            'lipids'   => 0,
            'weight'   => 0,
        ];

        // Load recipes with ingredients and units/dosifications
        $dish->load(['recipes.ingredient.dosification', 'recipes.unit']);

        foreach ($dish->recipes as $recipe) {
            $ingredient = $recipe->ingredient;
            if (!$ingredient || !$ingredient->dosification) {
                continue;
            }

            // Normalize quantity to grams based on Unit conversion factor
            // Assuming unit->conversion_factor is "multiplier to get grams"
            // e.g. Kg -> 1000, g -> 1, Lb -> 453.59
            $grams = $recipe->quantity * ($recipe->unit->conversion_factor ?? 1);

            // Dosification is typically per 100g
            $factor = $grams / 100;

            $totals['calories'] += ($ingredient->dosification->energy ?? 0) * $factor;
            $totals['proteins'] += ($ingredient->dosification->protein ?? 0) * $factor;
            $totals['lipids']   += ($ingredient->dosification->lipid ?? 0) * $factor;
            $totals['weight']   += $grams;
        }

        return [
            'calories' => round($totals['calories'], 2),
            'proteins' => round($totals['proteins'], 2),
            'lipids'   => round($totals['lipids'], 2),
            'total_weight_g' => round($totals['weight'], 2),
        ];
    }
}
