<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dosification extends Model
{
    use HasFactory;
    protected $fillable = [
        'ingredient_id',
        'energy',
        'water',
        'protein',
        'lipid',
        'carbohydrate',
        'fiber',
        'ash',
        'calcium',
        'phosphorus',
        'iron',
        'retinol',
        'thiamine',
        'riboflavin',
        'niacin',
        'a_asc',
        'sodium',
        'potassium',
        'magnesium',
        'zinc',
        'selenium',
        'a_folic',
        'v_b6',
        'v_e',
        'v_b12',
        'v_b9',
        'iodine',
        'cholesterol'
    ];
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
