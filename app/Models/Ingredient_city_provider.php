<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ingredient_city_provider extends Model
{

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
