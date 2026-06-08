<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Staff_file;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();

        // Determine visibility from the route_names of the user's permissions
        $routes = $user->getAllPermissions()->pluck('route_name')->filter()->toArray();

        $canSeeSales     = count(array_intersect(['sales', 'reportsales', 'dinners'], $routes)) > 0;
        $canSeeHeadcount = in_array('headcount', $routes);

        // ── Sales section ──────────────────────────────────────────────────
        $salesStats  = null;
        $salesChart  = null;

        if ($canSeeSales) {
            $todaySales = Sale::whereDate('date', $today);

            $salesStats = [
                'todayCount'  => (clone $todaySales)->count(),
                'todayAmount' => round((clone $todaySales)->sum('total'), 2),
            ];

            $weeklySales = Sale::selectRaw('DATE(date) as day, COUNT(*) as count, SUM(total) as amount')
                ->whereDate('date', '>=', Carbon::today()->subDays(6))
                ->groupByRaw('DATE(date)')
                ->orderBy('day')
                ->get()
                ->keyBy('day');

            $salesChart = [];
            for ($i = 6; $i >= 0; $i--) {
                $date         = Carbon::today()->subDays($i)->toDateString();
                $salesChart[] = [
                    'date'   => $date,
                    'count'  => (int) ($weeklySales->get($date)?->count  ?? 0),
                    'amount' => (float) ($weeklySales->get($date)?->amount ?? 0),
                ];
            }
        }

        // ── Headcount / HR section ─────────────────────────────────────────
        $staffStats = null;
        $docAlerts  = null;

        if ($canSeeHeadcount) {
            $in30days = Carbon::today()->addDays(30);

            $staffStats = [
                'total'       => Staff::where('status', '!=', 0)->count(),
                'active'      => Staff::where('status', 1)->count(),
                'expiring'    => Staff_file::whereNotNull('expiration_date')
                    ->whereDate('expiration_date', '>=', $today)
                    ->whereDate('expiration_date', '<=', $in30days)
                    ->count(),
                'expired'     => Staff_file::whereNotNull('expiration_date')
                    ->whereDate('expiration_date', '<', $today)
                    ->count(),
            ];

            $docAlerts = Staff_file::with('staff:id,name')
                ->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '>=', $today)
                ->whereDate('expiration_date', '<=', $in30days)
                ->orderBy('expiration_date')
                ->limit(8)
                ->get()
                ->map(fn($f) => [
                    'staff_name'      => $f->staff?->name ?? '—',
                    'file_type'       => $f->file_type,
                    'expiration_date' => $f->expiration_date,
                    'days_left'       => (int) Carbon::parse($f->expiration_date)->diffInDays($today),
                ]);
        }

        return Inertia::render('Dashboard', [
            'salesStats'    => $salesStats,
            'salesChart'    => $salesChart,
            'staffStats'    => $staffStats,
            'docAlerts'     => $docAlerts,
            'totalServices' => Service::count(),
        ]);
    }
}
