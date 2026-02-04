<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;


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

    public function stocks(): MorphMany
    {
        return $this->morphMany(InventoryStock::class, 'stockable');
    }
}
