<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Level;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        return Inertia::render('purchase-orders/Index', [
            'orders' => PurchaseOrder::with(['program.cafe.unit'])->latest()->get(),
        ]);
    }

    public function ordersIndex()
    {
        return Inertia::render('orders/Index', [
            'orders'  => PurchaseOrder::with(['program.cafe.unit'])->latest()->get(),
            'levels'  => Level::orderBy('name')->get(),
            'cities'  => City::orderBy('name')->get(),
        ]);
    }

    public function show($id)
    {
        return Inertia::render('purchase-orders/Show', [
            'order' => PurchaseOrder::with(['program.cafe', 'items.ingredient'])->findOrFail($id),
        ]);
    }

    public function destroy($id)
    {
        $order = PurchaseOrder::findOrFail($id);
        $order->delete();

        return back();
    }
}
