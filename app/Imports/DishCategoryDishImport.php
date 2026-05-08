<?php

namespace App\Imports;

use App\Models\Dish;
use App\Models\Dish_category;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class DishCategoryDishImport implements OnEachRow, WithHeadingRow
{
    public function onRow(Row $row)
    {
        $data = $row->toArray();
        $categoryName = trim($data['cdescripcion'] ?? '');
        $dishName = trim($data['cnomplato'] ?? '');

        if (empty($categoryName) || empty($dishName)) {
            return;
        }

        // Buscar la categoría por nombre (se importó cDescripcion como name anteriormente)
        $category = Dish_category::where('name', $categoryName)->first();
        
        // Buscar el plato por nombre
        $dish = Dish::where('name', $dishName)->first();

        if ($category && $dish) {
            // Vincular el plato con la categoría en la tabla dish_category_dish
            $dish->dish_categories()->syncWithoutDetaching([$category->id]);
        }
    }
}
