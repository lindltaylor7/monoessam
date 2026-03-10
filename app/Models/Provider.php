<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Provider extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'type', 'email', 'phone'];

    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'ingredient_city_providers', 'provider_id', 'city_id');
    }
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_city_providers', 'provider_id', 'ingredient_id')->withPivot('cost_price');
    }
}
