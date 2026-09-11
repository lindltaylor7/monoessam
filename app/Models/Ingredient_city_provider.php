<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ingredient_city_provider extends Model
{
    // Eloquent's default pluralization would resolve this to "ingredient_city_providers", but the
    // migration (2025_07_08_104003_create_ingredient_city_provider_table.php) created the table
    // singular — without this override every query against this model 500s with "table not found".
    protected $table = 'ingredient_city_provider';

    protected $fillable = [
        'ingredient_id',
        'provider_id',
        'city_id',
        'cost_price'
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
}
