<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class City extends Model
{
    use HasFactory;
    
    protected $fillable = ['name'];

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'ingredient_city_providers', 'city_id', 'provider_id');
    }
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_city_providers', 'city_id', 'ingredient_id');
    } 
}
