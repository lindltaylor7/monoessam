<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Epp_city_provider extends Model
{
    protected $fillable = ['epp_id', 'city_id', 'cloth_provider_id', 'cost_price'];

    public function epp()
    {
        return $this->belongsTo(Epp::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function provider()
    {
        return $this->belongsTo(ClothProvider::class, 'cloth_provider_id');
    }
}
