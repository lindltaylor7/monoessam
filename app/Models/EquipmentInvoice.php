<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Provider;

class EquipmentInvoice extends Model
{
    protected $fillable = [
        'business_id', 'provider_id', 'document_type',
        'invoice_number', 'date', 'notes', 'invoice_image',
        'total_amount', 'user_id',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function computerEquipments(): HasMany
    {
        return $this->hasMany(ComputerEquipment::class);
    }

    public function kitchenEquipments(): HasMany
    {
        return $this->hasMany(KitchenEquipment::class);
    }
}
