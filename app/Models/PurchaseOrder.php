<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = ['weekly_program_id', 'status', 'notes'];

    public function program()
    {
        return $this->belongsTo(WeeklyProgram::class, 'weekly_program_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
