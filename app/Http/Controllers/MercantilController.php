<?php

namespace App\Http\Controllers;

use App\Models\Mercantil;
use App\Models\MercantilSale;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MercantilController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $units = Unit::where('mine_id', $user->mine_id)->orderBy('name')->get(['id', 'name']);
        $unitIds = $units->pluck('id');

        $mercantiles = Mercantil::whereIn('unit_id', $unitIds)
            ->with([
                'unit:id,name',
                'products' => function ($q) {
                    $q->select('id', 'mercantil_id', 'name', 'marca', 'category', 'price', 'stock', 'is_active', 'sku')
                      ->with('batches')
                      ->orderBy('name');
                },
                'sales' => function ($q) {
                    $q->select('id', 'mercantil_id', 'unit_id', 'user_id', 'sale_type_id', 'payment_method', 'payment_condition', 'buyer_dni', 'date', 'subtotal', 'igv', 'total', 'created_at')
                      ->with(['user:id,name', 'saleType:id,name', 'dinner:id,name,dni'])
                      ->latest()
                      ->limit(20);
                }
            ])
            ->withCount([
                'products',
                'products as active_products_count' => fn($q) => $q->where('is_active', true),
                'products as out_of_stock_count' => fn($q) => $q->where('stock', '<=', 0),
                'products as low_stock_count' => fn($q) => $q->where('stock', '>', 0)->where('stock', '<=', 5),
                'sales',
            ])
            ->withSum('sales as total_revenue', 'total')
            ->withSum('products as total_stock', 'stock')
            ->orderBy('name')
            ->get();

        $mercantilIds = $mercantiles->pluck('id');
        $today = now()->format('Y-m-d');
        $startOfMonth = now()->startOfMonth()->format('Y-m-d');

        // Sales metrics today and this month
        $todaySales = MercantilSale::whereIn('mercantil_id', $mercantilIds)
            ->where('date', $today)
            ->select('mercantil_id', DB::raw('SUM(total) as today_total'), DB::raw('COUNT(*) as today_count'))
            ->groupBy('mercantil_id')
            ->get()
            ->keyBy('mercantil_id');

        $monthSales = MercantilSale::whereIn('mercantil_id', $mercantilIds)
            ->whereBetween('date', [$startOfMonth, $today])
            ->select('mercantil_id', DB::raw('SUM(total) as month_total'), DB::raw('COUNT(*) as month_count'))
            ->groupBy('mercantil_id')
            ->get()
            ->keyBy('mercantil_id');

        // Augment each mercantil with computed properties
        $mercantiles->each(function ($m) {
            $m->total_revenue = (float) ($m->total_revenue ?? 0);
            $m->total_stock = (int) ($m->total_stock ?? 0);

            // Compute valuation
            $valuation = $m->products->reduce(function ($carry, $prod) {
                return $carry + (($prod->price ?? 0) * max(0, $prod->stock ?? 0));
            }, 0.0);
            $m->inventory_valuation = round((float) $valuation, 2);

            // Categories list
            $m->categories = $m->products->pluck('category')->filter()->unique()->values()->all();

            // Expiring batches in this mercantil
            $expiringCount = 0;
            foreach ($m->products as $prod) {
                if ($prod->batches) {
                    foreach ($prod->batches as $b) {
                        if ($b->expiration_status && $b->expiration_status !== 'ok') {
                            $expiringCount++;
                        }
                    }
                }
            }
            $m->expiring_batches_count = $expiringCount;
        });

        // Set today and month stats
        foreach ($mercantiles as $m) {
            $m->today_sales_amount = (float) ($todaySales[$m->id]->today_total ?? 0);
            $m->today_sales_count = (int) ($todaySales[$m->id]->today_count ?? 0);
            $m->month_sales_amount = (float) ($monthSales[$m->id]->month_total ?? 0);
            $m->month_sales_count = (int) ($monthSales[$m->id]->month_count ?? 0);
        }

        // Global stats aggregation
        $globalStats = [
            'total_mercantiles'        => $mercantiles->count(),
            'active_mercantiles'       => $mercantiles->where('is_active', true)->count(),
            'inactive_mercantiles'     => $mercantiles->where('is_active', false)->count(),
            'total_units'              => $units->count(),
            'total_products'           => (int) $mercantiles->sum('products_count'),
            'total_stock'              => (int) $mercantiles->sum('total_stock'),
            'total_inventory_value'    => round((float) $mercantiles->sum('inventory_valuation'), 2),
            'total_revenue'            => round((float) $mercantiles->sum('total_revenue'), 2),
            'today_sales_amount'       => round((float) $mercantiles->sum('today_sales_amount'), 2),
            'today_sales_count'        => (int) $mercantiles->sum('today_sales_count'),
            'month_sales_amount'       => round((float) $mercantiles->sum('month_sales_amount'), 2),
            'month_sales_count'        => (int) $mercantiles->sum('month_sales_count'),
            'total_low_stock_alerts'   => (int) $mercantiles->sum('low_stock_count'),
            'total_out_of_stock'       => (int) $mercantiles->sum('out_of_stock_count'),
            'total_expiring_batches'   => (int) $mercantiles->sum('expiring_batches_count'),
        ];

        return Inertia::render('mercantiles/AdminMercantil', [
            'mercantiles' => $mercantiles,
            'units'       => $units,
            'globalStats' => $globalStats,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'unit_id'   => 'required|exists:units,id',
            'name'      => 'required|string|max:255',
            'address'   => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        Mercantil::create($data);

        return redirect()->back();
    }

    public function update(Request $request, int $id)
    {
        $mercantil = Mercantil::findOrFail($id);

        $data = $request->validate([
            'unit_id'   => 'required|exists:units,id',
            'name'      => 'required|string|max:255',
            'address'   => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $mercantil->update($data);

        return redirect()->back();
    }

    public function destroy(int $id)
    {
        Mercantil::findOrFail($id)->delete();

        return redirect()->back();
    }
}
