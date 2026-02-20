<?php

namespace App\Http\Controllers;

use App\Models\Dish;
use App\Models\Dish_category;
use App\Models\Ingredient;
use App\Models\Ingredient_category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FoodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $dishes = Dish::with(['dish_categories', 'recipes.ingredients', 'recipes.levels'])->take(50)->get();

        foreach ($dishes as $dish) {
            $recipe = $dish->recipes->first();
            if ($recipe) {
                // Set mesearument_unit to the first level ID for backward compatibility in some views
                $dish->mesearument_unit = $recipe->levels->first()?->id;
                $dish->levels = $recipe->levels; // Pass the whole collection

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
                $dish->levels = [];
            }
        }

        return Inertia::render('food/Index', [
            'dishes' => $dishes,
            'ingredient_categories' => Ingredient_category::all(),
            'dish_categories' => Dish_category::all(),
            'ingredients' => Ingredient::with(['providers', 'providers.cities'])->get()
        ]);
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
            'categories' => Dish_category::all()
        ]);
    }
}
