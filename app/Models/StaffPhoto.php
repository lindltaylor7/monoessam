<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffPhoto extends Model
{
    protected $fillable = ['staff_id', 'url'];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
