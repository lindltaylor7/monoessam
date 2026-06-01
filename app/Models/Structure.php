<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Structure extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function costs()
    {
        return $this->hasMany(StructureCost::class);
    }
}
