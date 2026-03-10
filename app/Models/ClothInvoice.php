<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClothInvoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'business_id',
        'headquarter_id',
        'cloth_provider_id',
        'date',
        'total_amount',
        'notes',
        'invoice_image',
    ];

    public function headquarter()
    {
        return $this->belongsTo(Headquarter::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function provider()
    {
        return $this->belongsTo(ClothProvider::class, 'cloth_provider_id');
    }

    public function items()
    {
        return $this->hasMany(ClothInvoiceItem::class);
    }
}
