<?php

namespace App\Imports;

use App\Models\Dish;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DishesImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $name = $row['cnomplato'] ?? $row['cnomplato'] ?? null;

        if (!$name) {
            return null;
        }

        return new Dish([
            'name' => $name,
        ]);
    }

    public function headingRow(): int
    {
        return 1;
    }
}
