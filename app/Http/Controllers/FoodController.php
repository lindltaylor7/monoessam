<?php

namespace App\Http\Controllers;

use App\Models\Dish;
use App\Models\Dish_category;
use App\Models\Ingredient;
use App\Models\Ingredient_category;
use App\Models\Ingredient_city_provider;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FoodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $dishes = Dish::with([
            'dish_categories',
            'recipes.ingredients.assignments.provider',
            'recipes.ingredients.assignments.city',
            'recipes.ingredients.nutritionalFactors',
            'recipes.ingredients.dosification',
            'recipes.level'
        ])->take(50)->get();

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

        return Inertia::render('food/Index', [
            'dishes' => $dishes,
            'ingredient_categories' => Ingredient_category::all(),
            'dish_categories' => Dish_category::all(),
            'ingredients' => Ingredient::with(['assignments.provider', 'assignments.city', 'nutritionalFactors', 'dosification'])
                ->orderBy('name')
                ->take(300)
                ->get()
        ]);
    }

    public function searchIngredients($query)
    {
        return Ingredient::where('name', 'like', "%$query%")
            ->with(['assignments.provider', 'assignments.city', 'dosification', 'nutritionalFactors'])
            ->take(20)
            ->get();
    }

    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function structure()
    {
        return Inertia::render('structure-menu/Index', [
            'categories' => Dish_category::all(),
            'mines' => \App\Models\Mine::with(['units', 'units.cafes', 'units.cafes.services'])->get(),
            'structures' => \App\Models\Structure::with('costs')->get()
        ]);
    }

    public function storeStructure(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:structures,name',
            'serviceable_id' => 'required|exists:serviceables,id',
            'categories' => 'required|array'
        ], [
            'name.unique' => 'Ya existe una estructura guardada con ese nombre. Por favor, elige otro.',
            'serviceable_id.required' => 'Debe seleccionar un servicio antes de guardar la estructura.'
        ]);

        $structure = \App\Models\Structure::create([
            'name' => $request->name,
            'serviceable_id' => $request->serviceable_id
        ]);

        foreach ($request->categories as $category) {
            \App\Models\StructureCost::create([
                'structure_id' => $structure->id,
                'dish_category_id' => $category['id'] ?? null,
                'name' => $category['name'] ?? null,
                'order' => $category['order'] ?? 0,
                'reference_volume' => $category['reference_volume'] ?? null,
                'measurement_unit' => $category['measurement_unit'] ?? $category['mesearument_unit'] ?? null,
                'ration' => $category['ration'] ?? null,
                'unit_cost' => $category['unit_cost'] ?? null,
                'total_cost' => $category['total_cost'] ?? null,
            ]);
        }

        return back()->with('success', 'Estructura guardada exitosamente.');
    }

    public function destroyStructure($id)
    {
        $structure = \App\Models\Structure::findOrFail($id);
        $structure->delete();
        
        return back()->with('success', 'Estructura eliminada exitosamente.');
    }
}
