<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EppSize extends Model
{
    protected $fillable = ['epp_id', 'size'];

    public function epp()
    {
        return $this->belongsTo(Epp::class);
    }
}
