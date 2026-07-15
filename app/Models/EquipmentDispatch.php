<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
// Cafe used for originCafe relationship

class EquipmentDispatch extends Model
{
    protected $fillable = [
        'equipable_type', 'equipable_id', 'quantity',
        'origin_headquarter_id',
        'destination_type', 'destination_id',
        'staff_id', 'description',
        'dispatch_number', 'guide_number', 'status',
        'dispatched_at', 'returned_at',
        'dispatched_by', 'received_at', 'received_by', 'reception_notes',
        'origin_cafe_id',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'returned_at'   => 'datetime',
        'received_at'   => 'datetime',
    ];

    public function equipable(): MorphTo
    {
        return $this->morphTo();
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Headquarter::class, 'origin_headquarter_id');
    }

    public function originCafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class, 'origin_cafe_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
