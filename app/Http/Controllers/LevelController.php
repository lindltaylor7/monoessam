<?php

namespace App\Http\Controllers;

use App\Models\DishRecipe;
use App\Models\Level;
use Illuminate\Http\Request;

class LevelController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:levels,name',
        ]);

        $level = Level::create([
            'name' => $request->name,
        ]);

        return response()->json($level);
    }

    /**
     * Borra un nivel de aplicación.
     *
     * `dish_recipes`, `dish_recipe_levels` y `dish_ingredient_levels` referencian `levels` con
     * ON DELETE CASCADE, así que borrar un nivel en uso arrasa en silencio con el recetario de
     * ese nivel en TODOS los platos. Se bloquea salvo que el nivel esté vacío.
     */
    public function destroy($id)
    {
        $level = Level::findOrFail($id);

        $recipesCount = DishRecipe::where('level_id', $level->id)->count();

        if ($recipesCount > 0) {
            return response()->json([
                'message' => "El nivel \"{$level->name}\" está en uso por {$recipesCount} "
                    . ($recipesCount === 1 ? 'receta' : 'recetas')
                    . '. Borrarlo eliminaría esas recetas en todos los platos. '
                    . 'Quite el nivel de esos platos antes de eliminarlo.',
                'recipes_count' => $recipesCount,
            ], 422);
        }

        $level->delete();

        return response()->json(['success' => true]);
    }
}
