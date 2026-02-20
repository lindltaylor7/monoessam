<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Level extends Model
{

    use HasFactory;
    protected $fillable = ['name'];
    public function dishes(): BelongsToMany
    {
        return $this->belongsToMany(Dish::class, 'dish_ingredient_levels', 'level_id', 'dish_id');
    }
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'dish_ingredient_levels', 'level_id', 'ingredient_id');
    }
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(DishRecipe::class, 'dish_recipe_levels')
            ->withTimestamps();
    }
}
