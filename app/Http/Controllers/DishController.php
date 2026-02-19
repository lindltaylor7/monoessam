<?php

namespace App\Http\Controllers;

use App\Models\Dish;
use App\Models\DishRecipe;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DishController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'mesearument_unit' => 'required', // This can be a single ID or array of IDs
            'ingredients' => 'nullable|array',
            'ingredients.*.id' => 'required|exists:ingredients,id',
            'ingredients.*.gross_weight' => 'nullable|numeric',
            'ingredients.*.solid_waste' => 'nullable|numeric',
            'ingredients.*.liquid_waste' => 'nullable|numeric',
            'ingredients.*.calories' => 'nullable|numeric',
            'ingredients.*.cost' => 'nullable|numeric',
            'ingredients.*.unit_price' => 'nullable|numeric',
            'ingredients.*.final_product' => 'nullable|numeric',
            'total_gross_weight' => 'nullable|numeric',
            'total_waste_weight' => 'nullable|numeric',
            'total_calories' => 'nullable|numeric',
            'total_cost' => 'nullable|numeric',
            'total_net_weight' => 'nullable|numeric',
        ]);

        try {
            DB::beginTransaction();

            $dish = Dish::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
            ]);

            // Create the recipe configuration
            $recipe = DishRecipe::create([
                'dish_id' => $dish->id,
                'name' => 'Receta ' . $dish->name,
                'total_gross_weight' => $validated['total_gross_weight'] ?? 0,
                'total_waste_weight' => $validated['total_waste_weight'] ?? 0,
                'total_calories' => $validated['total_calories'] ?? 0,
                'total_cost' => $validated['total_cost'] ?? 0,
                'total_net_weight' => $validated['total_net_weight'] ?? 0,
            ]);

            // Attach levels
            $levelIds = is_array($validated['mesearument_unit'])
                ? $validated['mesearument_unit']
                : [$validated['mesearument_unit']];
            $recipe->levels()->attach($levelIds);

            if (!empty($validated['ingredients'])) {
                foreach ($validated['ingredients'] as $ingredientData) {
                    $recipe->ingredients()->attach($ingredientData['id'], [
                        'gross_weight'  => $ingredientData['gross_weight'] ?? 0,
                        'solid_waste'   => $ingredientData['solid_waste'] ?? 0,
                        'liquid_waste'  => $ingredientData['liquid_waste'] ?? 0,
                        'calories'      => $ingredientData['calories'] ?? 0,
                        'cost'          => $ingredientData['cost'] ?? 0,
                        'unit_price'    => $ingredientData['unit_price'] ?? 0,
                        'net_weight'    => $ingredientData['final_product'] ?? 0,
                    ]);
                }
            }

            DB::commit();

            return redirect()->back();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Error saving dish: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'mesearument_unit' => 'required',
            'ingredients' => 'nullable|array',
            'ingredients.*.id' => 'required|exists:ingredients,id',
            'ingredients.*.gross_weight' => 'nullable|numeric',
            'ingredients.*.solid_waste' => 'nullable|numeric',
            'ingredients.*.liquid_waste' => 'nullable|numeric',
            'ingredients.*.calories' => 'nullable|numeric',
            'ingredients.*.cost' => 'nullable|numeric',
            'ingredients.*.unit_price' => 'nullable|numeric',
            'ingredients.*.final_product' => 'nullable|numeric',
            'total_gross_weight' => 'nullable|numeric',
            'total_waste_weight' => 'nullable|numeric',
            'total_calories' => 'nullable|numeric',
            'total_cost' => 'nullable|numeric',
            'total_net_weight' => 'nullable|numeric',
        ]);

        try {
            DB::beginTransaction();

            $dish = Dish::findOrFail($id);
            $dish->update([
                'name' => $validated['name'],
                'description' => $validated['description'],
            ]);

            // For now, we assume one primary recipe per dish or we update the existing one
            $recipe = $dish->recipes()->first();
            if (!$recipe) {
                $recipe = DishRecipe::create([
                    'dish_id' => $dish->id,
                    'name' => 'Receta ' . $dish->name,
                    'total_gross_weight' => $validated['total_gross_weight'] ?? 0,
                    'total_waste_weight' => $validated['total_waste_weight'] ?? 0,
                    'total_calories' => $validated['total_calories'] ?? 0,
                    'total_cost' => $validated['total_cost'] ?? 0,
                    'total_net_weight' => $validated['total_net_weight'] ?? 0,
                ]);
            } else {
                $recipe->update([
                    'total_gross_weight' => $validated['total_gross_weight'] ?? 0,
                    'total_waste_weight' => $validated['total_waste_weight'] ?? 0,
                    'total_calories' => $validated['total_calories'] ?? 0,
                    'total_cost' => $validated['total_cost'] ?? 0,
                    'total_net_weight' => $validated['total_net_weight'] ?? 0,
                ]);
            }

            // Sync levels
            $levelIds = is_array($validated['mesearument_unit'])
                ? $validated['mesearument_unit']
                : [$validated['mesearument_unit']];
            $recipe->levels()->sync($levelIds);

            // Sync ingredients with pivot data
            $ingredientsSync = [];
            if (!empty($validated['ingredients'])) {
                foreach ($validated['ingredients'] as $ingredientData) {
                    $ingredientsSync[$ingredientData['id']] = [
                        'gross_weight'  => $ingredientData['gross_weight'] ?? 0,
                        'solid_waste'   => $ingredientData['solid_waste'] ?? 0,
                        'liquid_waste'  => $ingredientData['liquid_waste'] ?? 0,
                        'calories'      => $ingredientData['calories'] ?? 0,
                        'cost'          => $ingredientData['cost'] ?? 0,
                        'unit_price'    => $ingredientData['unit_price'] ?? 0,
                        'net_weight'    => $ingredientData['final_product'] ?? 0,
                    ];
                }
            }
            $recipe->ingredients()->sync($ingredientsSync);

            DB::commit();

            return redirect()->back();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Error updating dish: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $dish = Dish::findOrFail($id);
            $dish->delete();
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Error deleting dish']);
        }
    }

    public function search(string $word)
    {
        $dishes = Dish::where('name', 'like', '%' . $word . '%')
            ->with(['dish_categories', 'recipes.ingredients', 'recipes.levels'])
            ->take(8)
            ->get();

        // Map the new structure to the flat format expected by the frontend (for backward compatibility)
        foreach ($dishes as $dish) {
            $recipe = $dish->recipes->first();
            if ($recipe) {
                // Set mesearument_unit to the first level ID for now
                $dish->mesearument_unit = $recipe->levels->first()?->id;

                // Map ingredients with their pivot data
                $dish->ingredients = $recipe->ingredients->map(function ($ingredient) {
                    $ingredient->gross_weight = $ingredient->pivot->gross_weight;
                    $ingredient->solid_waste = $ingredient->pivot->solid_waste;
                    $ingredient->liquid_waste = $ingredient->pivot->liquid_waste;
                    $ingredient->calories = $ingredient->pivot->calories;
                    $ingredient->cost = $ingredient->pivot->cost;
                    $ingredient->unit_price = $ingredient->pivot->unit_price;
                    $ingredient->final_product = $ingredient->pivot->net_weight;
                    return $ingredient;
                });
            } else {
                $dish->ingredients = [];
            }
        }

        return $dishes;
    }
}
