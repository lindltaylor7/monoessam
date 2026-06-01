<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\NutritionalFactor;
use App\Models\Ingredient;
use Inertia\Inertia;

class NutritionalFactorController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $groupedIngredients = Ingredient::whereHas('nutritionalFactors')
            ->with('nutritionalFactors')
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $ingredients = Ingredient::orderBy('name')->get(['id', 'name']);

        return Inertia::render('nutritional/Index', [
            'groupedIngredients' => $groupedIngredients,
            'ingredients' => $ingredients,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'nfactorcal' => 'nullable|numeric|min:0',
            'composition' => 'nullable|numeric|min:0',
        ]);

        NutritionalFactor::create($validated);

        return redirect()->back()->with('success', 'Factor nutricional creado correctamente.');
    }

    public function update(Request $request, NutritionalFactor $nutritional_factor)
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'nfactorcal' => 'nullable|numeric|min:0',
            'composition' => 'nullable|numeric|min:0',
        ]);

        $nutritional_factor->update($validated);

        return redirect()->back()->with('success', 'Factor nutricional actualizado correctamente.');
    }

    public function destroy(NutritionalFactor $nutritional_factor)
    {
        $nutritional_factor->delete();

        return redirect()->back()->with('success', 'Factor nutricional eliminado correctamente.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\NutritionalFactorsImport, $request->file('file'));

        return redirect()->back()->with('success', 'Factores nutricionales importados correctamente.');
    }
}
