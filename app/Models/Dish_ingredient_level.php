<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dish_ingredient_level extends Pivot
{
    use HasFactory;
    protected $fillable = ['dish_id', 'ingredient_id', 'level_id'];

    public function gross_weight(): HasOne
    {
        return $this->hasOne(Gross_weight::class);
    }
    public function net_weight(): HasOne
    {
        return $this->hasOne(Net_weight::class);
    }
    public function calorie(): HasOne
    {
        return $this->hasOne(Calorie::class);
    }
    public function ingredient_cost(): HasOne
    {
        return $this->hasOne(Ingredient_cost::class);
    }
    public function liquid_waste(): HasOne
    {
        return $this->hasOne(Liquid_waste::class);
    }
    public function solid_waste(): HasOne
    {
        return $this->hasOne(Solid_waste::class);
    }
}
