<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MercantilSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'mercantil_id',
        'unit_id',
        'user_id',
        'sale_type_id',
        'date',
        'subtotal',
        'igv',
        'total',
    ];

    protected $casts = [
        'date'     => 'date',
        'subtotal' => 'float',
        'igv'      => 'float',
        'total'    => 'float',
    ];

    public function mercantil(): BelongsTo
    {
        return $this->belongsTo(Mercantil::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function saleType(): BelongsTo
    {
        return $this->belongsTo(Sale_type::class, 'sale_type_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(MercantilSaleDetail::class);
    }
}
