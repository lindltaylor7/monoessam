<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyProgramItem extends Model
{
    protected $fillable = ['weekly_program_id', 'date', 'meal_type', 'dish_category_id', 'dish_id'];

    public function program()
    {
        return $this->belongsTo(WeeklyProgram::class, 'weekly_program_id');
    }

    public function dish()
    {
        return $this->belongsTo(Dish::class);
    }

    public function dish_category()
    {
        return $this->belongsTo(Dish_category::class);
    }
}
