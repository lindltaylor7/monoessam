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
            'mesearument_unit' => 'required',
            'recipes' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $dish = Dish::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
            ]);

            $levelIds = is_array($validated['mesearument_unit'])
                ? $validated['mesearument_unit']
                : [$validated['mesearument_unit']];

            $recipesData = $request->input('recipes', []);

            foreach ($levelIds as $levelId) {
                $levelRecipe = $recipesData[$levelId] ?? [];
                
                $recipe = DishRecipe::create([
                    'dish_id' => $dish->id,
                    'level_id' => $levelId,
                    'name' => 'Receta ' . $dish->name . ' - Nivel ' . $levelId,
                    'total_gross_weight' => $levelRecipe['total_gross_weight'] ?? 0,
                    'total_waste_weight' => $levelRecipe['total_waste_weight'] ?? 0,
                    'total_calories' => $levelRecipe['total_calories'] ?? 0,
                    'total_cost' => $levelRecipe['total_cost'] ?? 0,
                    'total_net_weight' => $levelRecipe['total_net_weight'] ?? 0,
                ]);

                if (!empty($levelRecipe['ingredients'])) {
                    foreach ($levelRecipe['ingredients'] as $ingredientData) {
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
            'recipes' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $dish = Dish::findOrFail($id);
            $dish->update([
                'name' => $validated['name'],
                'description' => $validated['description'],
            ]);

            $levelIds = is_array($validated['mesearument_unit'])
                ? $validated['mesearument_unit']
                : [$validated['mesearument_unit']];

            $recipesData = $request->input('recipes', []);
            
            $dish->recipes()->whereNotIn('level_id', $levelIds)->delete();

            foreach ($levelIds as $levelId) {
                $levelRecipe = $recipesData[$levelId] ?? [];
                
                $recipe = $dish->recipes()->where('level_id', $levelId)->first();
                if (!$recipe) {
                    $recipe = DishRecipe::create([
                        'dish_id' => $dish->id,
                        'level_id' => $levelId,
                        'name' => 'Receta ' . $dish->name . ' - Nivel ' . $levelId,
                    ]);
                }
                
                $recipe->update([
                    'total_gross_weight' => $levelRecipe['total_gross_weight'] ?? 0,
                    'total_waste_weight' => $levelRecipe['total_waste_weight'] ?? 0,
                    'total_calories' => $levelRecipe['total_calories'] ?? 0,
                    'total_cost' => $levelRecipe['total_cost'] ?? 0,
                    'total_net_weight' => $levelRecipe['total_net_weight'] ?? 0,
                ]);

                $ingredientsSync = [];
                if (!empty($levelRecipe['ingredients'])) {
                    foreach ($levelRecipe['ingredients'] as $ingredientData) {
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
            }

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
            ->with([
                'dish_categories',
                'recipes.ingredients.assignments.provider',
                'recipes.ingredients.assignments.city',
                'recipes.ingredients.nutritionalFactors',
                'recipes.ingredients.dosification',
                'recipes.level'
            ])
            ->take(8)
            ->get();

        foreach ($dishes as $dish) {
            $dish->mesearument_unit = $dish->recipes->pluck('level_id')->toArray();
            foreach ($dish->recipes as $recipe) {
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
            }
        }

        return $dishes;
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        ini_set('max_execution_time', 0); // Disable time limit for this request

        $file = $request->file('excel_file');
        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\DishRecipesImport, $file);

        return redirect()->back()->with('success', 'Platos importados correctamente');
    }
}
