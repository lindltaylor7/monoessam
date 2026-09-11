<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'dinner_id', 'cafe_id', 'date', 'sale_type_id', 'payment_method_id',
        'business_id', 'business_name', 'cafe_name', 'user_id',
        'total', 'total_igv', 'discount', 'payment_status', 'status',
        'is_visitor', 'mine_id',
        // Desglose tributario (SUNAT). Las columnas existen en `sales` desde la
        // migración inicial; antes se escribían fuera de $fillable y Eloquent las
        // descartaba en silencio.
        'total_discounts', 'total_non_taxable_operations', 'total_taxable_operations',
        'total_unaffected_operations', 'total_exonerated_operations',
        'total_exported_operations', 'total_isc', 'total_other_taxes', 'total_other_charges',
    ];

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
    public function sale_details(): HasMany
    {
        return $this->hasMany(Sale_detail::class);
    }
    public function dinner(): BelongsTo
    {
        return $this->belongsTo(Dinner::class);
    }
}
