<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryStock extends Model
{
    protected $fillable = [
        'stockable_id',
        'stockable_type',
        'headquarter_id',
        'cafe_id',
        'quantity',
    ];

    public function stockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function headquarter(): BelongsTo
    {
        return $this->belongsTo(Headquarter::class);
    }

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }
}
