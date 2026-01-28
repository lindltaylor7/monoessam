<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cloth extends Model
{
    protected $fillable = ['name'];

    public function roles()
    {
        return $this->belongsToMany(\Spatie\Permission\Models\Role::class, 'cloth_role')
            ->withPivot('cafe_id')
            ->withTimestamps();
    }

    public function inventories()
    {
        return $this->hasMany(ClothInventory::class);
    }
}
