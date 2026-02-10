<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyPortion extends Model
{
    protected $fillable = ['weekly_program_id', 'date', 'meal_type', 'portions_count'];

    public function program()
    {
        return $this->belongsTo(WeeklyProgram::class, 'weekly_program_id');
    }
}
