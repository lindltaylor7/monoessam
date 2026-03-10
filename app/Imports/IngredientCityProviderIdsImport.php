<?php

namespace App\Imports;

use App\Models\Ingredient_city_provider;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class IngredientCityProviderIdsImport implements ToModel, WithStartRow
{
    public function startRow(): int
    {
        return 2; // Assuming the first row is headers
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Mapping according to the image:
        // A: ingredient_id (Index 0)
        // B: provider_id (Index 1)
        // C: city_id (Index 2)
        // D: cost_price (Index 3)

        $ingredient_id = $row[0] ?? null;
        $provider_id = $row[1] ?? null;
        $city_id = $row[2] ?? null;
        $cost_price = $row[3] ?? null;

        if (!$ingredient_id || !$provider_id || !$city_id || $cost_price === null) {
            return null;
        }

        // Clean price if it has commas (as seen in the image "33,50")
        if (is_string($cost_price)) {
            $cost_price = str_replace(',', '.', $cost_price);
        }

        if (!is_numeric($cost_price)) {
            return null;
        }

        return Ingredient_city_provider::updateOrCreate(
            [
                'ingredient_id' => $ingredient_id,
                'provider_id' => $provider_id,
                'city_id' => $city_id,
            ],
            [
                'cost_price' => $cost_price,
            ]
        );
    }
}
