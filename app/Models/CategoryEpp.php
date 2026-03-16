<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryEpp extends Model
{
    protected $table = 'category_epps';
    protected $fillable = ['name'];

    public function epps()
    {
        return $this->hasMany(Epp::class, 'category_epp_id');
    }
}
