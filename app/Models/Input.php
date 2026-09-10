<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Input extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'unit_of_measure',
        'unit',
        'cost',
        'total',
    ];

    protected $casts = [
        'unit'  => 'float',
        'cost'  => 'float',
        'total' => 'float',
    ];
}
