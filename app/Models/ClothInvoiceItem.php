<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClothInvoiceItem extends Model
{
    protected $fillable = [
        'cloth_invoice_id',
        'cloth_id',
        'epp_id',
        'color_id',
        'size',
        'quantity',
        'unit_price',
        'total_price',
    ];

    public function invoice()
    {
        return $this->belongsTo(ClothInvoice::class, 'cloth_invoice_id');
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
