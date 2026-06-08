<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class KitchenEquipment extends Model
{
    protected $table = 'kitchen_equipments';

    protected $fillable = [
        'name', 'brand', 'model', 'size', 'description', 'color',
        'current_type', 'series', 'manual', 'code', 'status', 'responsible_id',
        'storage_headquarter_id',
    ];

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_id');
    }

    public function storageHeadquarter(): BelongsTo
    {
        return $this->belongsTo(Headquarter::class, 'storage_headquarter_id');
    }

    public function histories(): MorphMany
    {
        return $this->morphMany(EquipmentHistory::class, 'equipable')->latest();
    }

    public function stocks(): MorphMany
    {
        return $this->morphMany(InventoryStock::class, 'stockable');
    }

    public function dispatches(): MorphMany
    {
        return $this->morphMany(EquipmentDispatch::class, 'equipable')->latest();
    }
}
