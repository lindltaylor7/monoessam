<?php

namespace App\Imports;

use App\Models\Dish_category;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DishCategoriesImport implements ToModel, WithHeadingRow
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        // El usuario solicitó mapear:
        // name -> cdescripcion
        // description -> cnombabr
        
        if (empty($row['cdescripcion'])) {
            return null;
        }

        return new Dish_category([
            'name'        => $row['cdescripcion'],
            'description' => $row['cnombabr'] ?? '',
        ]);
    }
}
