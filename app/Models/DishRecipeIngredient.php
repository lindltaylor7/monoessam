<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DishRecipeIngredient extends Pivot
{
    protected $table = 'dish_recipe_ingredients';

    protected $fillable = [
        'dish_recipe_id',
        'ingredient_id',
        'gross_weight',
        'solid_waste',
        'liquid_waste',
        'calories',
        'cost',
        'unit_price',
        'net_weight'
    ];
}
