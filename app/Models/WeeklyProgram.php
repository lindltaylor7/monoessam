<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyProgram extends Model
{
    protected $fillable = ['cafe_id', 'start_date', 'end_date', 'status', 'user_id'];

    public function cafe()
    {
        return $this->belongsTo(Cafe::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(WeeklyProgramItem::class);
    }

    public function portions()
    {
        return $this->hasMany(DailyPortion::class);
    }

    public function purchase_order()
    {
        return $this->hasOne(PurchaseOrder::class);
    }
}
