<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EppSize extends Model
{
    protected $fillable = ['epp_id', 'city_id', 'size'];

    public function epp()
    {
        return $this->belongsTo(Epp::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
