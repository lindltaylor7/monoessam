<?php

namespace App\Imports;

use App\Models\NutritionalFactor;
use App\Models\Ingredient;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class NutritionalFactorsImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $ingredientName = $row['cnomprod'] ?? null;
            if (!$ingredientName) {
                continue;
            }

            // Buscar ingrediente por nombre exacto
            $ingredient = Ingredient::where('name', trim($ingredientName))->first();

            if ($ingredient) {
                $nfactorcal = isset($row['nfactorcal']) ? $row['nfactorcal'] : null;
                $composition = isset($row['ncomposicion']) ? $row['ncomposicion'] : null;

                // Limpiar textos 'NULL' que vengan de excel
                if ($nfactorcal === 'NULL' || trim($nfactorcal) === '') $nfactorcal = null;
                if ($composition === 'NULL' || trim($composition) === '') $composition = null;

                NutritionalFactor::firstOrCreate([
                    'ingredient_id' => $ingredient->id,
                    'nfactorcal' => $nfactorcal,
                    'composition' => $composition,
                ]);
            }
        }
    }
}
