<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dish_ingredient_level extends Pivot
{
    use HasFactory;
    public $incrementing = true;
    protected $table = 'dish_ingredient_levels';
    protected $fillable = ['dish_id', 'ingredient_id', 'level_id'];

    public function gross_weight(): HasOne
    {
        return $this->hasOne(Gross_weight::class, 'dish_ingredient_level_id', 'id');
    }
    public function net_weight(): HasOne
    {
        return $this->hasOne(Net_weight::class, 'dish_ingredient_level_id', 'id');
    }
    public function calorie(): HasOne
    {
        return $this->hasOne(Calorie::class, 'dish_ingredient_level_id', 'id');
    }
    public function ingredient_cost(): HasOne
    {
        return $this->hasOne(Ingredient_cost::class, 'dish_ingredient_level_id', 'id');
    }
    public function liquid_waste(): HasOne
    {
        return $this->hasOne(Liquid_waste::class, 'dish_ingredient_level_id', 'id');
    }
    public function solid_waste(): HasOne
    {
        return $this->hasOne(Solid_waste::class, 'dish_ingredient_level_id', 'id');
    }
}
