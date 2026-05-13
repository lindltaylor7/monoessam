<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'serviceable_id',
        'days',
        'cycle_data',
    ];

    protected $casts = [
        'cycle_data' => 'array',
    ];
}
