<?php

namespace App\Imports;

use App\Models\Dosification;
use App\Models\Ingredient;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class DosificationsImport implements OnEachRow, WithHeadingRow
{
    public function onRow(Row $row)
    {
        $rowData = $row->toArray();

        // 1. Limpieza del nombre para evitar fallos por espacios
        $nombreAlimento = isset($rowData['cnomprod']) ? trim($rowData['cnomprod']) : null;

        if (!$nombreAlimento) return;

        $ingredientExistent = Ingredient::where('name', $nombreAlimento)->first();

        if (!$ingredientExistent) {
            // Opcional: Loguear alimentos que no existen para saber qué falta
            // \Log::warning("Ingrediente no encontrado: " . $nombreAlimento);
            return;
        }

        // 2. Función auxiliar para limpiar números y manejar el "NULL" de texto
        $parseNum = function ($value) {
            if (is_null($value) || strtoupper($value) === 'NULL' || $value === '') {
                return null;
            }
            // Elimina comas si el Excel viene formateado como string (ej: 1,200.50)
            $cleanNum = str_replace(',', '', $value);
            return is_numeric($cleanNum) ? $cleanNum : null;
        };

        Dosification::updateOrCreate(
            ['ingredient_id' => $ingredientExistent->id],
            [
                'energy'       => $parseNum($rowData['energ'] ?? null),
                'water'        => $parseNum($rowData['agua'] ?? null),
                'protein'      => $parseNum($rowData['prot'] ?? null),
                'lipid'        => $parseNum($rowData['lipid'] ?? null),
                'carbohydrate' => $parseNum($rowData['carbo'] ?? null),
                'fiber'        => $parseNum($rowData['fibra'] ?? null),
                'ash'          => $parseNum($rowData['ceniza'] ?? null),
                'calcium'      => $parseNum($rowData['calc'] ?? null),
                'phosphorus'   => $parseNum($rowData['fosfo'] ?? null),
                'iron'         => $parseNum($rowData['hierro'] ?? null),
                'retinol'      => $parseNum($rowData['retinol'] ?? null),
                'thiamine'     => $parseNum($rowData['tiamina'] ?? null),
                'riboflavin'   => $parseNum($rowData['riboflav'] ?? null),
                'niacin'       => $parseNum($rowData['niacin'] ?? null),
                'a_asc'        => $parseNum($rowData['acidoasc'] ?? null),
                'sodium'       => $parseNum($rowData['na'] ?? null),
                'potassium'    => $parseNum($rowData['k'] ?? null),
                'magnesium'    => $parseNum($rowData['magnesio'] ?? null),
                'zinc'         => $parseNum($rowData['zinc'] ?? null),
                'selenium'     => $parseNum($rowData['selenio'] ?? null),
                'a_folic'      => $parseNum($rowData['acidfol'] ?? null),
                'v_b6'         => $parseNum($rowData['v_b6'] ?? null),
                'v_e'          => $parseNum($rowData['v_e'] ?? null),
                'v_b12'        => $parseNum($rowData['v_b12'] ?? null),
                'v_b9'         => $parseNum($rowData['v_b9'] ?? null),
                'iodine'       => $parseNum($rowData['yodo'] ?? null),
                'cholesterol'  => $parseNum($rowData['colesterol'] ?? null),
            ]
        );
    }

    public function headingRow(): int
    {
        return 1;
    }
}
