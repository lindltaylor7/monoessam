<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AtwaterFactor extends Model
{
    protected $fillable = [
        'group',
        'name',
        'order',
        'protein_kcal',
        'protein_kj',
        'fat_kcal',
        'fat_kj',
        'carb_kcal',
        'carb_kj',
        'footnote',
    ];

    protected $casts = [
        'protein_kcal' => 'decimal:2',
        'protein_kj' => 'decimal:2',
        'fat_kcal' => 'decimal:2',
        'fat_kj' => 'decimal:2',
        'carb_kcal' => 'decimal:2',
        'carb_kj' => 'decimal:2',
    ];

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }
}
