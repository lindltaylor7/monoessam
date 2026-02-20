<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DishRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'dish_id',
        'name',
        'total_gross_weight',
        'total_waste_weight',
        'total_calories',
        'total_cost',
        'total_net_weight',
    ];

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'dish_recipe_ingredients')
            ->using(DishRecipeIngredient::class)
            ->withPivot([
                'gross_weight',
                'solid_waste',
                'liquid_waste',
                'calories',
                'cost',
                'unit_price',
                'net_weight'
            ])
            ->withTimestamps();
    }

    public function levels(): BelongsToMany
    {
        return $this->belongsToMany(Level::class, 'dish_recipe_levels')
            ->withTimestamps();
    }
}
