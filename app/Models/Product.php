<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'mercantil_id',
        'name',
        'description',
        'sku',
        'category',
        'price',
        'stock',
        'is_active',
    ];

    protected $casts = [
        'price'     => 'float',
        'stock'     => 'integer',
        'is_active' => 'boolean',
    ];

    public function mercantil(): BelongsTo
    {
        return $this->belongsTo(Mercantil::class);
    }

    public function saleDetails(): HasMany
    {
        return $this->hasMany(MercantilSaleDetail::class);
    }
}
