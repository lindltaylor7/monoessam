<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EquipmentStock extends Model
{
    protected $fillable = [
        'stockable_id',
        'stockable_type',
        'cafe_id',
        'unit_id',
        'quantity',
    ];

    public function stockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
