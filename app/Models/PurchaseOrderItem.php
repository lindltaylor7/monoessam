<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'ingredient_id', 'total_amount', 'unit', 'estimated_cost'];

    public function order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
