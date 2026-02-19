<?php

namespace App\Imports;

use App\Models\Dosification;
use App\Models\Ingredient;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DosificationsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        // 1. Limpieza del nombre para evitar fallos por espacios
        $nombreAlimento = isset($row['cnomprod']) ? trim($row['cnomprod']) : null;

        if (!$nombreAlimento) return null;

        $ingredientExistent = Ingredient::where('name', $nombreAlimento)->first();

        if (!$ingredientExistent) {
            // Opcional: Loguear alimentos que no existen para saber qué falta
            // \Log::warning("Ingrediente no encontrado: " . $nombreAlimento);
            return null;
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

        return new Dosification([
            'ingredient_id' => $ingredientExistent->id,
            'energy'       => $parseNum($row['energ']),
            'water'        => $parseNum($row['agua']),
            'protein'      => $parseNum($row['prot']),
            'lipid'        => $parseNum($row['lipid']),
            'carbohydrate' => $parseNum($row['carbo']),
            'fiber'        => $parseNum($row['fibra']),
            'ash'          => $parseNum($row['ceniza']),
            'calcium'      => $parseNum($row['calc']), // <-- Revisa si aquí debes dividir entre 1000 si son mg
            'phosphorus'   => $parseNum($row['fosfo']),
            'iron'         => $parseNum($row['hierro']),
            'retinol'      => $parseNum($row['retinol']),
            'thiamine'     => $parseNum($row['tiamina']),
            'riboflavin'   => $parseNum($row['riboflav']),
            'niacin'       => $parseNum($row['niacin']),
            'a_asc'        => $parseNum($row['acidoasc']),
            'sodium'       => $parseNum($row['na']),
            'potassium'    => $parseNum($row['k']),
            'magnesium'    => $parseNum($row['magnesio']),
            'zinc'         => $parseNum($row['zinc']),
            'selenium'     => $parseNum($row['selenio']),
            'a_folic'      => $parseNum($row['acidfol']),
            'v_b6'         => $parseNum($row['v_b6']),
            'v_e'          => $parseNum($row['v_e']),
            'v_b12'        => $parseNum($row['v_b12']),
            'v_b9'         => $parseNum($row['v_b9']),
            'iodine'       => $parseNum($row['yodo']),
            'cholesterol'  => $parseNum($row['colesterol']),
        ]);
    }

    public function headingRow(): int
    {
        return 1;
    }
}
