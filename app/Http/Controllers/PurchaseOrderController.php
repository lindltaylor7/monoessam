<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        return Inertia::render('purchase-orders/Index', [
            'orders' => PurchaseOrder::with(['program.cafe'])->get(),
        ]);
    }

    public function show($id)
    {
        return Inertia::render('purchase-orders/Show', [
            'order' => PurchaseOrder::with(['program.cafe', 'items.ingredient'])->findOrFail($id),
        ]);
    }
}
