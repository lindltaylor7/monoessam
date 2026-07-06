<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mercantil extends Model
{
    use HasFactory;

    protected $table = 'mercantiles';

    protected $fillable = [
        'unit_id',
        'name',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
