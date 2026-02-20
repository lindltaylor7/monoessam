<?php

namespace App\Http\Controllers;

use App\Imports\DosificationsImport;
use App\Models\Ingredient;
use App\Models\Provider;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class IngredientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('food/Inputs', [
            'ingredients' => Ingredient::with(['ingredient_category', 'dosification'])->get(),
            'categories' => \App\Models\Ingredient_category::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'nullable|numeric',
            'waste' => 'nullable|numeric',
            'energy' => 'nullable|numeric',
            'ingredient_category_id' => 'nullable|exists:ingredient_categories,id',
        ]);

        Ingredient::create($validated);

        return redirect()->back()->with('success', 'Ingrediente creado correctamente');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'nullable|numeric',
            'waste' => 'nullable|numeric',
            'energy' => 'nullable|numeric',
            'ingredient_category_id' => 'nullable|exists:ingredient_categories,id',
        ]);

        $ingredient = Ingredient::findOrFail($id);
        $ingredient->update($validated);

        return redirect()->back()->with('success', 'Ingrediente actualizado correctamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $ingredient = Ingredient::findOrFail($id);
        $ingredient->delete();

        return redirect()->back()->with('success', 'Ingrediente eliminado correctamente');
    }

    public function updateDosification(Request $request, string $id)
    {
        $validated = $request->validate([
            'energy' => 'nullable|numeric',
            'water' => 'nullable|numeric',
            'protein' => 'nullable|numeric',
            'lipid' => 'nullable|numeric',
            'carbohydrate' => 'nullable|numeric',
            'fiber' => 'nullable|numeric',
            'ash' => 'nullable|numeric',
            'calcium' => 'nullable|numeric',
            'phosphorus' => 'nullable|numeric',
            'iron' => 'nullable|numeric',
            'retinol' => 'nullable|numeric',
            'thiamine' => 'nullable|numeric',
            'riboflavin' => 'nullable|numeric',
            'niacin' => 'nullable|numeric',
            'a_asc' => 'nullable|numeric',
            'sodium' => 'nullable|numeric',
            'potassium' => 'nullable|numeric',
            'magnesium' => 'nullable|numeric',
            'zinc' => 'nullable|numeric',
            'selenium' => 'nullable|numeric',
            'a_folic' => 'nullable|numeric',
            'v_b6' => 'nullable|numeric',
            'v_e' => 'nullable|numeric',
            'v_b12' => 'nullable|numeric',
            'v_b9' => 'nullable|numeric',
            'iodine' => 'nullable|numeric',
            'cholesterol' => 'nullable|numeric',
        ]);

        $ingredient = Ingredient::findOrFail($id);
        $ingredient->dosification()->updateOrCreate(
            ['ingredient_id' => $id],
            $validated
        );

        return redirect()->back()->with('success', 'Dosificación actualizada correctamente');
    }

    public function search(Request $request)
    {
        $word = $request->word;
        $ingredients = Ingredient::where('name', 'like', "%$word%")
            ->limit(10)
            ->get();

        return response()->json($ingredients);
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        $file = $request->file('excel_file');
        Excel::import(new \App\Imports\IngredientsImport, $file);
        return redirect()->back()->with('success', 'Ingredientes importados correctamente');
    }

    public function importEnergy(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        $file = $request->file('excel_file');
        Excel::import(new \App\Imports\IngredientsEnergyImport, $file);
        return redirect()->back()->with('success', 'Energía de ingredientes actualizada correctamente');
    }
}
