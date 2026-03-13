<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    protected $fillable = ['name'];

    public function epps()
    {
        return $this->belongsToMany(Epp::class, 'epp_size_pivot');
    }
}
