<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Epp extends Model
{
    protected $fillable = ['name', 'cost_price'];

    public function clothProviders()
    {
        return $this->belongsToMany(ClothProvider::class, 'cloth_provider_epp');
    }

    public function sizes()
    {
        return $this->hasMany(EppSize::class);
    }
}
