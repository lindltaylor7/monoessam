<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class KitchenEquipment extends Model
{
    protected $table = 'kitchen_equipments';

    protected $fillable = [
        'name',
        'brand',
        'model',
        'size',
        'description',
        'color',
        'current_type',
        'series',
        'manual',
        'code',
        'status',
    ];

    public function stocks(): MorphMany
    {
        return $this->morphMany(InventoryStock::class, 'stockable');
    }
}
