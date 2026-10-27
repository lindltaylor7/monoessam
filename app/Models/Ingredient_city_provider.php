<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Ingredient_city_provider extends Model
{
    // The real table (in production/beta and local dev, with live pricing data) is named
    // "ingredient_city_providers" — Eloquent's default pluralization already resolves to that, so
    // no override is needed. The migration that creates the table singular
    // (2025_07_08_104003_create_ingredient_city_provider_table.php) predates the actual table:
    // at some point it was manually renamed to plural without a tracked migration, leaving that
    // singular table empty/unused. Don't "fix" this back to singular — see
    // 2026_09_11_114519_create_ingredient_city_providers_table_if_missing.php.
    protected $fillable = [
        'ingredient_id',
        'provider_id',
        'city_id',
        'cost_price',
        'measurement_unit_id',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
    public function measurement_unit(): BelongsTo
    {
        return $this->belongsTo(Measurement_unit::class);
    }

    /**
     * Regla única de "precio vigente" de un insumo para una ciudad: el proveedor activo con precio
     * de esa ciudad. Si la ciudad no tiene ninguno, se toma el activo de otra ciudad y se marca
     * como prestado, para que el costo no quede en 0 y la pantalla pueda avisarlo.
     *
     * Recibe las asignaciones ya cargadas del insumo (Ingredient::assignments).
     *
     * @return array{assignment: self, borrowed: bool}|null  null = sin precio en ninguna ciudad.
     */
    public static function activeFor(?Collection $assignments, ?int $cityId): ?array
    {
        $priced = ($assignments ?? collect())
            ->filter(fn ($a) => $a->is_active && (float) $a->cost_price > 0);

        $own = $cityId ? $priced->firstWhere('city_id', $cityId) : null;
        if ($own) {
            return ['assignment' => $own, 'borrowed' => false];
        }

        $other = $priced->sortBy('city_id')->first();

        return $other ? ['assignment' => $other, 'borrowed' => (bool) $cityId] : null;
    }
}
