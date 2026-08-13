<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AtwaterSubfactor extends Model
{
    protected $fillable = [
        'atwater_factor_id',
        'name',
        'protein_kcal',
        'protein_kj',
        'fat_kcal',
        'fat_kj',
        'carb_kcal',
        'carb_kj',
    ];

    protected $casts = [
        'protein_kcal' => 'decimal:2',
        'protein_kj' => 'decimal:2',
        'fat_kcal' => 'decimal:2',
        'fat_kj' => 'decimal:2',
        'carb_kcal' => 'decimal:2',
        'carb_kj' => 'decimal:2',
    ];

    public function atwaterFactor(): BelongsTo
    {
        return $this->belongsTo(AtwaterFactor::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }
}
