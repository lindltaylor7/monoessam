<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dish extends Model
{
    /** @use HasFactory<\Database\Factories\MineFactory> */
    use HasFactory;
    protected $fillable = ['name', 'description', 'dish_category_id', 'user_id'];

    public function dish_categories(): BelongsToMany
    {
        return $this->belongsToMany(Dish_category::class, 'dish_category_dish', 'dish_id', 'dish_category_id');
    }
    public function levels(): BelongsToMany
    {
        return $this->belongsToMany(Level::class, 'dish_ingredient_levels', 'dish_id', 'level_id');
    }
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'dish_ingredient_levels', 'dish_id', 'ingredient_id')
            ->using(Dish_ingredient_level::class)
            ->withPivot('id');
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
