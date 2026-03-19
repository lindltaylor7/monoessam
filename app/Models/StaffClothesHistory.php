<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffClothesHistory extends Model
{
    protected $fillable = [
        'staff_id',
        'user_id',
        'reason',
        'assigned_at',
        'items',
    ];

    protected $casts = [
        'items' => 'array',
        'assigned_at' => 'date',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
