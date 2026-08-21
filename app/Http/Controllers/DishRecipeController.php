<?php

namespace App\Http\Controllers;

use App\Models\DishRecipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DishRecipeController extends Controller
{
    /**
     * Find the DishRecipe for a given dish + level (unique together), with ingredients
     * and pricing/nutrition relations flattened the same way DishController::search does.
     * Used by the quick "quebrado" editor opened from a cycle's day cell, which only knows
     * the dish_id/level_id it assigned, not the DishRecipe id itself.
     */
    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'dish_id' => 'required|integer',
            'level_id' => 'required|integer',
        ]);

        $recipe = DishRecipe::where('dish_id', $validated['dish_id'])
            ->where('level_id', $validated['level_id'])
            ->with([
                'ingredients.assignments.provider',
                'ingredients.assignments.city',
                'ingredients.nutritionalFactors',
                'ingredients.dosification',
                'ingredients.atwaterFactor',
            ])
            ->first();

        if (!$recipe) {
            return response()->json(['message' => 'No se encontró la receta para este plato y nivel.'], 404);
        }

        $recipe->ingredients = $recipe->ingredients->map(function ($ingredient) {
            $ingredient->gross_weight = $ingredient->pivot->gross_weight;
            $ingredient->solid_waste = $ingredient->pivot->solid_waste;
            $ingredient->liquid_waste = $ingredient->pivot->liquid_waste;
            $ingredient->calories = $ingredient->pivot->calories;
            $ingredient->cost = $ingredient->pivot->cost;
            $ingredient->unit_price = $ingredient->pivot->unit_price;
            $ingredient->final_product = $ingredient->pivot->net_weight;
            return $ingredient;
        });

        return response()->json($recipe);
    }

    /**
     * Update a single recipe's ingredient quantities/costs and totals in place, without
     * touching the dish's other levels/recipes. This is the master DishRecipe used across
     * the app (Alimentos module, dish search, etc.) — saving here updates it for future use
     * everywhere, but never rewrites cycle_data already persisted on other saved cycles,
     * since that data is a frozen snapshot taken at save time.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'total_gross_weight' => 'nullable|numeric',
            'total_waste_weight' => 'nullable|numeric',
            'total_calories' => 'nullable|numeric',
            'total_cost' => 'nullable|numeric',
            'total_net_weight' => 'nullable|numeric',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.id' => 'required|integer',
            'ingredients.*.gross_weight' => 'nullable|numeric',
            'ingredients.*.solid_waste' => 'nullable|numeric',
            'ingredients.*.liquid_waste' => 'nullable|numeric',
            'ingredients.*.calories' => 'nullable|numeric',
            'ingredients.*.cost' => 'nullable|numeric',
            'ingredients.*.unit_price' => 'nullable|numeric',
            'ingredients.*.final_product' => 'nullable|numeric',
        ]);

        try {
            DB::beginTransaction();

            $recipe = DishRecipe::findOrFail($id);
            $recipe->update([
                'total_gross_weight' => $validated['total_gross_weight'] ?? 0,
                'total_waste_weight' => $validated['total_waste_weight'] ?? 0,
                'total_calories' => $validated['total_calories'] ?? 0,
                'total_cost' => $validated['total_cost'] ?? 0,
                'total_net_weight' => $validated['total_net_weight'] ?? 0,
            ]);

            $ingredientsSync = [];
            foreach ($validated['ingredients'] as $ingredientData) {
                $ingredientsSync[$ingredientData['id']] = [
                    'gross_weight' => $ingredientData['gross_weight'] ?? 0,
                    'solid_waste' => $ingredientData['solid_waste'] ?? 0,
                    'liquid_waste' => $ingredientData['liquid_waste'] ?? 0,
                    'calories' => $ingredientData['calories'] ?? 0,
                    'cost' => $ingredientData['cost'] ?? 0,
                    'unit_price' => $ingredientData['unit_price'] ?? 0,
                    'net_weight' => $ingredientData['final_product'] ?? 0,
                ];
            }
            $recipe->ingredients()->sync($ingredientsSync);

            DB::commit();

            return response()->json([
                'id' => $recipe->id,
                'total_calories' => $recipe->total_calories,
                'total_cost' => $recipe->total_cost,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al guardar el quebrado: ' . $e->getMessage()], 500);
        }
    }
}
