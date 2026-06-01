<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NutritionalFactor extends Model
{
    protected $fillable = ['ingredient_id', 'nfactorcal', 'composition'];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
