<?php

namespace App\Imports;

use App\Models\Dish;
use App\Models\DishRecipe;
use App\Models\Ingredient;
use App\Models\Level;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Str;

class DishRecipesImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        // 1. The imported recipe is stored at the Master application level,
        // matching how the UI keys recipes by (dish_id, level_id)
        $masterLevel = Level::firstOrCreate(['name' => 'Master']);

        // 2. Local cache for ingredients to avoid repeated firstOrCreate
        $ingredientCache = [];

        // Group by dish name
        $groupedDishes = $rows->groupBy(function ($row) {
            return trim($row['cnomplato'] ?? '');
        });

        foreach ($groupedDishes as $dishName => $items) {
            if (empty($dishName)) continue;

            // 1. Find or create the Dish
            $dish = Dish::firstOrCreate(
                ['name' => $dishName],
                ['description' => 'Importado de Excel']
            );

            // 2. Find or create the recipe keyed by level (same key the UI uses),
            // so re-imports and UI edits never create duplicates
            $recipe = $dish->recipes()->firstOrCreate(
                ['level_id' => $masterLevel->id],
                [
                    'name' => 'Receta ' . $dish->name,
                    'total_gross_weight' => 0,
                    'total_waste_weight' => 0,
                    'total_calories' => 0,
                    'total_cost' => 0,
                    'total_net_weight' => 0,
                ]
            );

            // 4. Process Ingredients
            $ingredientsSync = [];
            foreach ($items as $item) {
                $ingredientName = trim($item['cnomprod'] ?? '');
                if (empty($ingredientName)) continue;

                // Check cache first
                if (isset($ingredientCache[$ingredientName])) {
                    $ingredientId = $ingredientCache[$ingredientName];
                } else {
                    $ingredient = Ingredient::firstOrCreate(
                        ['name' => $ingredientName],
                        ['description' => '']
                    );
                    $ingredientId = $ingredient->id;
                    $ingredientCache[$ingredientName] = $ingredientId;
                }

                $ingredientsSync[$ingredientId] = [
                    'gross_weight'  => 0,
                    'solid_waste'   => 0,
                    'liquid_waste'  => 0,
                    'calories'      => 0,
                    'cost'          => 0,
                    'unit_price'    => 0,
                    'net_weight'    => 0,
                ];
            }

            // Sync will replace existing ingredients for this recipe with the new ones from Excel
            if (!empty($ingredientsSync)) {
                $recipe->ingredients()->syncWithoutDetaching($ingredientsSync);
            }
        }
    }

    public function headingRow(): int
    {
        return 1;
    }
}
