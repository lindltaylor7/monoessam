<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ComputerEquipment extends Model
{
    protected $table = 'computer_equipments';

    protected $fillable = [
        'name',
        'description',
        'brand',
        'model',
        'presentation',
        'color',
    ];

    public function stocks(): MorphMany
    {
        return $this->morphMany(InventoryStock::class, 'stockable');
    }
}
