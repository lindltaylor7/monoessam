<?php

namespace App\Imports;

use App\Models\City;
use App\Models\Ingredient;
use App\Models\Ingredient_city_provider;
use App\Models\Measurement_unit;
use App\Models\Provider;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class IngredientCityProviderImport implements ToModel, WithStartRow
{
    protected $cityId;

    public function __construct($cityId)
    {
        $this->cityId = $cityId;
    }

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
        // Column mapping based on NEW image:
        // A, B, C, D: Indices 0, 1, 2, 3 - Providers
        // E: Index 4 - Ingredient Name
        // F: Index 5 - Measurement Unit
        // G, H, I, J: Indices 6, 7, 8, 9 - Prices (matching providers A, B, C, D)

        $ingredientName = trim($row[4] ?? '');
        if (!$ingredientName) return null;

        $ingredient = Ingredient::where('name', $ingredientName)->first();
        if (!$ingredient) return null;

        $unitText = trim($row[5] ?? '');
        $measurementUnitId = $unitText ? $this->resolveMeasurementUnitId($unitText) : null;

        // Iterate through the 4 provider/price slots
        for ($i = 0; $i <= 3; $i++) {
            $currentProvName = trim($row[$i] ?? '');

            if (empty($currentProvName)) {
                continue;
            }

            // Find Provider with flexible matching
            $provider = Provider::where('name', $currentProvName)->first();

            if (!$provider) {
                // Try stripping suffix after dash
                $parts = explode('-', $currentProvName);
                $baseName = trim($parts[0]);
                $provider = Provider::where('name', 'like', "%{$baseName}%")->first();
            }

            if (!$provider) {
                continue;
            }

            $currentPrice = $row[$i + 6] ?? null; // G=6, H=7, I=8, J=9
            $costPrice = is_numeric($currentPrice) && $currentPrice > 0 ? $currentPrice : null;

            Ingredient_city_provider::updateOrCreate(
                [
                    'ingredient_id' => $ingredient->id,
                    'provider_id' => $provider->id,
                    'city_id' => $this->cityId,
                ],
                [
                    'cost_price' => $costPrice,
                    'measurement_unit_id' => $measurementUnitId,
                ]
            );
        }

        return null;
    }

    protected function resolveMeasurementUnitId(string $unitText): ?int
    {
        $unit = Measurement_unit::whereRaw('LOWER(name) = ?', [mb_strtolower($unitText)])
            ->orWhereRaw('LOWER(abbreviation) = ?', [mb_strtolower($unitText)])
            ->first();

        return $unit?->id;
    }
}
