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
            'ingredients' => Ingredient::with(['ingredient_category', 'dosification', 'nutritionalFactors', 'atwaterFactor'])->get(),
            'categories' => \App\Models\Ingredient_category::all(),
            'atwaterFactors' => \App\Models\AtwaterFactor::orderBy('order')->get(['id', 'group', 'name', 'protein_kcal', 'fat_kcal', 'carb_kcal']),
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
            'atwater_factor_id' => 'nullable|exists:atwater_factors,id',
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
            'atwater_factor_id' => 'nullable|exists:atwater_factors,id',
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
            'carbohydrate_available' => 'nullable|numeric',
            'fiber' => 'nullable|numeric',
            'ash' => 'nullable|numeric',
            'calcium' => 'nullable|numeric',
            'phosphorus' => 'nullable|numeric',
            'iron' => 'nullable|numeric',
            'carotene' => 'nullable|numeric',
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
            'alcohol' => 'nullable|numeric',
        ]);

        $ingredient = Ingredient::findOrFail($id);
        $dosification = $ingredient->dosification()->updateOrCreate(
            ['ingredient_id' => $id],
            $validated
        );

        // Llamadas por axios (p.ej. desde el editor de platos) reciben JSON en vez del redirect que
        // espera el formulario Inertia de la página de Insumos.
        if (!$request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Dosificación actualizada correctamente',
                'dosification' => $dosification,
            ]);
        }

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

    public function substitutes(Request $request, $id)
    {
        $ingredient = Ingredient::findOrFail($id);
        $cityId = $request->integer('city_id') ?: null;

        // Primera palabra "real" del nombre: se ignoran signos y números para que
        // "- GUINDONES CON PEPA..." o "Guindones (4 x Kg)" den la misma raíz.
        preg_match_all('/\p{L}{3,}/u', $ingredient->name, $m);
        $words = $m[0];
        $baseWord = mb_strtoupper($words[0] ?? trim($ingredient->name));

        // Singular simple en español, para que "GUINDONES" encuentre también "GUINDON".
        if (mb_strlen($baseWord) > 4 && mb_substr($baseWord, -2) === 'ES') {
            $baseWord = mb_substr($baseWord, 0, -2);
        } elseif (mb_strlen($baseWord) > 3 && mb_substr($baseWord, -1) === 'S') {
            $baseWord = mb_substr($baseWord, 0, -1);
        }

        if ($baseWord === '') {
            return response()->json([]);
        }

        $substitutes = Ingredient::where('name', 'like', '%' . $baseWord . '%')
            ->where('id', '!=', $ingredient->id)
            ->with(['assignments.provider', 'assignments.city'])
            ->whereHas('assignments', fn ($q) => $q->where('cost_price', '>', 0))
            ->take(40)
            ->get();

        $target = mb_strtoupper($ingredient->name);
        $assignments = collect();
        foreach ($substitutes as $substitute) {
            similar_text($target, mb_strtoupper($substitute->name), $similarity);
            foreach ($substitute->assignments as $assignment) {
                if ((float) $assignment->cost_price <= 0) {
                    continue;
                }
                $assignment->ingredient_name = $substitute->name;
                $assignment->similarity = round($similarity, 1);
                $assignments->push($assignment);
            }
        }

        // Primero los de la ciudad de abastecimiento y activos; luego por parecido del nombre.
        $sorted = $assignments->sortBy([
            fn ($a, $b) => (int) ($cityId && $b->city_id == $cityId) <=> (int) ($cityId && $a->city_id == $cityId),
            fn ($a, $b) => (int) $b->is_active <=> (int) $a->is_active,
            fn ($a, $b) => $b->similarity <=> $a->similarity,
        ])->values();

        return response()->json($sorted);
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

    public function importDosifications(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        $file = $request->file('excel_file');
        Excel::import(new \App\Imports\DosificationsImport, $file);
        return redirect()->back()->with('success', 'Dosificaciones importadas correctamente');
    }
}
