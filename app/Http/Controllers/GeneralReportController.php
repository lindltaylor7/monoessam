<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use App\Models\Dinner;
use App\Models\Mine;
use App\Models\Sale;
use App\Models\Subdealership;
use App\Models\Ticket_detail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GeneralReportController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date',   Carbon::now()->toDateString());
        $mineId    = $request->input('mine_id');

        /* ── Mines with their units→cafes ── */
        $mines = Mine::with(['units.cafes'])->get(['id', 'name']);

        /* ── Cafe IDs filtered by mine ── */
        $cafeIds = $mineId
            ? Mine::find($mineId)?->units->flatMap->cafes->pluck('id')->all() ?? []
            : Cafe::pluck('id')->all();

        /* ── KPI: totals for the period ── */
        $totalRevenue = Sale::whereIn('cafe_id', $cafeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('total');

        $totalSales = Sale::whereIn('cafe_id', $cafeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->count();

        $totalDiners = Sale::whereIn('cafe_id', $cafeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('is_visitor', false)
            ->distinct('dinner_id')
            ->count('dinner_id');

        $totalVisitors = Sale::whereIn('cafe_id', $cafeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('is_visitor', true)
            ->count();

        /* ── Daily trend (revenue + count) ── */
        $dailyTrend = Sale::whereIn('cafe_id', $cafeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('date, count(*) as cnt, sum(total) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => [
                'date'    => Carbon::parse($r->date)->format('d/m'),
                'date_full' => $r->date,
                'count'   => (int) $r->cnt,
                'revenue' => round((float) $r->revenue, 2),
            ]);

        /* ── Revenue by mine ── */
        $revenueByMine = Mine::with(['units.cafes'])->get()->map(function ($mine) use ($startDate, $endDate) {
            $cIds = $mine->units->flatMap->cafes->pluck('id');
            $rev  = Sale::whereIn('cafe_id', $cIds)->whereBetween('date', [$startDate, $endDate])->sum('total');
            $cnt  = Sale::whereIn('cafe_id', $cIds)->whereBetween('date', [$startDate, $endDate])->count();
            return ['mine' => $mine->name, 'revenue' => round((float) $rev, 2), 'sales' => $cnt];
        })->filter(fn($r) => $r['sales'] > 0)->values();

        /* ── Revenue by cafe ── */
        $revenueByCafe = Sale::whereIn('cafe_id', $cafeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('cafe_name, sum(total) as revenue, count(*) as sales')
            ->groupBy('cafe_name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn($r) => [
                'cafe'    => $r->cafe_name ?: 'Sin nombre',
                'revenue' => round((float) $r->revenue, 2),
                'sales'   => (int) $r->sales,
            ]);

        /* ── Service type breakdown ── */
        $svcLabels = [1 => 'Desayuno', 2 => 'Almuerzo', 3 => 'Cena', 4 => 'Refrigerio'];
        $byServiceType = Ticket_detail::whereHas('ticket.sale', function ($q) use ($cafeIds, $startDate, $endDate) {
            $q->whereIn('cafe_id', $cafeIds)->whereBetween('date', [$startDate, $endDate]);
        })
            ->selectRaw('service_type, count(*) as qty, sum(unit_price) as revenue')
            ->groupBy('service_type')
            ->orderBy('service_type')
            ->get()
            ->map(fn($r) => [
                'label'   => $svcLabels[(int) $r->service_type] ?? 'Otro',
                'qty'     => (int) $r->qty,
                'revenue' => round((float) $r->revenue, 2),
            ]);

        /* ── Revenue by subdealership ── */
        $bySubdealership = DB::table('tickets')
            ->join('sales', 'tickets.sale_id', '=', 'sales.id')
            ->whereIn('sales.cafe_id', $cafeIds)
            ->whereBetween('sales.date', [$startDate, $endDate])
            ->selectRaw('tickets.subdealership_name, count(distinct sales.id) as sales, sum(sales.total) as revenue')
            ->groupBy('tickets.subdealership_name')
            ->orderByDesc('revenue')
            ->get()
            ->filter(fn($r) => !empty($r->subdealership_name))
            ->map(fn($r) => [
                'name'    => $r->subdealership_name,
                'sales'   => (int) $r->sales,
                'revenue' => round((float) $r->revenue, 2),
            ])->values();

        /* ── Top 10 most active diners ── */
        $topDiners = DB::table('sales')
            ->join('dinners', 'sales.dinner_id', '=', 'dinners.id')
            ->whereIn('sales.cafe_id', $cafeIds)
            ->whereBetween('sales.date', [$startDate, $endDate])
            ->selectRaw('dinners.name, dinners.dni, count(*) as visits, sum(sales.total) as spent')
            ->groupBy('dinners.id', 'dinners.name', 'dinners.dni')
            ->orderByDesc('visits')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'name'  => $r->name,
                'dni'   => $r->dni,
                'visits' => (int) $r->visits,
                'spent' => round((float) $r->spent, 2),
            ]);

        /* ── Visitor vs regular ratio ── */
        $visitorRatio = [
            ['label' => 'Comensales', 'count' => Sale::whereIn('cafe_id', $cafeIds)->whereBetween('date', [$startDate, $endDate])->where('is_visitor', false)->count()],
            ['label' => 'Visitantes',  'count' => Sale::whereIn('cafe_id', $cafeIds)->whereBetween('date', [$startDate, $endDate])->where('is_visitor', true)->count()],
        ];

        /* ── Previous period comparison (same duration, shifted back) ── */
        $days    = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $prevEnd = Carbon::parse($startDate)->subDay()->toDateString();
        $prevStart = Carbon::parse($prevEnd)->subDays($days - 1)->toDateString();

        $prevRevenue = Sale::whereIn('cafe_id', $cafeIds)->whereBetween('date', [$prevStart, $prevEnd])->sum('total');
        $prevSales   = Sale::whereIn('cafe_id', $cafeIds)->whereBetween('date', [$prevStart, $prevEnd])->count();

        $revenueGrowth = $prevRevenue > 0 ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : null;
        $salesGrowth   = $prevSales   > 0 ? round((($totalSales   - $prevSales)   / $prevSales)   * 100, 1) : null;

        return Inertia::render('generalreport/Index', [
            'mines'           => $mines,
            'filters'         => ['start_date' => $startDate, 'end_date' => $endDate, 'mine_id' => $mineId],
            'kpis'            => [
                'total_revenue'   => round((float) $totalRevenue, 2),
                'total_sales'     => $totalSales,
                'total_diners'    => $totalDiners,
                'total_visitors'  => $totalVisitors,
                'revenue_growth'  => $revenueGrowth,
                'sales_growth'    => $salesGrowth,
                'avg_ticket'      => $totalSales > 0 ? round($totalRevenue / $totalSales, 2) : 0,
            ],
            'daily_trend'        => $dailyTrend,
            'revenue_by_mine'    => $revenueByMine,
            'revenue_by_cafe'    => $revenueByCafe,
            'by_service_type'    => $byServiceType,
            'by_subdealership'   => $bySubdealership,
            'top_diners'         => $topDiners,
            'visitor_ratio'      => $visitorRatio,
        ]);
    }
}
