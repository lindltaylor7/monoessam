<?php

namespace App\Imports;

use App\Models\Ingredient;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class IngredientsEnergyImport implements OnEachRow, WithHeadingRow
{
    /**
     * @param Row $row
     */
    public function onRow(Row $row)
    {
        $data = $row->toArray();

        $name = $data['cnomprod'] ?? null;
        $energy = $data['energ'] ?? null;

        if ($name) {
            $ingredient = Ingredient::where('name', $name)->first();
            if ($ingredient) {
                $ingredient->update([
                    'energy' => $energy
                ]);
            }
        }
    }
}
