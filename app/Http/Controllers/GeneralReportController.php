<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use App\Models\Mercantil;
use App\Models\MercantilSale;
use App\Models\Mine;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Subdealership;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GeneralReportController extends Controller
{
    /** service_type -> etiqueta legible (los ids vienen del catálogo de servicios del POS). */
    private const SERVICE_LABELS = [1 => 'Desayuno', 4 => 'Almuerzo', 8 => 'Cena'];

    /** Lunes → domingo en ISO (Carbon::dayOfWeekIso), para no depender de DAYOFWEEK de MySQL. */
    private const WEEKDAY_LABELS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    /** Bajo este stock un producto de mercantil entra al panel de reposición. */
    private const LOW_STOCK_THRESHOLD = 10;

    public function index(Request $request)
    {
        $f = $this->resolveFilters($request);

        return Inertia::render('generalreport/Index', [
            /* ── Catálogos para los selects de filtro ── */
            'mines'               => Mine::with(['units:id,mine_id,name', 'units.cafes:id,unit_id,name'])->get(['id', 'name']),
            'subdealerships'      => Subdealership::orderBy('name')->get(['id', 'name']),
            'mercantiles'         => Mercantil::with('unit:id,mine_id,name')->orderBy('name')->get(['id', 'unit_id', 'name']),
            'service_types'       => collect(self::SERVICE_LABELS)->map(fn ($label, $id) => ['id' => $id, 'label' => $label])->values(),
            'payment_methods'     => $this->mercantilPaymentMethods(),

            'filters'             => $f['public'],

            /* ── Pestaña Comedores ── */
            'kpis'                => $this->cafeKpis($f),
            'daily_trend'         => $f['daily_trend'],
            'org_breakdown'       => $this->orgBreakdown($f),
            'revenue_by_cafe'     => $this->revenueByCafe($f),
            'by_service_type'     => $this->byServiceType($f),
            'by_subdealership'    => $this->bySubdealership($f),
            'top_diners'          => $this->topDiners($f),
            'visitor_ratio'       => $this->visitorRatio($f),
            'by_weekday'          => $this->byWeekday($f),
            'service_heatmap'     => $this->serviceHeatmap($f),
            'service_mix_by_cafe' => $this->serviceMixByCafe($f),
            'visit_frequency'     => $this->visitFrequency($f),
            'period_comparison'   => $this->periodComparison($f),

            /* ── Pestaña Mercantiles ── */
            'mercantil'           => $this->mercantilBlock($f),

            /* ── Pestaña Consolidado ── */
            'consolidated'        => $this->consolidatedBlock($f),
        ]);
    }

    /* ====================================================================
     | Filtros
     ==================================================================== */

    /**
     * Normaliza el request en un contexto de filtros reutilizable por todas las consultas.
     * Devuelve los ids ya resueltos (comedores/unidades/mercantiles) para no re-resolverlos
     * en cada bloque, más la serie diaria de comedores que varios cálculos reaprovechan.
     */
    private function resolveFilters(Request $request): array
    {
        $startDate = $request->input('start_date') ?: Carbon::now()->startOfMonth()->toDateString();
        $endDate   = $request->input('end_date')   ?: Carbon::now()->toDateString();

        // Un rango invertido devuelve cero filas sin avisar; se ordena en vez de fallar.
        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $mineId          = $this->nullableId($request->input('mine_id'));
        $unitId          = $this->nullableId($request->input('unit_id'));
        $cafeId          = $this->nullableId($request->input('cafe_id'));
        $subdealershipId = $this->nullableId($request->input('subdealership_id'));
        $mercantilId     = $this->nullableId($request->input('mercantil_id'));
        $serviceType     = $this->nullableId($request->input('service_type'));

        $dinerType = in_array($request->input('diner_type'), ['diners', 'visitors'], true)
            ? $request->input('diner_type')
            : 'all';

        $paymentCondition = in_array($request->input('payment_condition'), ['contado', 'credito'], true)
            ? $request->input('payment_condition')
            : null;

        $paymentMethod = $request->input('payment_method') ?: null;
        $paymentMethod = $paymentMethod === 'all' ? null : $paymentMethod;

        // Si el usuario está asignado a una mina, todo el reporte queda acotado a ella:
        // el filtro de mina de la petición no puede ampliar el alcance por encima de la
        // suya (antes, sin `mine_id` en la query, se agregaban todas las minas).
        $userMineId = optional($request->user())->mine_id;
        if ($userMineId) {
            $mineId = $userMineId;
        }

        /* ── Alcance geográfico: comedor > unidad > mina > todo ── */
        $cafeIds = Cafe::query()
            ->when($cafeId, fn ($q) => $q->where('id', $cafeId))
            ->when(! $cafeId && $unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->when(! $cafeId && ! $unitId && $mineId, fn ($q) => $q->whereHas('unit', fn ($u) => $u->where('mine_id', $mineId)))
            ->when($userMineId, fn ($q) => $q->whereHas('unit', fn ($u) => $u->where('mine_id', $userMineId)))
            ->pluck('id')->all();

        // Los mercantiles cuelgan de la unidad, no del comedor: el filtro de comedor no aplica.
        $unitIds = Unit::query()
            ->when($unitId, fn ($q) => $q->where('id', $unitId))
            ->when(! $unitId && $mineId, fn ($q) => $q->where('mine_id', $mineId))
            ->when($userMineId, fn ($q) => $q->where('mine_id', $userMineId))
            ->pluck('id')->all();

        $mercantilIds = Mercantil::query()
            ->when($mercantilId, fn ($q) => $q->where('id', $mercantilId))
            ->when(! $mercantilId, fn ($q) => $q->whereIn('unit_id', $unitIds))
            ->pluck('id')->all();

        $f = [
            'start'              => $startDate,
            'end'                => $endDate,
            'mine_id'            => $mineId,
            'unit_id'            => $unitId,
            'cafe_id'            => $cafeId,
            'service_type'       => $serviceType,
            'subdealership_id'   => $subdealershipId,
            'subdealership_name' => $subdealershipId ? Subdealership::find($subdealershipId)?->name : null,
            'diner_type'         => $dinerType,
            'mercantil_id'       => $mercantilId,
            'payment_condition'  => $paymentCondition,
            'payment_method'     => $paymentMethod,
            'cafe_ids'           => $cafeIds,
            'unit_ids'           => $unitIds,
            'mercantil_ids'      => $mercantilIds,
        ];

        $f['public'] = [
            'start_date'        => $startDate,
            'end_date'          => $endDate,
            'mine_id'           => $mineId ? (string) $mineId : null,
            'unit_id'           => $unitId ? (string) $unitId : null,
            'cafe_id'           => $cafeId ? (string) $cafeId : null,
            'service_type'      => $serviceType ? (string) $serviceType : null,
            'subdealership_id'  => $subdealershipId ? (string) $subdealershipId : null,
            'diner_type'        => $dinerType,
            'mercantil_id'      => $mercantilId ? (string) $mercantilId : null,
            'payment_condition' => $paymentCondition,
            'payment_method'    => $paymentMethod,
        ];

        $f['daily_trend'] = $this->dailyTrend($f);

        return $f;
    }

    private function nullableId($value): ?int
    {
        if ($value === null || $value === '' || $value === 'all') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /* ====================================================================
     | Constructores de consulta compartidos
     ==================================================================== */

    /** Ventas de comedor con todos los filtros del contexto aplicados. */
    private function saleQuery(array $f): Builder
    {
        $q = Sale::whereIn('cafe_id', $f['cafe_ids'])
            ->whereBetween('date', [$f['start'], $f['end']]);

        if ($f['diner_type'] === 'diners') {
            $q->where('is_visitor', false);
        } elseif ($f['diner_type'] === 'visitors') {
            $q->where('is_visitor', true);
        }

        if ($f['service_type']) {
            $q->whereHas('tickets.ticket_details', fn ($t) => $t->where('service_type', $f['service_type']));
        }

        if ($f['subdealership_name']) {
            $q->whereHas('tickets', fn ($t) => $t->where('subdealership_name', $f['subdealership_name']));
        }

        return $q;
    }

    /**
     * Detalle de ticket (una fila por consumo) unido a su venta. Es el grano correcto para
     * todo lo que se cuente por servicio: una venta puede traer desayuno + almuerzo a la vez.
     */
    private function detailQuery(array $f): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('ticket_details')
            ->join('tickets', 'ticket_details.ticket_id', '=', 'tickets.id')
            ->join('sales', 'tickets.sale_id', '=', 'sales.id')
            ->whereIn('sales.cafe_id', $f['cafe_ids'])
            ->whereBetween('sales.date', [$f['start'], $f['end']]);

        if ($f['diner_type'] === 'diners') {
            $q->where('sales.is_visitor', false);
        } elseif ($f['diner_type'] === 'visitors') {
            $q->where('sales.is_visitor', true);
        }

        if ($f['service_type']) {
            $q->where('ticket_details.service_type', $f['service_type']);
        }

        if ($f['subdealership_name']) {
            $q->where('tickets.subdealership_name', $f['subdealership_name']);
        }

        return $q;
    }

    /**
     * `mercantil_sales.date` es una columna DATE, pero el modelo la castea a `date`: MySQL la
     * guarda sin hora y los motores que almacenan fechas como texto le anteponen "00:00:00".
     * Se ensancha el limite superior para que el ultimo dia del rango entre en ambos casos.
     */
    private function mercDateRange(array $f): array
    {
        return [$f['start'], $f['end'] . ' 23:59:59'];
    }

    /** Ventas de mercantil con los filtros que le aplican (unidad/mina, mercantil, pago). */
    private function mercSaleQuery(array $f): Builder
    {
        $q = MercantilSale::whereIn('mercantil_id', $f['mercantil_ids'])
            ->whereBetween('mercantil_sales.date', $this->mercDateRange($f));

        if ($f['payment_condition']) {
            $q->where('payment_condition', $f['payment_condition']);
        }

        if ($f['payment_method']) {
            $q->where('payment_method', $f['payment_method']);
        }

        if ($f['subdealership_id']) {
            $q->where('subdealership_id', $f['subdealership_id']);
        }

        return $q;
    }

    /* ====================================================================
     | Pestaña Comedores
     ==================================================================== */

    private function dailyTrend(array $f): array
    {
        return $this->saleQuery($f)
            ->selectRaw('date, count(*) as cnt, sum(total) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date'      => Carbon::parse($r->date)->format('d/m'),
                'date_full' => Carbon::parse($r->date)->toDateString(),
                'count'     => (int) $r->cnt,
                'revenue'   => round((float) $r->revenue, 2),
            ])->all();
    }

    private function cafeKpis(array $f): array
    {
        $totalRevenue = round(array_sum(array_column($f['daily_trend'], 'revenue')), 2);
        $totalSales   = array_sum(array_column($f['daily_trend'], 'count'));

        $totalDiners = $this->saleQuery($f)->where('is_visitor', false)
            ->distinct('dinner_id')->count('dinner_id');

        $totalVisitors = $this->saleQuery($f)->where('is_visitor', true)->count();

        // Raciones servidas ≠ ventas: una venta puede incluir varios servicios.
        $totalServings = (int) $this->detailQuery($f)->count();

        /* ── Período anterior de la misma duración, desplazado hacia atrás ── */
        $prev  = $this->previousRange($f);
        $prevF = array_merge($f, ['start' => $prev['start'], 'end' => $prev['end']]);

        $prevRevenue = (float) $this->saleQuery($prevF)->sum('total');
        $prevSales   = $this->saleQuery($prevF)->count();

        return [
            'total_revenue'     => $totalRevenue,
            'total_sales'       => $totalSales,
            'total_diners'      => $totalDiners,
            'total_visitors'    => $totalVisitors,
            'total_servings'    => $totalServings,
            'revenue_growth'    => $prevRevenue > 0 ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : null,
            'sales_growth'      => $prevSales   > 0 ? round((($totalSales - $prevSales) / $prevSales) * 100, 1) : null,
            'avg_ticket'        => $totalSales > 0 ? round($totalRevenue / $totalSales, 2) : 0,
            'servings_per_sale' => $totalSales > 0 ? round($totalServings / $totalSales, 2) : 0,
        ];
    }

    private function previousRange(array $f): array
    {
        $days      = (int) Carbon::parse($f['start'])->diffInDays(Carbon::parse($f['end'])) + 1;
        $prevEnd   = Carbon::parse($f['start'])->subDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1);

        return ['start' => $prevStart->toDateString(), 'end' => $prevEnd->toDateString(), 'days' => $days];
    }

    /**
     * Desglose organizacional que baja de nivel según el filtro activo: sin mina se comparan
     * minas, con mina se comparan sus unidades, con unidad se comparan sus comedores. Así el
     * gráfico sigue diciendo algo cuando el usuario filtra, en vez de quedar en una sola barra.
     */
    private function orgBreakdown(array $f): array
    {
        $base = DB::table('sales')
            ->join('cafes', 'sales.cafe_id', '=', 'cafes.id')
            ->join('units', 'cafes.unit_id', '=', 'units.id')
            ->join('mines', 'units.mine_id', '=', 'mines.id')
            ->whereIn('sales.cafe_id', $f['cafe_ids'])
            ->whereBetween('sales.date', [$f['start'], $f['end']]);

        if ($f['unit_id'] || $f['cafe_id']) {
            $level = 'Comedor';
            $base->selectRaw('cafes.name as label, sum(sales.total) as revenue, count(*) as sales')
                ->groupBy('cafes.id', 'cafes.name');
        } elseif ($f['mine_id']) {
            $level = 'Unidad';
            $base->selectRaw('units.name as label, sum(sales.total) as revenue, count(*) as sales')
                ->groupBy('units.id', 'units.name');
        } else {
            $level = 'Mina';
            $base->selectRaw('mines.name as label, sum(sales.total) as revenue, count(*) as sales')
                ->groupBy('mines.id', 'mines.name');
        }

        $rows = $this->applyDinerAndServiceScope($base, $f)
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'label'      => $r->label,
                'revenue'    => round((float) $r->revenue, 2),
                'sales'      => (int) $r->sales,
                'avg_ticket' => $r->sales > 0 ? round((float) $r->revenue / (int) $r->sales, 2) : 0,
            ])->values()->all();

        return ['level' => $level, 'rows' => $rows];
    }

    /** Aplica los filtros de comensal/servicio/subconcesionaria a una query cruda sobre `sales`. */
    private function applyDinerAndServiceScope(\Illuminate\Database\Query\Builder $q, array $f): \Illuminate\Database\Query\Builder
    {
        if ($f['diner_type'] === 'diners') {
            $q->where('sales.is_visitor', false);
        } elseif ($f['diner_type'] === 'visitors') {
            $q->where('sales.is_visitor', true);
        }

        if ($f['service_type'] || $f['subdealership_name']) {
            $q->whereExists(function ($sub) use ($f) {
                $sub->select(DB::raw(1))
                    ->from('tickets')
                    ->join('ticket_details', 'ticket_details.ticket_id', '=', 'tickets.id')
                    ->whereColumn('tickets.sale_id', 'sales.id');

                if ($f['service_type']) {
                    $sub->where('ticket_details.service_type', $f['service_type']);
                }
                if ($f['subdealership_name']) {
                    $sub->where('tickets.subdealership_name', $f['subdealership_name']);
                }
            });
        }

        return $q;
    }

    private function revenueByCafe(array $f): array
    {
        return $this->saleQuery($f)
            ->selectRaw('cafe_name, sum(total) as revenue, count(*) as sales')
            ->groupBy('cafe_name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'cafe'       => $r->cafe_name ?: 'Sin nombre',
                'revenue'    => round((float) $r->revenue, 2),
                'sales'      => (int) $r->sales,
                'avg_ticket' => $r->sales > 0 ? round((float) $r->revenue / (int) $r->sales, 2) : 0,
            ])->all();
    }

    private function byServiceType(array $f): array
    {
        return $this->detailQuery($f)
            ->selectRaw('ticket_details.service_type as service_type, count(*) as qty, sum(ticket_details.unit_price) as revenue')
            ->groupBy('ticket_details.service_type')
            ->orderBy('ticket_details.service_type')
            ->get()
            ->map(fn ($r) => [
                'label'   => self::SERVICE_LABELS[(int) $r->service_type] ?? 'Otro',
                'qty'     => (int) $r->qty,
                'revenue' => round((float) $r->revenue, 2),
            ])->all();
    }

    private function bySubdealership(array $f): array
    {
        $q = DB::table('tickets')
            ->join('sales', 'tickets.sale_id', '=', 'sales.id')
            ->whereIn('sales.cafe_id', $f['cafe_ids'])
            ->whereBetween('sales.date', [$f['start'], $f['end']])
            ->whereNotNull('tickets.subdealership_name')
            ->where('tickets.subdealership_name', '<>', '');

        if ($f['subdealership_name']) {
            $q->where('tickets.subdealership_name', $f['subdealership_name']);
        }

        return $this->applyDinerAndServiceScope($q, $f)
            ->selectRaw('tickets.subdealership_name as subdealership_name, count(distinct sales.id) as sales, sum(sales.total) as revenue')
            ->groupBy('tickets.subdealership_name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'name'    => $r->subdealership_name,
                'sales'   => (int) $r->sales,
                'revenue' => round((float) $r->revenue, 2),
            ])->values()->all();
    }

    private function topDiners(array $f): array
    {
        $q = DB::table('sales')
            ->join('dinners', 'sales.dinner_id', '=', 'dinners.id')
            ->whereIn('sales.cafe_id', $f['cafe_ids'])
            ->whereBetween('sales.date', [$f['start'], $f['end']]);

        return $this->applyDinerAndServiceScope($q, $f)
            ->selectRaw('dinners.name as name, dinners.dni as dni, count(*) as visits, sum(sales.total) as spent')
            ->groupBy('dinners.id', 'dinners.name', 'dinners.dni')
            ->orderByDesc('visits')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'name'   => $r->name,
                'dni'    => $r->dni,
                'visits' => (int) $r->visits,
                'spent'  => round((float) $r->spent, 2),
            ])->all();
    }

    private function visitorRatio(array $f): array
    {
        $rows = $this->saleQuery($f)
            ->selectRaw('is_visitor, count(*) as cnt')
            ->groupBy('is_visitor')
            ->pluck('cnt', 'is_visitor');

        return [
            ['label' => 'Comensales', 'count' => (int) ($rows[0] ?? 0)],
            ['label' => 'Visitantes', 'count' => (int) ($rows[1] ?? 0)],
        ];
    }

    /** Se agrega en PHP sobre la serie diaria: evita DAYOFWEEK (MySQL) y ahorra una consulta. */
    private function byWeekday(array $f): array
    {
        $acc = [];
        foreach ($f['daily_trend'] as $row) {
            $dow = Carbon::parse($row['date_full'])->dayOfWeekIso;
            $acc[$dow]['sales']   = ($acc[$dow]['sales']   ?? 0) + $row['count'];
            $acc[$dow]['revenue'] = ($acc[$dow]['revenue'] ?? 0) + $row['revenue'];
            $acc[$dow]['days']    = ($acc[$dow]['days']    ?? 0) + 1;
        }

        return collect(self::WEEKDAY_LABELS)->map(fn ($label, $dow) => [
            'day'     => $label,
            'sales'   => (int) ($acc[$dow]['sales'] ?? 0),
            'revenue' => round((float) ($acc[$dow]['revenue'] ?? 0), 2),
            // Promedio por día ocurrido: 4 lunes y 5 martes en el rango no son comparables en bruto.
            'avg_sales' => ($acc[$dow]['days'] ?? 0) > 0 ? round($acc[$dow]['sales'] / $acc[$dow]['days'], 1) : 0,
        ])->values()->all();
    }

    /**
     * Mapa de calor día de la semana × servicio con el promedio de raciones por día ocurrido.
     * Sirve para dimensionar turnos y compras: dice qué combinación concentra la demanda.
     */
    private function serviceHeatmap(array $f): array
    {
        $rows = $this->detailQuery($f)
            ->selectRaw('sales.date as d, ticket_details.service_type as st, count(*) as qty')
            ->groupBy('sales.date', 'ticket_details.service_type')
            ->get();

        $totals   = []; // [service_type][dow] => raciones
        $daysSeen = []; // [dow] => fechas distintas, para promediar
        foreach ($rows as $r) {
            $date = Carbon::parse($r->d);
            $dow  = $date->dayOfWeekIso;
            $totals[(int) $r->st][$dow] = ($totals[(int) $r->st][$dow] ?? 0) + (int) $r->qty;
            $daysSeen[$dow][$date->toDateString()] = true;
        }

        $series = [];
        foreach (self::SERVICE_LABELS as $st => $label) {
            $data = [];
            foreach (self::WEEKDAY_LABELS as $dow => $dayLabel) {
                $qty  = $totals[$st][$dow] ?? 0;
                $days = count($daysSeen[$dow] ?? []);
                $data[] = ['x' => mb_substr($dayLabel, 0, 3), 'y' => $days > 0 ? (int) round($qty / $days) : 0];
            }
            $series[] = ['name' => $label, 'data' => $data];
        }

        return $series;
    }

    /**
     * Mix de servicios por comedor (top 12 por volumen). Un comedor con casi cero desayunos
     * frente a sus pares es una brecha de cobertura, no una preferencia del personal.
     */
    private function serviceMixByCafe(array $f): array
    {
        $rows = $this->detailQuery($f)
            ->selectRaw('sales.cafe_name as cafe, ticket_details.service_type as st, count(*) as qty')
            ->groupBy('sales.cafe_name', 'ticket_details.service_type')
            ->get();

        $byCafe = [];
        foreach ($rows as $r) {
            $cafe = $r->cafe ?: 'Sin nombre';
            $byCafe[$cafe][(int) $r->st] = (int) $r->qty;
            $byCafe[$cafe]['total']      = ($byCafe[$cafe]['total'] ?? 0) + (int) $r->qty;
        }

        uasort($byCafe, fn ($a, $b) => $b['total'] <=> $a['total']);
        $byCafe = array_slice($byCafe, 0, 12, true);

        $categories = array_keys($byCafe);
        $series     = [];
        foreach (self::SERVICE_LABELS as $st => $label) {
            $series[] = [
                'name' => $label,
                'data' => array_map(fn ($cafe) => $byCafe[$cafe][$st] ?? 0, $categories),
            ];
        }

        return ['categories' => $categories, 'series' => $series];
    }

    /**
     * Distribución de comensales por número de visitas en el período: separa el padrón que
     * consume a diario del que aparece una vez. Base para proyectar demanda y detectar fuga.
     */
    private function visitFrequency(array $f): array
    {
        $visits = $this->saleQuery($f)
            ->whereNotNull('dinner_id')
            ->where('is_visitor', false)
            ->selectRaw('dinner_id, count(*) as visits')
            ->groupBy('dinner_id')
            ->pluck('visits')
            ->map(fn ($v) => (int) $v);

        $buckets = [
            '1 visita'  => fn (int $v) => $v === 1,
            '2-5'       => fn (int $v) => $v >= 2 && $v <= 5,
            '6-10'      => fn (int $v) => $v >= 6 && $v <= 10,
            '11-20'     => fn (int $v) => $v >= 11 && $v <= 20,
            'Más de 20' => fn (int $v) => $v > 20,
        ];

        $out = [];
        foreach ($buckets as $label => $test) {
            $out[] = ['label' => $label, 'count' => $visits->filter($test)->count()];
        }

        return $out;
    }

    /**
     * Ingreso acumulado del período contra el período anterior alineado por día n.
     * Responde "¿vamos por encima o por debajo del período pasado a estas alturas?".
     */
    private function periodComparison(array $f): array
    {
        $prev  = $this->previousRange($f);
        $prevF = array_merge($f, ['start' => $prev['start'], 'end' => $prev['end']]);

        $current  = $this->cumulative($f['daily_trend'], $f['start'], $f['end']);
        $previous = $this->cumulative($this->dailyTrend($prevF), $prev['start'], $prev['end']);

        return [
            'labels'     => array_map(fn ($i) => 'Día ' . $i, range(1, max(count($current), count($previous), 1))),
            'current'    => $current,
            'previous'   => $previous,
            'prev_range' => ['start' => $prev['start'], 'end' => $prev['end']],
        ];
    }

    /** Serie acumulada día a día, rellenando con 0 las fechas sin ventas. */
    private function cumulative(array $trend, string $start, string $end): array
    {
        $byDate = collect($trend)->keyBy('date_full');
        $out    = [];
        $sum    = 0.0;

        for ($d = Carbon::parse($start); $d->lte(Carbon::parse($end)); $d->addDay()) {
            $sum  += (float) ($byDate[$d->toDateString()]['revenue'] ?? 0);
            $out[] = round($sum, 2);
        }

        return $out;
    }

    /* ====================================================================
     | Pestaña Mercantiles
     ==================================================================== */

    private function mercantilPaymentMethods(): array
    {
        return MercantilSale::query()
            ->whereNotNull('payment_method')
            ->distinct()
            ->orderBy('payment_method')
            ->pluck('payment_method')
            ->all();
    }

    /** Query base sobre el detalle de venta de mercantil, con los filtros del contexto. */
    private function mercDetailQuery(array $f): \Illuminate\Database\Query\Builder
    {
        return DB::table('mercantil_sale_details')
            ->join('mercantil_sales', 'mercantil_sale_details.mercantil_sale_id', '=', 'mercantil_sales.id')
            ->whereIn('mercantil_sales.mercantil_id', $f['mercantil_ids'])
            ->whereBetween('mercantil_sales.date', $this->mercDateRange($f))
            ->when($f['payment_condition'], fn ($q) => $q->where('mercantil_sales.payment_condition', $f['payment_condition']))
            ->when($f['payment_method'], fn ($q) => $q->where('mercantil_sales.payment_method', $f['payment_method']))
            ->when($f['subdealership_id'], fn ($q) => $q->where('mercantil_sales.subdealership_id', $f['subdealership_id']));
    }

    private function mercantilBlock(array $f): array
    {
        $totalRevenue = round((float) $this->mercSaleQuery($f)->sum('total'), 2);
        $totalSales   = $this->mercSaleQuery($f)->count();
        $totalUnits   = (int) $this->mercDetailQuery($f)->sum('mercantil_sale_details.quantity');

        // Crédito = mercadería ya entregada y aún no cobrada: la cuenta por cobrar del período.
        $creditOutstanding = round((float) MercantilSale::whereIn('mercantil_id', $f['mercantil_ids'])
            ->whereBetween('date', $this->mercDateRange($f))
            ->where('payment_condition', 'credito')
            ->when($f['subdealership_id'], fn ($q) => $q->where('subdealership_id', $f['subdealership_id']))
            ->sum('total'), 2);

        // Valorización: foto del stock actual, no depende del rango de fechas.
        $inventoryValue = round((float) Product::whereIn('mercantil_id', $f['mercantil_ids'])
            ->where('is_active', true)
            ->sum(DB::raw('price * stock')), 2);

        $prev        = $this->previousRange($f);
        $prevF       = array_merge($f, ['start' => $prev['start'], 'end' => $prev['end']]);
        $prevRevenue = (float) $this->mercSaleQuery($prevF)->sum('total');

        /* ── Tendencia diaria ── */
        $dailyTrend = $this->mercSaleQuery($f)
            ->selectRaw('date, count(*) as cnt, sum(total) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date'      => Carbon::parse($r->date)->format('d/m'),
                'date_full' => Carbon::parse($r->date)->toDateString(),
                'count'     => (int) $r->cnt,
                'revenue'   => round((float) $r->revenue, 2),
            ])->all();

        /* ── Ingresos por mercantil ── */
        $byMercantil = $this->mercSaleQuery($f)
            ->join('mercantiles', 'mercantil_sales.mercantil_id', '=', 'mercantiles.id')
            ->selectRaw('mercantiles.name as name, sum(mercantil_sales.total) as revenue, count(*) as sales')
            ->groupBy('mercantiles.id', 'mercantiles.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'name'    => $r->name,
                'revenue' => round((float) $r->revenue, 2),
                'sales'   => (int) $r->sales,
            ])->all();

        /* ── Top productos por ingreso ── */
        $topProducts = $this->mercDetailQuery($f)
            ->selectRaw('mercantil_sale_details.product_name as name, sum(mercantil_sale_details.subtotal) as revenue, sum(mercantil_sale_details.quantity) as units')
            ->groupBy('mercantil_sale_details.product_name')
            ->orderByDesc('revenue')
            ->limit(12)
            ->get()
            ->map(fn ($r) => [
                'name'    => $r->name,
                'revenue' => round((float) $r->revenue, 2),
                'units'   => (int) $r->units,
            ])->all();

        /* ── Ingresos por categoría de producto ── */
        $byCategory = $this->mercDetailQuery($f)
            ->selectRaw('mercantil_sale_details.category as category, sum(mercantil_sale_details.subtotal) as revenue, sum(mercantil_sale_details.quantity) as units')
            ->groupBy('mercantil_sale_details.category')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'category' => $r->category ?: 'Sin categoría',
                'revenue'  => round((float) $r->revenue, 2),
                'units'    => (int) $r->units,
            ])->all();

        /* ── Contado vs crédito ── */
        $byCondition = $this->mercSaleQuery($f)
            ->selectRaw('payment_condition, count(*) as cnt, sum(total) as revenue')
            ->groupBy('payment_condition')
            ->get()
            ->map(fn ($r) => [
                'label'   => ucfirst($r->payment_condition ?: 'contado'),
                'count'   => (int) $r->cnt,
                'revenue' => round((float) $r->revenue, 2),
            ])->all();

        /* ── Método de pago ── */
        $byPaymentMethod = $this->mercSaleQuery($f)
            ->selectRaw('payment_method, count(*) as cnt, sum(total) as revenue')
            ->groupBy('payment_method')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'label'   => ucfirst($r->payment_method ?: 'Sin registrar'),
                'count'   => (int) $r->cnt,
                'revenue' => round((float) $r->revenue, 2),
            ])->all();

        /* ── Cuentas por cobrar por subconcesionaria ── */
        $creditBySubdealership = MercantilSale::whereIn('mercantil_id', $f['mercantil_ids'])
            ->whereBetween('mercantil_sales.date', $this->mercDateRange($f))
            ->where('mercantil_sales.payment_condition', 'credito')
            ->when($f['subdealership_id'], fn ($q) => $q->where('mercantil_sales.subdealership_id', $f['subdealership_id']))
            ->leftJoin('subdealerships', 'mercantil_sales.subdealership_id', '=', 'subdealerships.id')
            ->selectRaw('subdealerships.name as name, count(*) as sales, sum(mercantil_sales.total) as revenue')
            ->groupBy('subdealerships.id', 'subdealerships.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'name'    => $r->name ?: 'Sin subconcesionaria',
                'sales'   => (int) $r->sales,
                'revenue' => round((float) $r->revenue, 2),
            ])->all();

        /* ── Ventas por hora (se agrupa en PHP: HOUR() no es portable) ── */
        $hours = array_fill(0, 24, 0);
        foreach ($this->mercSaleQuery($f)->pluck('created_at') as $ts) {
            if ($ts) {
                $hours[(int) Carbon::parse($ts)->format('G')]++;
            }
        }
        $byHour = [];
        foreach ($hours as $h => $cnt) {
            $byHour[] = ['hour' => sprintf('%02d:00', $h), 'sales' => $cnt];
        }

        /* ── Reposición: stock por debajo del umbral ── */
        $lowStock = Product::whereIn('mercantil_id', $f['mercantil_ids'])
            ->where('is_active', true)
            ->where('stock', '<=', self::LOW_STOCK_THRESHOLD)
            ->with('mercantil:id,name')
            ->orderBy('stock')
            ->limit(15)
            ->get(['id', 'mercantil_id', 'name', 'category', 'stock', 'price'])
            ->map(fn ($p) => [
                'name'      => $p->name,
                'mercantil' => $p->mercantil?->name ?? '—',
                'category'  => $p->category ?: 'Sin categoría',
                'stock'     => (int) $p->stock,
                'price'     => round((float) $p->price, 2),
            ])->all();

        /* ── Capital inmovilizado: con stock y sin una sola venta en el período ── */
        $slowMovers = Product::whereIn('mercantil_id', $f['mercantil_ids'])
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereDoesntHave('saleDetails', fn ($q) => $q->whereHas('sale', fn ($s) => $s->whereBetween('date', $this->mercDateRange($f))))
            ->with('mercantil:id,name')
            ->orderByRaw('price * stock desc')
            ->limit(15)
            ->get(['id', 'mercantil_id', 'name', 'category', 'stock', 'price'])
            ->map(fn ($p) => [
                'name'      => $p->name,
                'mercantil' => $p->mercantil?->name ?? '—',
                'category'  => $p->category ?: 'Sin categoría',
                'stock'     => (int) $p->stock,
                'tied_up'   => round((float) $p->price * (int) $p->stock, 2),
            ])->all();

        /* ── Quién más compra (identificado por DNI) ── */
        $topBuyers = MercantilSale::whereIn('mercantil_id', $f['mercantil_ids'])
            ->whereBetween('mercantil_sales.date', $this->mercDateRange($f))
            ->whereNotNull('mercantil_sales.buyer_dni')
            ->when($f['payment_condition'], fn ($q) => $q->where('mercantil_sales.payment_condition', $f['payment_condition']))
            ->when($f['subdealership_id'], fn ($q) => $q->where('mercantil_sales.subdealership_id', $f['subdealership_id']))
            ->leftJoin('dinners', 'mercantil_sales.dinner_id', '=', 'dinners.id')
            ->selectRaw('mercantil_sales.buyer_dni as dni, max(dinners.name) as name, count(*) as purchases, sum(mercantil_sales.total) as spent')
            ->groupBy('mercantil_sales.buyer_dni')
            ->orderByDesc('spent')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'dni'       => $r->dni,
                'name'      => $r->name ?: 'No registrado',
                'purchases' => (int) $r->purchases,
                'spent'     => round((float) $r->spent, 2),
            ])->all();

        return [
            'kpis' => [
                'total_revenue'      => $totalRevenue,
                'total_sales'        => $totalSales,
                'avg_ticket'         => $totalSales > 0 ? round($totalRevenue / $totalSales, 2) : 0,
                'total_units'        => $totalUnits,
                'credit_outstanding' => $creditOutstanding,
                'credit_share'       => $totalRevenue > 0 ? round(($creditOutstanding / $totalRevenue) * 100, 1) : 0,
                'inventory_value'    => $inventoryValue,
                'revenue_growth'     => $prevRevenue > 0 ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : null,
            ],
            'daily_trend'             => $dailyTrend,
            'by_mercantil'            => $byMercantil,
            'top_products'            => $topProducts,
            'by_category'             => $byCategory,
            'by_condition'            => $byCondition,
            'by_payment_method'       => $byPaymentMethod,
            'credit_by_subdealership' => $creditBySubdealership,
            'by_hour'                 => $byHour,
            'low_stock'               => $lowStock,
            'slow_movers'             => $slowMovers,
            'top_buyers'              => $topBuyers,
        ];
    }

    /* ====================================================================
     | Pestaña Consolidado
     ==================================================================== */

    /**
     * Une los dos canales de ingreso (comedores y mercantiles) en la misma escala: cuánto pesa
     * cada uno, cómo se mueven día a día y qué unidad aporta qué.
     */
    private function consolidatedBlock(array $f): array
    {
        $cafeRevenue = round(array_sum(array_column($f['daily_trend'], 'revenue')), 2);
        $mercRevenue = round((float) $this->mercSaleQuery($f)->sum('total'), 2);
        $total       = round($cafeRevenue + $mercRevenue, 2);

        /* ── Series diarias alineadas sobre el mismo eje de fechas ── */
        $cafeByDate = collect($f['daily_trend'])->pluck('revenue', 'date_full');
        $mercByDate = $this->mercSaleQuery($f)
            ->selectRaw('date, sum(total) as revenue')
            ->groupBy('date')
            ->pluck('revenue', 'date')
            ->mapWithKeys(fn ($v, $k) => [Carbon::parse($k)->toDateString() => round((float) $v, 2)]);

        $labels     = [];
        $cafeSeries = [];
        $mercSeries = [];
        for ($d = Carbon::parse($f['start']); $d->lte(Carbon::parse($f['end'])); $d->addDay()) {
            $key          = $d->toDateString();
            $labels[]     = $d->format('d/m');
            $cafeSeries[] = round((float) ($cafeByDate[$key] ?? 0), 2);
            $mercSeries[] = round((float) ($mercByDate[$key] ?? 0), 2);
        }

        /* ── Aporte por unidad, sumando ambos canales ── */
        $cafeUnitQuery = DB::table('sales')
            ->join('cafes', 'sales.cafe_id', '=', 'cafes.id')
            ->join('units', 'cafes.unit_id', '=', 'units.id')
            ->whereIn('sales.cafe_id', $f['cafe_ids'])
            ->whereBetween('sales.date', [$f['start'], $f['end']]);

        $cafeByUnit = $this->applyDinerAndServiceScope($cafeUnitQuery, $f)
            ->selectRaw('units.name as unit, sum(sales.total) as revenue')
            ->groupBy('units.id', 'units.name')
            ->pluck('revenue', 'unit');

        $mercByUnit = $this->mercSaleQuery($f)
            ->join('units', 'mercantil_sales.unit_id', '=', 'units.id')
            ->selectRaw('units.name as unit, sum(mercantil_sales.total) as revenue')
            ->groupBy('units.id', 'units.name')
            ->pluck('revenue', 'unit');

        $unitRows = $cafeByUnit->keys()->merge($mercByUnit->keys())->unique()->values()
            ->map(fn ($u) => [
                'unit'        => $u,
                'comedores'   => round((float) ($cafeByUnit[$u] ?? 0), 2),
                'mercantiles' => round((float) ($mercByUnit[$u] ?? 0), 2),
                'total'       => round((float) ($cafeByUnit[$u] ?? 0) + (float) ($mercByUnit[$u] ?? 0), 2),
            ])
            ->sortByDesc('total')
            ->take(12)
            ->values()
            ->all();

        return [
            'cafe_revenue'  => $cafeRevenue,
            'merc_revenue'  => $mercRevenue,
            'total_revenue' => $total,
            'cafe_share'    => $total > 0 ? round(($cafeRevenue / $total) * 100, 1) : 0,
            'merc_share'    => $total > 0 ? round(($mercRevenue / $total) * 100, 1) : 0,
            'daily'         => ['labels' => $labels, 'comedores' => $cafeSeries, 'mercantiles' => $mercSeries],
            'by_unit'       => $unitRows,
        ];
    }
}
