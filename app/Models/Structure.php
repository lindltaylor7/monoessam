<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Structure extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function costs()
    {
        return $this->hasMany(StructureCost::class);
    }

    public function serviceableRecord(): BelongsTo
    {
        return $this->belongsTo(Serviceable::class, 'serviceable_id');
    }
}
