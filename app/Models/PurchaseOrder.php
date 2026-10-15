<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = ['weekly_program_id', 'status', 'notes'];

    /** Primera programación del quebrado (compatibilidad con órdenes de una sola programación). */
    public function program()
    {
        return $this->belongsTo(WeeklyProgram::class, 'weekly_program_id');
    }

    /** Todas las programaciones consolidadas en este quebrado. */
    public function programs()
    {
        return $this->belongsToMany(WeeklyProgram::class, 'purchase_order_weekly_program')
            ->withTimestamps()
            ->orderBy('weekly_programs.start_date')
            ->orderBy('weekly_programs.id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
