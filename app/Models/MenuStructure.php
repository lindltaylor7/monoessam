<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuStructure extends Model
{
    protected $fillable = ['meal_type', 'dish_category_id', 'sort_order', 'cost_percentage'];

    public function dish_category()
    {
        return $this->belongsTo(Dish_category::class);
    }
}
