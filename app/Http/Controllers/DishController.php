<?php

namespace App\Http\Controllers;

use App\Models\Dish;
use App\Models\DishRecipe;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            'mesearument_unit' => 'nullable',
            'recipes' => 'nullable|array',
            'dish_categories' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $dish = Dish::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'user_id' => auth()->id(),
            ]);

            if (isset($validated['dish_categories'])) {
                $dish->dish_categories()->sync($validated['dish_categories']);
            }

            $levelIds = $request->input('mesearument_unit', []);
            if (!is_array($levelIds)) {
                $levelIds = [$levelIds];
            }

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
            'mesearument_unit' => 'nullable',
            'recipes' => 'nullable|array',
            'dish_categories' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $dish = Dish::findOrFail($id);
            $dish->update([
                'name' => $validated['name'],
                'description' => $validated['description'],
            ]);

            if (isset($validated['dish_categories'])) {
                $dish->dish_categories()->sync($validated['dish_categories']);
            }

            $levelIds = $request->input('mesearument_unit', []);
            if (!is_array($levelIds)) {
                $levelIds = [$levelIds];
            }

            $recipesData = $request->input('recipes', []);
            
            // Solo procesar recetas si se enviaron niveles
            if (!empty($levelIds)) {
                $dish->recipes()->whereNotIn('level_id', $levelIds)->delete();

                foreach ($levelIds as $levelId) {
                    $levelRecipe = $recipesData[$levelId] ?? [];

                    // Si existen recetas duplicadas para este nivel, conservar la que
                    // tiene más ingredientes y eliminar las sobrantes
                    $levelRecipes = $dish->recipes()
                        ->where('level_id', $levelId)
                        ->withCount('ingredients')
                        ->orderByDesc('ingredients_count')
                        ->orderByDesc('id')
                        ->get();

                    $recipe = $levelRecipes->first();
                    foreach ($levelRecipes->slice(1) as $duplicate) {
                        $duplicate->ingredients()->detach();
                        $duplicate->delete();
                    }

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

    public function search(Request $request, ?string $word = null)
    {
        $categoryId = $request->query('category_id');
        $levelId = $request->query('level_id');

        $query = Dish::query();

        if ($categoryId) {
            $query->whereHas('dish_categories', function ($q) use ($categoryId) {
                $q->where('dish_categories.id', $categoryId);
            });
        }

        if ($levelId) {
            $query->whereHas('recipes', function ($q) use ($levelId) {
                $q->where('level_id', $levelId);
            });
        }

        $resultLimit = 25;

        if ($word) {
            // Camino rápido: la mayoría de las búsquedas reales coinciden por substring,
            // así que primero se intenta un LIKE en SQL (rápido, no trae los ~17k platos
            // a PHP). Solo si eso no alcanza el tope se recurre al escaneo completo con
            // Levenshtein para tolerar errores de tipeo.
            $likeIds = (clone $query)
                ->where(function ($q) use ($word) {
                    $q->where('name', 'like', '%' . $word . '%')
                        ->orWhereHas('dish_categories', function ($q2) use ($word) {
                            $q2->where('name', 'like', '%' . $word . '%');
                        })
                        ->orWhereHas('recipes.level', function ($q2) use ($word) {
                            $q2->where('name', 'like', '%' . $word . '%');
                        });
                })
                ->take($resultLimit)
                ->pluck('id');

            if ($likeIds->count() >= $resultLimit) {
                $matchingIds = $likeIds->values();
            } else {
                // Con ~17k platos, cargar las relaciones pesadas (ingredientes, proveedores,
                // factores nutricionales, etc.) para toda la tabla agota la memoria. Por eso
                // primero se filtra con una consulta liviana (solo lo necesario para el
                // matching difuso) y recién después se hace el eager load completo, solo
                // para los platos que coincidieron.
                $needle = Str::lower(trim($word));

                $matchingIds = (clone $query)
                    ->select('id', 'name')
                    ->with(['dish_categories:id,name', 'recipes:id,dish_id,level_id', 'recipes.level:id,name'])
                    ->get()
                    ->map(function ($dish) use ($needle) {
                        $score = $this->fuzzyScore($needle, $dish->name);

                        foreach ($dish->dish_categories as $category) {
                            $categoryScore = $this->fuzzyScore($needle, $category->name);
                            if ($categoryScore !== null) {
                                $score = $score === null ? $categoryScore : min($score, $categoryScore);
                            }
                        }

                        foreach ($dish->recipes as $recipe) {
                            $levelScore = $this->fuzzyScore($needle, $recipe->level->name ?? null);
                            if ($levelScore !== null) {
                                $score = $score === null ? $levelScore : min($score, $levelScore);
                            }
                        }

                        return ['id' => $dish->id, 'score' => $score];
                    })
                    ->filter(fn ($row) => $row['score'] !== null)
                    // Mejor coincidencia primero (0 = substring exacto).
                    ->sortBy('score')
                    ->take($resultLimit)
                    ->pluck('id')
                    ->values();
            }

            $query->whereIn('id', $matchingIds);
        }

        $dishes = $query->with([
                'dish_categories',
                'recipes.ingredients.assignments.provider',
                'recipes.ingredients.assignments.city',
                'recipes.ingredients.nutritionalFactors',
                'recipes.ingredients.dosification',
                'recipes.ingredients.atwaterFactor',
                'recipes.level'
            ])
            ->get();

        if ($word) {
            $order = $matchingIds->flip();
            $dishes = $dishes->sortBy(fn ($dish) => $order[$dish->id] ?? PHP_INT_MAX)->values();
        }

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

    /**
     * Compara $needle contra $haystack tolerando errores de tipeo: primero por
     * substring (score 0), luego por la menor distancia de Levenshtein (contra
     * la cadena completa y contra cada palabra) que quede dentro de un umbral
     * proporcional al largo de las cadenas comparadas. Devuelve null si no matchea,
     * o un score (menor = mejor) útil para ordenar resultados por relevancia.
     */
    private function fuzzyScore(string $needle, ?string $haystack): ?int
    {
        if (!$needle || !$haystack) {
            return null;
        }

        $haystack = Str::lower($haystack);

        if (Str::contains($haystack, $needle)) {
            return 0;
        }

        $candidates = array_filter(array_merge([$haystack], preg_split('/\s+/', $haystack)));
        $best = null;

        foreach ($candidates as $candidate) {
            $distance = levenshtein(substr($needle, 0, 255), substr($candidate, 0, 255));
            $threshold = max(1, (int) floor(min(strlen($needle), strlen($candidate)) / 3));

            if ($distance <= $threshold) {
                $best = $best === null ? $distance : min($best, $distance);
            }
        }

        return $best;
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
