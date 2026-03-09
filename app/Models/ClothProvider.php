<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClothProvider extends Model
{
    protected $fillable = ['name', 'email', 'phone'];

    public function epps()
    {
        return $this->belongsToMany(Epp::class, 'cloth_provider_epp');
    }

    public function clothes()
    {
        return $this->belongsToMany(Cloth::class, 'cloth_cloth_provider');
    }
}
