<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClothInventory extends Model
{
    protected $fillable = ['cloth_id', 'color_id', 'cafe_id', 'quantity'];

    public function cloth()
    {
        return $this->belongsTo(Cloth::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function cafe()
    {
        return $this->belongsTo(Cafe::class);
    }
}
