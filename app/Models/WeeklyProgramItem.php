<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyProgramItem extends Model
{
    protected $fillable = ['weekly_program_id', 'date', 'meal_type', 'dish_category_id', 'dish_id', 'percentage'];

    protected $casts = [
        'percentage' => 'float',
    ];

    /**
     * Raciones efectivas de este plato = raciones del servicio ese día escaladas por el
     * porcentaje de comensales que lo toman. Usado por todos los reportes del módulo para
     * dimensionar insumos/costos por plato en vez de asumir el 100% del servicio.
     */
    public function effectivePortions(int|float $servicePortions): int
    {
        $pct = $this->percentage === null ? 100.0 : (float) $this->percentage;

        return (int) round($servicePortions * $pct / 100);
    }

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
