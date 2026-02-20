<?php

namespace App\Imports;

use App\Models\Ingredient;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class IngredientsImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return new Ingredient([
            'name'        => $row['cnomprod'],
            'description' => $row['cabrund'] ?? null,
            'amount'      => $row['nvaluniequi'] ?? 0,
            'waste'       => $row['nmerma'] ?? 0
        ]);
    }

    public function headingRow(): int
    {
        return 1;
    }
}
