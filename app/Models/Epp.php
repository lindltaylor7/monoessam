<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Epp extends Model
{
    protected $fillable = ['name', 'category_epp_id'];

    public function category()
    {
        return $this->belongsTo(CategoryEpp::class, 'category_epp_id');
    }

    public function clothProviders()
    {
        return $this->belongsToMany(ClothProvider::class, 'cloth_provider_epp');
    }

    public function cityProviders()
    {
        return $this->hasMany(Epp_city_provider::class);
    }

    public function sizes()
    {
        return $this->hasMany(EppSize::class);
    }

    public function availableSizes()
    {
        return $this->belongsToMany(Size::class, 'epp_size_pivot');
    }

    public function roles()
    {
        return $this->belongsToMany(\Spatie\Permission\Models\Role::class, 'epp_role')
            ->withPivot('cafe_id')
            ->withTimestamps();
    }
}
