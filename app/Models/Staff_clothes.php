<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff_clothes extends Model
{
    /** @use HasFactory<\Database\Factories\StaffClothesFactory> */
    use HasFactory;

    protected $fillable = ['staff_id', 'clothe_name', 'clothing_size', 'cloth_id', 'epp_id', 'status', 'color_id', 'quantity', 'condition'];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function cloth()
    {
        return $this->belongsTo(Cloth::class);
    }

    public function epp()
    {
        return $this->belongsTo(Epp::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }
}
