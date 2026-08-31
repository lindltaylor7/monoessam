<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    AlarmClock,
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    BarChart3,
    Boxes,
    Building2,
    CalendarDays,
    Coffee,
    CreditCard,
    DollarSign,
    Filter,
    Layers,
    Moon,
    Package,
    PieChart,
    Repeat,
    RotateCcw,
    ShoppingBag,
    ShoppingCart,
    Store,
    Sunrise,
    Target,
    TrendingUp,
    UserCheck,
    Users,
    Utensils,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

/* ══════════════════════════════════════════════════════════════════
 | Tipos
 ══════════════════════════════════════════════════════════════════ */
interface Cafe {
    id: number;
    unit_id: number;
    name: string;
}
interface Unit {
    id: number;
    mine_id: number;
    name: string;
    cafes: Cafe[];
}
interface Mine {
    id: number;
    name: string;
    units: Unit[];
}
interface Subdealership {
    id: number;
    name: string;
}
interface MercantilOption {
    id: number;
    unit_id: number;
    name: string;
    unit?: { id: number; mine_id: number; name: string } | null;
}
interface ServiceType {
    id: number;
    label: string;
}

interface Filters {
    start_date: string;
    end_date: string;
    mine_id: string | null;
    unit_id: string | null;
    cafe_id: string | null;
    service_type: string | null;
    subdealership_id: string | null;
    diner_type: string;
    mercantil_id: string | null;
    payment_condition: string | null;
    payment_method: string | null;
}

interface Kpis {
    total_revenue: number;
    total_sales: number;
    total_diners: number;
    total_visitors: number;
    total_servings: number;
    revenue_growth: number | null;
    sales_growth: number | null;
    avg_ticket: number;
    servings_per_sale: number;
}

interface CafeSales {
    cafe: string;
    revenue: number;
    sales: number;
    avg_ticket: number;
}
interface WeekdaySales {
    day: string;
    sales: number;
    revenue: number;
    avg_sales: number;
}
interface OrgRow {
    label: string;
    revenue: number;
    sales: number;
    avg_ticket: number;
}

interface MercantilBlock {
    kpis: {
        total_revenue: number;
        total_sales: number;
        avg_ticket: number;
        total_units: number;
        credit_outstanding: number;
        credit_share: number;
        inventory_value: number;
        revenue_growth: number | null;
    };
    daily_trend: { date: string; date_full: string; count: number; revenue: number }[];
    by_mercantil: { name: string; revenue: number; sales: number }[];
    top_products: { name: string; revenue: number; units: number }[];
    by_category: { category: string; revenue: number; units: number }[];
    by_condition: { label: string; count: number; revenue: number }[];
    by_payment_method: { label: string; count: number; revenue: number }[];
    credit_by_subdealership: { name: string; sales: number; revenue: number }[];
    by_hour: { hour: string; sales: number }[];
    low_stock: { name: string; mercantil: string; category: string; stock: number; price: number }[];
    slow_movers: { name: string; mercantil: string; category: string; stock: number; tied_up: number }[];
    top_buyers: { dni: string; name: string; purchases: number; spent: number }[];
}

interface Consolidated {
    cafe_revenue: number;
    merc_revenue: number;
    total_revenue: number;
    cafe_share: number;
    merc_share: number;
    daily: { labels: string[]; comedores: number[]; mercantiles: number[] };
    by_unit: { unit: string; comedores: number; mercantiles: number; total: number }[];
}

const props = defineProps<{
    mines: Mine[];
    subdealerships: Subdealership[];
    mercantiles: MercantilOption[];
    service_types: ServiceType[];
    payment_methods: string[];
    filters: Filters;
    kpis: Kpis;
    daily_trend: { date: string; date_full: string; count: number; revenue: number }[];
    org_breakdown: { level: string; rows: OrgRow[] };
    revenue_by_cafe: CafeSales[];
    by_service_type: { label: string; qty: number; revenue: number }[];
    by_subdealership: { name: string; sales: number; revenue: number }[];
    top_diners: { name: string; dni: string; visits: number; spent: number }[];
    visitor_ratio: { label: string; count: number }[];
    by_weekday: WeekdaySales[];
    service_heatmap: { name: string; data: { x: string; y: number }[] }[];
    service_mix_by_cafe: { categories: string[]; series: { name: string; data: number[] }[] };
    visit_frequency: { label: string; count: number }[];
    period_comparison: { labels: string[]; current: number[]; previous: number[]; prev_range: { start: string; end: string } };
    mercantil: MercantilBlock;
    consolidated: Consolidated;
}>();

/* ══════════════════════════════════════════════════════════════════
 | Filtros
 ══════════════════════════════════════════════════════════════════ */
const ALL = 'all';
const activeTab = ref<'comedores' | 'mercantiles' | 'consolidado'>('comedores');

const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);
const mineId = ref(props.filters.mine_id ?? ALL);
const unitId = ref(props.filters.unit_id ?? ALL);
const cafeId = ref(props.filters.cafe_id ?? ALL);
const serviceType = ref(props.filters.service_type ?? ALL);
const subdealershipId = ref(props.filters.subdealership_id ?? ALL);
const dinerType = ref(props.filters.diner_type || ALL);
const mercantilId = ref(props.filters.mercantil_id ?? ALL);
const paymentCondition = ref(props.filters.payment_condition ?? ALL);
const paymentMethod = ref(props.filters.payment_method ?? ALL);

/* Cascada mina → unidad → comedor: al subir de nivel se limpia lo que ya no aplica. */
const unitOptions = computed<Unit[]>(() =>
    mineId.value === ALL ? props.mines.flatMap((m) => m.units ?? []) : (props.mines.find((m) => m.id.toString() === mineId.value)?.units ?? []),
);

const cafeOptions = computed<Cafe[]>(() =>
    unitId.value === ALL
        ? unitOptions.value.flatMap((u) => u.cafes ?? [])
        : (unitOptions.value.find((u) => u.id.toString() === unitId.value)?.cafes ?? []),
);

const mercantilOptions = computed<MercantilOption[]>(() => {
    if (unitId.value !== ALL) return props.mercantiles.filter((m) => m.unit_id.toString() === unitId.value);
    if (mineId.value !== ALL) return props.mercantiles.filter((m) => m.unit?.mine_id?.toString() === mineId.value);
    return props.mercantiles;
});

const onMineChange = () => {
    unitId.value = ALL;
    cafeId.value = ALL;
    mercantilId.value = ALL;
};
const onUnitChange = () => {
    cafeId.value = ALL;
    mercantilId.value = ALL;
};

const clean = (v: string) => (v === ALL ? null : v);

const applyFilters = () => {
    router.get(
        route('generalreport.index'),
        {
            start_date: startDate.value,
            end_date: endDate.value,
            mine_id: clean(mineId.value),
            unit_id: clean(unitId.value),
            cafe_id: clean(cafeId.value),
            service_type: clean(serviceType.value),
            subdealership_id: clean(subdealershipId.value),
            diner_type: dinerType.value,
            mercantil_id: clean(mercantilId.value),
            payment_condition: clean(paymentCondition.value),
            payment_method: clean(paymentMethod.value),
        },
        { preserveState: true, preserveScroll: true },
    );
};

const resetFilters = () => {
    mineId.value = ALL;
    unitId.value = ALL;
    cafeId.value = ALL;
    serviceType.value = ALL;
    subdealershipId.value = ALL;
    dinerType.value = ALL;
    mercantilId.value = ALL;
    paymentCondition.value = ALL;
    paymentMethod.value = ALL;
    applyFilters();
};

/* Atajos de rango: lo que se consulta el 90% de las veces, sin abrir el datepicker. */
// Se formatea con los componentes locales: toISOString() pasa a UTC y en Peru (UTC-5)
// devolveria el dia siguiente desde las 19:00.
const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const setRange = (preset: string) => {
    const today = new Date();
    let from = new Date();
    let to = new Date();

    if (preset === 'today') {
        /* from = to = hoy */
    } else if (preset === '7d') {
        from.setDate(today.getDate() - 6);
    } else if (preset === '30d') {
        from.setDate(today.getDate() - 29);
    } else if (preset === 'month') {
        from = new Date(today.getFullYear(), today.getMonth(), 1);
    } else if (preset === 'prev_month') {
        from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        to = new Date(today.getFullYear(), today.getMonth(), 0);
    } else if (preset === 'year') {
        from = new Date(today.getFullYear(), 0, 1);
    }

    startDate.value = iso(from);
    endDate.value = iso(to);
    applyFilters();
};

const presets = [
    { key: 'today', label: 'Hoy' },
    { key: '7d', label: '7 días' },
    { key: '30d', label: '30 días' },
    { key: 'month', label: 'Este mes' },
    { key: 'prev_month', label: 'Mes anterior' },
    { key: 'year', label: 'Año' },
];

const activeFilterCount = computed(
    () =>
        [mineId, unitId, cafeId, serviceType, subdealershipId, dinerType, mercantilId, paymentCondition, paymentMethod].filter((r) => r.value !== ALL)
            .length,
);

/* ══════════════════════════════════════════════════════════════════
 | Helpers de formato y paleta
 ══════════════════════════════════════════════════════════════════ */
const fmt = (n: number) => 'S/' + Number(n ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtShort = (n: number) => 'S/' + Number(n ?? 0).toLocaleString('es-PE', { maximumFractionDigits: 0 });
const num = (n: number) => Number(n ?? 0).toLocaleString('es-PE');

const C = {
    emerald: '#10b981',
    indigo: '#6366f1',
    amber: '#f59e0b',
    sky: '#0ea5e9',
    violet: '#8b5cf6',
    rose: '#f43f5e',
    teal: '#14b8a6',
    slate: '#94a3b8',
};
const CATEGORICAL = [C.indigo, C.emerald, C.amber, C.sky, C.violet, C.rose, C.teal, C.slate];

type ChartKind = 'line' | 'area' | 'bar' | 'donut' | 'pie' | 'heatmap';

/** Base común de ApexCharts: sin ella cada gráfico traía su propio estilo de ejes y grid. */
const baseChart = (type: ChartKind) => ({
    chart: { type, toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: true, speed: 350 } },
    grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
    dataLabels: { enabled: false },
    legend: { position: 'top' as const, fontSize: '11px' },
});
const axisLabel = { style: { fontSize: '10px', colors: '#94a3b8' } };
const moneyAxis = { labels: { ...axisLabel, formatter: (v: number) => fmtShort(v) } };
const countAxis = { labels: { ...axisLabel, formatter: (v: number) => num(Math.round(v)) } };

const svcIcon = (label: string) => {
    if (label.includes('Desayuno')) return Sunrise;
    if (label.includes('Almuerzo')) return Utensils;
    if (label.includes('Cena')) return Moon;
    return Coffee;
};
const svcColor = (label: string) => {
    if (label.includes('Desayuno')) return { bg: 'bg-amber-50', text: 'text-amber-600', bar: C.amber };
    if (label.includes('Almuerzo')) return { bg: 'bg-blue-50', text: 'text-blue-600', bar: C.sky };
    if (label.includes('Cena')) return { bg: 'bg-indigo-50', text: 'text-indigo-600', bar: C.indigo };
    return { bg: 'bg-emerald-50', text: 'text-emerald-600', bar: C.emerald };
};
const growthIcon = (v: number | null) => (v !== null && v >= 0 ? ArrowUpRight : ArrowDownRight);
const rankClass = (idx: number) =>
    idx === 0
        ? 'bg-amber-400 text-white'
        : idx === 1
          ? 'bg-slate-300 text-white'
          : idx === 2
            ? 'bg-orange-300 text-white'
            : 'bg-slate-100 text-slate-500';

/* ══════════════════════════════════════════════════════════════════
 | Gráficos — Comedores
 ══════════════════════════════════════════════════════════════════ */

/* Tendencia diaria: ingresos (S/) y ventas (unidades) en ejes separados. */
const trendOptions = computed(() => ({
    ...baseChart('line'),
    stroke: { curve: 'smooth' as const, width: [3, 2], dashArray: [0, 4] },
    fill: { type: ['gradient', 'solid'], gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.02 } },
    colors: [C.emerald, C.indigo],
    xaxis: { categories: props.daily_trend.map((d) => d.date), axisBorder: { show: false }, axisTicks: { show: false }, labels: axisLabel },
    yaxis: [
        { ...moneyAxis, title: { text: 'Ingresos', style: { fontSize: '10px', color: '#94a3b8' } } },
        { opposite: true, ...countAxis, title: { text: 'Ventas', style: { fontSize: '10px', color: '#94a3b8' } } },
    ],
    tooltip: { shared: true, y: [{ formatter: (v: number) => fmt(v) }, { formatter: (v: number) => num(v) + ' ventas' }] },
}));
const trendSeries = computed(() => [
    { name: 'Ingresos', type: 'area', data: props.daily_trend.map((d) => d.revenue) },
    { name: 'Ventas', type: 'line', data: props.daily_trend.map((d) => d.count) },
]);

/* Acumulado del período vs período anterior alineado por día n. */
const cumulativeOptions = computed(() => ({
    ...baseChart('line'),
    stroke: { curve: 'smooth' as const, width: [3, 2], dashArray: [0, 6] },
    colors: [C.emerald, C.slate],
    xaxis: {
        categories: props.period_comparison.labels,
        labels: { ...axisLabel, rotate: 0, hideOverlappingLabels: true },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: moneyAxis,
    tooltip: { shared: true, y: { formatter: (v: number) => fmt(v) } },
}));
const cumulativeSeries = computed(() => [
    { name: 'Período actual', data: props.period_comparison.current },
    { name: 'Período anterior', data: props.period_comparison.previous },
]);
const paceDelta = computed(() => {
    const cur = props.period_comparison.current.at(-1) ?? 0;
    const prv = props.period_comparison.previous.at(-1) ?? 0;
    if (!prv) return null;
    return Math.round(((cur - prv) / prv) * 1000) / 10;
});

/* Desglose organizacional (mina / unidad / comedor según el filtro activo). */
const orgOptions = computed(() => ({
    ...baseChart('bar'),
    plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '62%' } },
    colors: [C.indigo],
    legend: { show: false },
    xaxis: { categories: props.org_breakdown.rows.map((r) => r.label), labels: { ...axisLabel, formatter: (v: number) => fmtShort(v) } },
    yaxis: { labels: { style: { fontSize: '10px', colors: '#64748b' } } },
    tooltip: {
        y: {
            formatter: (v: number, o?: { dataPointIndex: number }) => {
                const row = props.org_breakdown.rows[o?.dataPointIndex ?? -1];
                return `${fmt(v)} · ${num(row?.sales ?? 0)} ventas · ticket ${fmt(row?.avg_ticket ?? 0)}`;
            },
        },
    },
}));
const orgSeries = computed(() => [{ name: 'Ingresos', data: props.org_breakdown.rows.map((r) => r.revenue) }]);

/* Donut de servicios. */
const donutOptions = computed(() => ({
    chart: { type: 'donut' as const, fontFamily: 'inherit' },
    labels: props.by_service_type.map((s) => s.label),
    colors: props.by_service_type.map((s) => svcColor(s.label).bar),
    legend: { position: 'bottom' as const, fontSize: '11px' },
    dataLabels: { style: { fontSize: '11px' } },
    plotOptions: {
        pie: {
            donut: {
                size: '65%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Raciones',
                        fontSize: '12px',
                        fontWeight: 700,
                        formatter: (w: { globals: { seriesTotals: number[] } }) => num(w.globals.seriesTotals.reduce((a, b) => a + b, 0)),
                    },
                },
            },
        },
    },
    tooltip: { y: { formatter: (v: number) => num(v) + ' consumos' } },
}));
const donutSeries = computed(() => props.by_service_type.map((s) => s.qty));

/* Mapa de calor día × servicio: promedio de raciones por día ocurrido. */
const heatmapOptions = computed(() => ({
    ...baseChart('heatmap'),
    legend: { show: false },
    colors: [C.indigo],
    plotOptions: {
        heatmap: {
            radius: 4,
            enableShades: true,
            shadeIntensity: 0.55,
            colorScale: { inverse: false },
        },
    },
    xaxis: { labels: axisLabel, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { labels: { style: { fontSize: '10px', colors: '#64748b' } } },
    tooltip: { y: { formatter: (v: number) => num(v) + ' raciones/día en promedio' } },
    dataLabels: { enabled: true, style: { fontSize: '10px', colors: ['#fff'] } },
}));
const heatmapHasData = computed(() => props.service_heatmap.some((s) => s.data.some((d) => d.y > 0)));

/* Mix de servicios por comedor (barras apiladas al 100%). */
const mixOptions = computed(() => ({
    ...baseChart('bar'),
    chart: { type: 'bar' as const, stacked: true, stackType: '100%' as const, toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '65%' } },
    colors: props.service_mix_by_cafe.series.map((s) => svcColor(s.name).bar),
    xaxis: { categories: props.service_mix_by_cafe.categories, labels: { ...axisLabel, formatter: (v: number) => Math.round(v) + '%' } },
    yaxis: { labels: { style: { fontSize: '10px', colors: '#64748b' } } },
    tooltip: { y: { formatter: (v: number) => num(v) + ' raciones' } },
}));

/* Pareto de comedores: barras de ingreso + línea de participación acumulada. */
const paretoData = computed(() => {
    const rows = [...props.revenue_by_cafe].sort((a, b) => b.revenue - a.revenue).slice(0, 15);
    const total = rows.reduce((s, r) => s + r.revenue, 0);
    let run = 0;
    return rows.map((r) => {
        run += r.revenue;
        return { cafe: r.cafe, revenue: r.revenue, cum: total > 0 ? Math.round((run / total) * 1000) / 10 : 0 };
    });
});
const paretoOptions = computed(() => ({
    ...baseChart('line'),
    stroke: { width: [0, 3], curve: 'smooth' as const },
    colors: [C.amber, C.rose],
    plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } },
    xaxis: { categories: paretoData.value.map((r) => r.cafe), labels: { ...axisLabel, rotate: -35, trim: true, maxHeight: 70 } },
    yaxis: [{ ...moneyAxis }, { opposite: true, max: 100, min: 0, labels: { ...axisLabel, formatter: (v: number) => Math.round(v) + '%' } }],
    annotations: {
        yaxis: [
            {
                y: 80,
                yAxisIndex: 1,
                borderColor: C.rose,
                strokeDashArray: 4,
                label: { text: '80%', style: { fontSize: '9px', background: C.rose, color: '#fff' } },
            },
        ],
    },
    tooltip: { shared: true, y: [{ formatter: (v: number) => fmt(v) }, { formatter: (v: number) => v + '% acumulado' }] },
}));
const paretoSeries = computed(() => [
    { name: 'Ingresos', type: 'column', data: paretoData.value.map((r) => r.revenue) },
    { name: '% acumulado', type: 'line', data: paretoData.value.map((r) => r.cum) },
]);
/** Cuántos comedores explican el 80% del ingreso — la lectura accionable del Pareto. */
const paretoCore = computed(() => {
    const idx = paretoData.value.findIndex((r) => r.cum >= 80);
    return idx === -1 ? paretoData.value.length : idx + 1;
});

/* Frecuencia de visitas. */
const frequencyOptions = computed(() => ({
    ...baseChart('bar'),
    plotOptions: { bar: { borderRadius: 6, columnWidth: '50%', distributed: true } },
    colors: [C.sky, C.teal, C.emerald, C.indigo, C.violet],
    legend: { show: false },
    xaxis: { categories: props.visit_frequency.map((v) => v.label), labels: axisLabel },
    yaxis: countAxis,
    tooltip: { y: { formatter: (v: number) => num(v) + ' comensales' } },
}));
const frequencySeries = computed(() => [{ name: 'Comensales', data: props.visit_frequency.map((v) => v.count) }]);
const loyalShare = computed(() => {
    const total = props.visit_frequency.reduce((s, v) => s + v.count, 0);
    const loyal = props.visit_frequency.slice(2).reduce((s, v) => s + v.count, 0);
    return total > 0 ? Math.round((loyal / total) * 1000) / 10 : 0;
});

/* Subconcesionarias. */
const sdOptions = computed(() => ({
    ...baseChart('bar'),
    plotOptions: { bar: { borderRadius: 6, columnWidth: '52%' } },
    colors: [C.emerald],
    legend: { show: false },
    xaxis: { categories: props.by_subdealership.map((s) => s.name), labels: { ...axisLabel, rotate: -30, trim: true, maxHeight: 70 } },
    yaxis: moneyAxis,
    tooltip: { y: { formatter: (v: number) => fmt(v) } },
}));
const sdSeries = computed(() => [{ name: 'Ingresos', data: props.by_subdealership.map((s) => s.revenue) }]);

/* Día de la semana: se grafica el promedio por día ocurrido, no el bruto. */
const weekdayOptions = computed(() => ({
    ...baseChart('bar'),
    plotOptions: { bar: { borderRadius: 6, columnWidth: '52%' } },
    colors: [C.indigo],
    legend: { show: false },
    xaxis: { categories: props.by_weekday.map((w) => w.day), labels: axisLabel },
    yaxis: countAxis,
    tooltip: {
        y: {
            formatter: (v: number, o?: { dataPointIndex: number }) =>
                `${v} ventas/día · ${num(props.by_weekday[o?.dataPointIndex ?? -1]?.sales ?? 0)} en total`,
        },
    },
}));
const weekdaySeries = computed(() => [{ name: 'Promedio', data: props.by_weekday.map((w) => w.avg_sales) }]);

/* Tipo de comensal. */
const ratioOptions = computed(() => ({
    chart: { type: 'pie' as const, fontFamily: 'inherit' },
    labels: props.visitor_ratio.map((r) => r.label),
    colors: [C.indigo, '#c4b5fd'],
    legend: { position: 'bottom' as const, fontSize: '11px' },
    tooltip: { y: { formatter: (v: number) => num(v) + ' registros' } },
}));
const ratioSeries = computed(() => props.visitor_ratio.map((r) => r.count));

const cafesBySales = computed(() => [...props.revenue_by_cafe].sort((a, b) => b.sales - a.sales));

/* ══════════════════════════════════════════════════════════════════
 | Gráficos — Mercantiles
 ══════════════════════════════════════════════════════════════════ */
const m = computed(() => props.mercantil);

const mercTrendOptions = computed(() => ({
    ...baseChart('line'),
    stroke: { curve: 'smooth' as const, width: [3, 2], dashArray: [0, 4] },
    fill: { type: ['gradient', 'solid'], gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.02 } },
    colors: [C.violet, C.amber],
    xaxis: { categories: m.value.daily_trend.map((d) => d.date), labels: axisLabel, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: [{ ...moneyAxis }, { opposite: true, ...countAxis }],
    tooltip: { shared: true, y: [{ formatter: (v: number) => fmt(v) }, { formatter: (v: number) => num(v) + ' ventas' }] },
}));
const mercTrendSeries = computed(() => [
    { name: 'Ingresos', type: 'area', data: m.value.daily_trend.map((d) => d.revenue) },
    { name: 'Ventas', type: 'line', data: m.value.daily_trend.map((d) => d.count) },
]);

const productOptions = computed(() => ({
    ...baseChart('bar'),
    plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '65%' } },
    colors: [C.violet],
    legend: { show: false },
    xaxis: { categories: m.value.top_products.map((p) => p.name), labels: { ...axisLabel, formatter: (v: number) => fmtShort(v) } },
    yaxis: { labels: { style: { fontSize: '10px', colors: '#64748b' }, maxWidth: 160 } },
    tooltip: {
        y: {
            formatter: (v: number, o?: { dataPointIndex: number }) =>
                `${fmt(v)} · ${num(m.value.top_products[o?.dataPointIndex ?? -1]?.units ?? 0)} unidades`,
        },
    },
}));
const productSeries = computed(() => [{ name: 'Ingresos', data: m.value.top_products.map((p) => p.revenue) }]);

const categoryOptions = computed(() => ({
    chart: { type: 'donut' as const, fontFamily: 'inherit' },
    labels: m.value.by_category.map((c) => c.category),
    colors: CATEGORICAL,
    legend: { position: 'bottom' as const, fontSize: '11px' },
    plotOptions: {
        pie: {
            donut: {
                size: '62%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total',
                        fontSize: '12px',
                        fontWeight: 700,
                        formatter: (w: { globals: { seriesTotals: number[] } }) => fmtShort(w.globals.seriesTotals.reduce((a, b) => a + b, 0)),
                    },
                },
            },
        },
    },
    tooltip: { y: { formatter: (v: number) => fmt(v) } },
}));
const categorySeries = computed(() => m.value.by_category.map((c) => c.revenue));

const conditionOptions = computed(() => ({
    chart: { type: 'donut' as const, fontFamily: 'inherit' },
    labels: m.value.by_condition.map((c) => c.label),
    colors: [C.emerald, C.rose],
    legend: { position: 'bottom' as const, fontSize: '11px' },
    plotOptions: { pie: { donut: { size: '62%' } } },
    tooltip: { y: { formatter: (v: number) => fmt(v) } },
}));
const conditionSeries = computed(() => m.value.by_condition.map((c) => c.revenue));

const mercantilBarOptions = computed(() => ({
    ...baseChart('bar'),
    plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '60%' } },
    colors: [C.teal],
    legend: { show: false },
    xaxis: { categories: m.value.by_mercantil.map((x) => x.name), labels: { ...axisLabel, formatter: (v: number) => fmtShort(v) } },
    yaxis: { labels: { style: { fontSize: '10px', colors: '#64748b' } } },
    tooltip: {
        y: {
            formatter: (v: number, o?: { dataPointIndex: number }) =>
                `${fmt(v)} · ${num(m.value.by_mercantil[o?.dataPointIndex ?? -1]?.sales ?? 0)} ventas`,
        },
    },
}));
const mercantilBarSeries = computed(() => [{ name: 'Ingresos', data: m.value.by_mercantil.map((x) => x.revenue) }]);

const methodOptions = computed(() => ({
    ...baseChart('bar'),
    plotOptions: { bar: { borderRadius: 6, columnWidth: '48%', distributed: true } },
    colors: CATEGORICAL,
    legend: { show: false },
    xaxis: { categories: m.value.by_payment_method.map((x) => x.label), labels: { ...axisLabel, rotate: -20 } },
    yaxis: moneyAxis,
    tooltip: { y: { formatter: (v: number) => fmt(v) } },
}));
const methodSeries = computed(() => [{ name: 'Ingresos', data: m.value.by_payment_method.map((x) => x.revenue) }]);

const hourOptions = computed(() => ({
    ...baseChart('bar'),
    plotOptions: { bar: { borderRadius: 3, columnWidth: '70%' } },
    colors: [C.amber],
    legend: { show: false },
    xaxis: { categories: m.value.by_hour.map((h) => h.hour), labels: { ...axisLabel, hideOverlappingLabels: true } },
    yaxis: countAxis,
    tooltip: { y: { formatter: (v: number) => num(v) + ' ventas' } },
}));
const hourSeries = computed(() => [{ name: 'Ventas', data: m.value.by_hour.map((h) => h.sales) }]);
const peakHour = computed(() => [...m.value.by_hour].sort((a, b) => b.sales - a.sales)[0]);

const creditOptions = computed(() => ({
    ...baseChart('bar'),
    plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '58%' } },
    colors: [C.rose],
    legend: { show: false },
    xaxis: { categories: m.value.credit_by_subdealership.map((x) => x.name), labels: { ...axisLabel, formatter: (v: number) => fmtShort(v) } },
    yaxis: { labels: { style: { fontSize: '10px', colors: '#64748b' } } },
    tooltip: {
        y: {
            formatter: (v: number, o?: { dataPointIndex: number }) =>
                `${fmt(v)} por cobrar · ${num(m.value.credit_by_subdealership[o?.dataPointIndex ?? -1]?.sales ?? 0)} ventas`,
        },
    },
}));
const creditSeries = computed(() => [{ name: 'Por cobrar', data: m.value.credit_by_subdealership.map((x) => x.revenue) }]);

/* ══════════════════════════════════════════════════════════════════
 | Gráficos — Consolidado
 ══════════════════════════════════════════════════════════════════ */
const cons = computed(() => props.consolidated);

const channelDailyOptions = computed(() => ({
    ...baseChart('area'),
    chart: { type: 'area' as const, stacked: true, toolbar: { show: false }, fontFamily: 'inherit' },
    stroke: { curve: 'smooth' as const, width: 2 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05 } },
    colors: [C.emerald, C.violet],
    xaxis: {
        categories: cons.value.daily.labels,
        labels: { ...axisLabel, hideOverlappingLabels: true },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: moneyAxis,
    tooltip: { shared: true, y: { formatter: (v: number) => fmt(v) } },
}));
const channelDailySeries = computed(() => [
    { name: 'Comedores', data: cons.value.daily.comedores },
    { name: 'Mercantiles', data: cons.value.daily.mercantiles },
]);

const channelSplitOptions = computed(() => ({
    chart: { type: 'donut' as const, fontFamily: 'inherit' },
    labels: ['Comedores', 'Mercantiles'],
    colors: [C.emerald, C.violet],
    legend: { position: 'bottom' as const, fontSize: '11px' },
    plotOptions: {
        pie: {
            donut: {
                size: '68%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Ingreso total',
                        fontSize: '11px',
                        fontWeight: 700,
                        formatter: (w: { globals: { seriesTotals: number[] } }) => fmtShort(w.globals.seriesTotals.reduce((a, b) => a + b, 0)),
                    },
                },
            },
        },
    },
    tooltip: { y: { formatter: (v: number) => fmt(v) } },
}));
const channelSplitSeries = computed(() => [cons.value.cafe_revenue, cons.value.merc_revenue]);

const unitStackOptions = computed(() => ({
    ...baseChart('bar'),
    chart: { type: 'bar' as const, stacked: true, toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '62%' } },
    colors: [C.emerald, C.violet],
    xaxis: { categories: cons.value.by_unit.map((u) => u.unit), labels: { ...axisLabel, formatter: (v: number) => fmtShort(v) } },
    yaxis: { labels: { style: { fontSize: '10px', colors: '#64748b' } } },
    tooltip: { shared: true, y: { formatter: (v: number) => fmt(v) } },
}));
const unitStackSeries = computed(() => [
    { name: 'Comedores', data: cons.value.by_unit.map((u) => u.comedores) },
    { name: 'Mercantiles', data: cons.value.by_unit.map((u) => u.mercantiles) },
]);
</script>

<template>
    <Head title="Dashboard Gerencial" />
    <AppLayout>
        <div class="min-h-screen space-y-5 bg-slate-50 p-6">
            <!-- ══ Header ══ -->
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 shadow-lg shadow-emerald-500/30"
                    >
                        <BarChart3 class="h-6 w-6 text-white" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Dashboard Gerencial</h1>
                        <p class="text-sm text-slate-500">Inteligencia de negocio · {{ filters.start_date }} → {{ filters.end_date }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="p in presets"
                        :key="p.key"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700"
                        @click="setRange(p.key)"
                    >
                        {{ p.label }}
                    </button>
                </div>
            </div>

            <!-- ══ Barra de filtros ══ -->
            <Card class="border-slate-200 shadow-sm">
                <CardContent class="space-y-3 p-4">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="space-y-1">
                            <Label class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Desde</Label>
                            <Input v-model="startDate" type="date" class="h-8 w-36 text-xs" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Hasta</Label>
                            <Input v-model="endDate" type="date" class="h-8 w-36 text-xs" />
                        </div>

                        <div class="space-y-1">
                            <Label class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Mina</Label>
                            <Select v-model="mineId" @update:model-value="onMineChange">
                                <SelectTrigger class="h-8 w-40 text-xs"><SelectValue placeholder="Todas" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas las minas</SelectItem>
                                    <SelectItem v-for="mine in mines" :key="mine.id" :value="mine.id.toString()">{{ mine.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-1">
                            <Label class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Unidad</Label>
                            <Select v-model="unitId" @update:model-value="onUnitChange">
                                <SelectTrigger class="h-8 w-40 text-xs"><SelectValue placeholder="Todas" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas las unidades</SelectItem>
                                    <SelectItem v-for="u in unitOptions" :key="u.id" :value="u.id.toString()">{{ u.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-1">
                            <Label class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Comedor</Label>
                            <Select v-model="cafeId">
                                <SelectTrigger class="h-8 w-40 text-xs"><SelectValue placeholder="Todos" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos los comedores</SelectItem>
                                    <SelectItem v-for="c in cafeOptions" :key="c.id" :value="c.id.toString()">{{ c.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-1">
                            <Label class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Servicio</Label>
                            <Select v-model="serviceType">
                                <SelectTrigger class="h-8 w-32 text-xs"><SelectValue placeholder="Todos" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem v-for="s in service_types" :key="s.id" :value="s.id.toString()">{{ s.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-1">
                            <Label class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Subconcesionaria</Label>
                            <Select v-model="subdealershipId">
                                <SelectTrigger class="h-8 w-44 text-xs"><SelectValue placeholder="Todas" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas</SelectItem>
                                    <SelectItem v-for="s in subdealerships" :key="s.id" :value="s.id.toString()">{{ s.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-1">
                            <Label class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Tipo comensal</Label>
                            <Select v-model="dinerType">
                                <SelectTrigger class="h-8 w-36 text-xs"><SelectValue placeholder="Todos" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem value="diners">Solo comensales</SelectItem>
                                    <SelectItem value="visitors">Solo visitantes</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- Filtros propios de mercantiles: solo estorban en las otras pestañas. -->
                    <div v-if="activeTab === 'mercantiles'" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-3">
                        <div class="space-y-1">
                            <Label class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Mercantil</Label>
                            <Select v-model="mercantilId">
                                <SelectTrigger class="h-8 w-44 text-xs"><SelectValue placeholder="Todos" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos los mercantiles</SelectItem>
                                    <SelectItem v-for="mc in mercantilOptions" :key="mc.id" :value="mc.id.toString()">{{ mc.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Condición</Label>
                            <Select v-model="paymentCondition">
                                <SelectTrigger class="h-8 w-32 text-xs"><SelectValue placeholder="Todas" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas</SelectItem>
                                    <SelectItem value="contado">Contado</SelectItem>
                                    <SelectItem value="credito">Crédito</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Método de pago</Label>
                            <Select v-model="paymentMethod">
                                <SelectTrigger class="h-8 w-40 text-xs"><SelectValue placeholder="Todos" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem v-for="pm in payment_methods" :key="pm" :value="pm">{{ pm }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 border-t border-slate-100 pt-3">
                        <Button size="sm" class="h-8 gap-1.5 bg-emerald-600 hover:bg-emerald-700" @click="applyFilters">
                            <Filter class="h-3.5 w-3.5" /> Aplicar filtros
                        </Button>
                        <Button size="sm" variant="outline" class="h-8 gap-1.5" @click="resetFilters">
                            <RotateCcw class="h-3.5 w-3.5" /> Limpiar
                        </Button>
                        <span v-if="activeFilterCount" class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-bold text-emerald-700">
                            {{ activeFilterCount }} filtro{{ activeFilterCount > 1 ? 's' : '' }} activo{{ activeFilterCount > 1 ? 's' : '' }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- ══ Pestañas ══ -->
            <Tabs v-model="activeTab" class="space-y-5">
                <TabsList class="grid w-full max-w-xl grid-cols-3">
                    <TabsTrigger value="comedores" class="gap-1.5 text-xs font-bold"> <Utensils class="h-3.5 w-3.5" /> Comedores </TabsTrigger>
                    <TabsTrigger value="mercantiles" class="gap-1.5 text-xs font-bold"> <Store class="h-3.5 w-3.5" /> Mercantiles </TabsTrigger>
                    <TabsTrigger value="consolidado" class="gap-1.5 text-xs font-bold"> <Layers class="h-3.5 w-3.5" /> Consolidado </TabsTrigger>
                </TabsList>

                <!-- ═══════════════════════════════════════════════
                   | COMEDORES
                   ═══════════════════════════════════════════════ -->
                <TabsContent value="comedores" class="space-y-5">
                    <!-- KPIs -->
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                        <Card
                            class="overflow-hidden border-none bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/20"
                        >
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-[10px] font-bold tracking-widest text-emerald-100 uppercase">Ingresos</p>
                                        <p class="mt-1.5 text-2xl leading-none font-black">{{ fmt(kpis.total_revenue) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/20 p-2"><DollarSign class="h-5 w-5" /></div>
                                </div>
                                <div v-if="kpis.revenue_growth !== null" class="mt-3 flex items-center gap-1">
                                    <component
                                        :is="growthIcon(kpis.revenue_growth)"
                                        class="h-4 w-4"
                                        :class="kpis.revenue_growth >= 0 ? 'text-emerald-200' : 'text-red-300'"
                                    />
                                    <span class="text-[11px] font-bold" :class="kpis.revenue_growth >= 0 ? 'text-emerald-100' : 'text-red-200'">
                                        {{ Math.abs(kpis.revenue_growth) }}% vs período anterior
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            class="overflow-hidden border-none bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/20"
                        >
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-[10px] font-bold tracking-widest text-indigo-100 uppercase">Ventas</p>
                                        <p class="mt-1.5 text-2xl leading-none font-black">{{ num(kpis.total_sales) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/20 p-2"><ShoppingBag class="h-5 w-5" /></div>
                                </div>
                                <div v-if="kpis.sales_growth !== null" class="mt-3 flex items-center gap-1">
                                    <component
                                        :is="growthIcon(kpis.sales_growth)"
                                        class="h-4 w-4"
                                        :class="kpis.sales_growth >= 0 ? 'text-indigo-200' : 'text-red-300'"
                                    />
                                    <span class="text-[11px] font-bold" :class="kpis.sales_growth >= 0 ? 'text-indigo-100' : 'text-red-200'">
                                        {{ Math.abs(kpis.sales_growth) }}% vs período anterior
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        <Card class="overflow-hidden border-none bg-gradient-to-br from-blue-500 to-cyan-500 text-white shadow-lg shadow-blue-500/20">
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-[10px] font-bold tracking-widest text-blue-100 uppercase">Comensales</p>
                                        <p class="mt-1.5 text-2xl leading-none font-black">{{ num(kpis.total_diners) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/20 p-2"><Users class="h-5 w-5" /></div>
                                </div>
                                <p class="mt-3 text-[11px] font-semibold text-blue-100">+ {{ num(kpis.total_visitors) }} visitantes externos</p>
                            </CardContent>
                        </Card>

                        <Card
                            class="overflow-hidden border-none bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-lg shadow-amber-500/20"
                        >
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-[10px] font-bold tracking-widest text-amber-100 uppercase">Ticket promedio</p>
                                        <p class="mt-1.5 text-2xl leading-none font-black">{{ fmt(kpis.avg_ticket) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/20 p-2"><TrendingUp class="h-5 w-5" /></div>
                                </div>
                                <p class="mt-3 text-[11px] font-semibold text-amber-100">por venta registrada</p>
                            </CardContent>
                        </Card>

                        <Card
                            class="overflow-hidden border-none bg-gradient-to-br from-slate-700 to-slate-900 text-white shadow-lg shadow-slate-500/20"
                        >
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-[10px] font-bold tracking-widest text-slate-300 uppercase">Raciones servidas</p>
                                        <p class="mt-1.5 text-2xl leading-none font-black">{{ num(kpis.total_servings) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/20 p-2"><Utensils class="h-5 w-5" /></div>
                                </div>
                                <p class="mt-3 text-[11px] font-semibold text-slate-300">{{ kpis.servings_per_sale }} por venta</p>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Tendencia + servicios -->
                    <div class="grid gap-4 lg:grid-cols-3">
                        <Card class="border-slate-200 shadow-sm lg:col-span-2">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <TrendingUp class="h-4 w-4 text-emerald-500" /> Tendencia Diaria — Ingresos y Ventas
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts v-if="daily_trend.length" height="250" :options="trendOptions" :series="trendSeries" />
                                <div v-else class="flex h-56 items-center justify-center text-sm text-slate-400">Sin datos para el período</div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <Utensils class="h-4 w-4 text-indigo-500" /> Consumo por Servicio
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="by_service_type.length"
                                    type="donut"
                                    height="210"
                                    :options="donutOptions"
                                    :series="donutSeries"
                                />
                                <div v-else class="flex h-40 items-center justify-center text-sm text-slate-400">Sin datos</div>
                                <div class="mt-2 space-y-2">
                                    <div
                                        v-for="svc in by_service_type"
                                        :key="svc.label"
                                        class="flex items-center gap-2 rounded-lg px-2 py-1.5"
                                        :class="svcColor(svc.label).bg"
                                    >
                                        <component :is="svcIcon(svc.label)" class="h-3.5 w-3.5 shrink-0" :class="svcColor(svc.label).text" />
                                        <span class="flex-1 text-[11px] font-semibold" :class="svcColor(svc.label).text">{{ svc.label }}</span>
                                        <span class="text-[11px] font-black" :class="svcColor(svc.label).text">{{ num(svc.qty) }}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Ritmo acumulado + desglose organizacional -->
                    <div class="grid gap-4 lg:grid-cols-2">
                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center justify-between gap-2 text-sm font-bold text-slate-700">
                                    <span class="flex items-center gap-2"
                                        ><Target class="h-4 w-4 text-emerald-500" /> Ritmo Acumulado vs Período Anterior</span
                                    >
                                    <span
                                        v-if="paceDelta !== null"
                                        class="rounded-full px-2 py-0.5 text-[10px] font-black"
                                        :class="paceDelta >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                                    >
                                        {{ paceDelta >= 0 ? '+' : '' }}{{ paceDelta }}%
                                    </span>
                                </CardTitle>
                                <p class="text-[11px] text-slate-400">
                                    Comparado con {{ period_comparison.prev_range.start }} → {{ period_comparison.prev_range.end }}
                                </p>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="period_comparison.current.length"
                                    height="230"
                                    :options="cumulativeOptions"
                                    :series="cumulativeSeries"
                                />
                                <div v-else class="flex h-52 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <Building2 class="h-4 w-4 text-indigo-500" /> Ingresos por {{ org_breakdown.level }}
                                </CardTitle>
                                <p class="text-[11px] text-slate-400">El desglose baja de nivel conforme filtras mina y unidad</p>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts v-if="org_breakdown.rows.length" type="bar" height="230" :options="orgOptions" :series="orgSeries" />
                                <div v-else class="flex h-52 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Mapa de calor + mix por comedor -->
                    <div class="grid gap-4 lg:grid-cols-2">
                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <CalendarDays class="h-4 w-4 text-indigo-500" /> Demanda por Día y Servicio
                                </CardTitle>
                                <p class="text-[11px] text-slate-400">Raciones promedio por día ocurrido — para dimensionar turnos y compras</p>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="heatmapHasData"
                                    type="heatmap"
                                    height="230"
                                    :options="heatmapOptions"
                                    :series="service_heatmap"
                                />
                                <div v-else class="flex h-52 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <PieChart class="h-4 w-4 text-amber-500" /> Mix de Servicios por Comedor
                                </CardTitle>
                                <p class="text-[11px] text-slate-400">Un comedor sin desayunos frente a sus pares es una brecha de cobertura</p>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="service_mix_by_cafe.categories.length"
                                    type="bar"
                                    height="230"
                                    :options="mixOptions"
                                    :series="service_mix_by_cafe.series"
                                />
                                <div v-else class="flex h-52 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Pareto + frecuencia -->
                    <div class="grid gap-4 lg:grid-cols-3">
                        <Card class="border-slate-200 shadow-sm lg:col-span-2">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <BarChart3 class="h-4 w-4 text-amber-500" /> Concentración de Ingresos (Pareto)
                                </CardTitle>
                                <p v-if="paretoData.length" class="text-[11px] text-slate-400">
                                    {{ paretoCore }} de {{ paretoData.length }} comedores concentran el 80% del ingreso
                                </p>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts v-if="paretoData.length" height="260" :options="paretoOptions" :series="paretoSeries" />
                                <div v-else class="flex h-56 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <Repeat class="h-4 w-4 text-sky-500" /> Frecuencia de Visitas
                                </CardTitle>
                                <p class="text-[11px] text-slate-400">{{ loyalShare }}% del padrón consume más de 5 veces</p>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="visit_frequency.some((v) => v.count > 0)"
                                    type="bar"
                                    height="260"
                                    :options="frequencyOptions"
                                    :series="frequencySeries"
                                />
                                <div v-else class="flex h-56 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Subconcesionarias + día de semana + tipo de comensal -->
                    <div class="grid gap-4 lg:grid-cols-3">
                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <ShoppingBag class="h-4 w-4 text-emerald-500" /> Ingresos por Subconcesionaria
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts v-if="by_subdealership.length" type="bar" height="220" :options="sdOptions" :series="sdSeries" />
                                <div v-else class="flex h-48 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <CalendarDays class="h-4 w-4 text-indigo-500" /> Ventas Promedio por Día
                                </CardTitle>
                                <p class="text-[11px] text-slate-400">Normalizado: 4 lunes y 5 martes no son comparables en bruto</p>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="by_weekday.some((w) => w.sales > 0)"
                                    type="bar"
                                    height="220"
                                    :options="weekdayOptions"
                                    :series="weekdaySeries"
                                />
                                <div v-else class="flex h-48 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <UserCheck class="h-4 w-4 text-violet-500" /> Tipo de Comensal
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="ratioSeries.some((v) => v > 0)"
                                    type="pie"
                                    height="180"
                                    :options="ratioOptions"
                                    :series="ratioSeries"
                                />
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <div v-for="r in visitor_ratio" :key="r.label" class="rounded-xl bg-slate-50 p-3 text-center">
                                        <p class="text-xl font-black text-slate-800">{{ num(r.count) }}</p>
                                        <p class="text-[10px] font-semibold text-slate-500">{{ r.label }}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Rankings -->
                    <div class="grid gap-4 lg:grid-cols-3">
                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <Coffee class="h-4 w-4 text-amber-500" /> Ranking por Ingresos
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="max-h-80 overflow-y-auto">
                                <div v-if="revenue_by_cafe.length" class="space-y-2">
                                    <div
                                        v-for="(cafe, idx) in revenue_by_cafe"
                                        :key="cafe.cafe"
                                        class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2.5"
                                    >
                                        <span
                                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-black"
                                            :class="rankClass(idx)"
                                            >{{ idx + 1 }}</span
                                        >
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-[12px] font-bold text-slate-700">{{ cafe.cafe }}</p>
                                            <p class="text-[10px] text-slate-400">{{ num(cafe.sales) }} ventas · ticket {{ fmt(cafe.avg_ticket) }}</p>
                                        </div>
                                        <span class="shrink-0 text-[12px] font-black text-emerald-600">{{ fmt(cafe.revenue) }}</span>
                                    </div>
                                </div>
                                <div v-else class="flex h-40 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <ShoppingBag class="h-4 w-4 text-amber-500" /> Ranking por Cantidad
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="max-h-80 overflow-y-auto">
                                <div v-if="cafesBySales.length" class="space-y-2">
                                    <div
                                        v-for="(row, idx) in cafesBySales"
                                        :key="row.cafe"
                                        class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2.5"
                                    >
                                        <span
                                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-black"
                                            :class="rankClass(idx)"
                                            >{{ idx + 1 }}</span
                                        >
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-[12px] font-bold text-slate-700">{{ row.cafe }}</p>
                                            <p class="text-[10px] text-slate-400">{{ fmt(row.revenue) }}</p>
                                        </div>
                                        <span class="shrink-0 text-[13px] font-black text-amber-600">{{ num(row.sales) }}</span>
                                    </div>
                                </div>
                                <div v-else class="flex h-40 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <Users class="h-4 w-4 text-blue-500" /> Top 10 Comensales
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="max-h-80 overflow-y-auto">
                                <div v-if="top_diners.length" class="space-y-1.5">
                                    <div
                                        v-for="(d, idx) in top_diners"
                                        :key="d.dni"
                                        class="flex items-center gap-2.5 rounded-lg px-2.5 py-2"
                                        :class="idx % 2 === 0 ? 'bg-slate-50' : 'bg-white'"
                                    >
                                        <div
                                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-[10px] font-black text-indigo-600"
                                        >
                                            {{ d.name?.charAt(0) ?? '?' }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-[11px] font-bold text-slate-700 uppercase">{{ d.name }}</p>
                                            <p class="font-mono text-[9px] text-slate-400">{{ d.dni }}</p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="text-[11px] font-black text-indigo-600">{{ d.visits }}x</p>
                                            <p class="text-[9px] font-semibold text-slate-400">{{ fmt(d.spent) }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex h-40 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- ═══════════════════════════════════════════════
                   | MERCANTILES
                   ═══════════════════════════════════════════════ -->
                <TabsContent value="mercantiles" class="space-y-5">
                    <!-- KPIs -->
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
                        <Card
                            class="overflow-hidden border-none bg-gradient-to-br from-violet-500 to-purple-600 text-white shadow-lg shadow-violet-500/20"
                        >
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-[10px] font-bold tracking-widest text-violet-100 uppercase">Ingresos</p>
                                        <p class="mt-1.5 text-xl leading-none font-black">{{ fmt(mercantil.kpis.total_revenue) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/20 p-2"><Store class="h-5 w-5" /></div>
                                </div>
                                <div v-if="mercantil.kpis.revenue_growth !== null" class="mt-3 flex items-center gap-1">
                                    <component
                                        :is="growthIcon(mercantil.kpis.revenue_growth)"
                                        class="h-4 w-4"
                                        :class="mercantil.kpis.revenue_growth >= 0 ? 'text-violet-200' : 'text-red-300'"
                                    />
                                    <span class="text-[11px] font-bold text-violet-100"
                                        >{{ Math.abs(mercantil.kpis.revenue_growth) }}% vs anterior</span
                                    >
                                </div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-[10px] font-bold tracking-widest text-slate-500 uppercase">Ventas</p>
                                        <p class="mt-1.5 text-xl leading-none font-black text-slate-800">{{ num(mercantil.kpis.total_sales) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-indigo-50 p-2"><ShoppingCart class="h-5 w-5 text-indigo-600" /></div>
                                </div>
                                <p class="mt-3 text-[11px] font-semibold text-slate-400">transacciones registradas</p>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-[10px] font-bold tracking-widest text-slate-500 uppercase">Ticket promedio</p>
                                        <p class="mt-1.5 text-xl leading-none font-black text-slate-800">{{ fmt(mercantil.kpis.avg_ticket) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-emerald-50 p-2"><TrendingUp class="h-5 w-5 text-emerald-600" /></div>
                                </div>
                                <p class="mt-3 text-[11px] font-semibold text-slate-400">por transacción</p>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-[10px] font-bold tracking-widest text-slate-500 uppercase">Unidades</p>
                                        <p class="mt-1.5 text-xl leading-none font-black text-slate-800">{{ num(mercantil.kpis.total_units) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-amber-50 p-2"><Package class="h-5 w-5 text-amber-600" /></div>
                                </div>
                                <p class="mt-3 text-[11px] font-semibold text-slate-400">productos vendidos</p>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-[10px] font-bold tracking-widest text-slate-500 uppercase">Por cobrar</p>
                                        <p class="mt-1.5 text-xl leading-none font-black text-rose-600">
                                            {{ fmt(mercantil.kpis.credit_outstanding) }}
                                        </p>
                                    </div>
                                    <div class="rounded-xl bg-rose-50 p-2"><CreditCard class="h-5 w-5 text-rose-600" /></div>
                                </div>
                                <p class="mt-3 text-[11px] font-semibold text-slate-400">{{ mercantil.kpis.credit_share }}% del ingreso al crédito</p>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-[10px] font-bold tracking-widest text-slate-500 uppercase">Inventario</p>
                                        <p class="mt-1.5 text-xl leading-none font-black text-slate-800">{{ fmt(mercantil.kpis.inventory_value) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-teal-50 p-2"><Boxes class="h-5 w-5 text-teal-600" /></div>
                                </div>
                                <p class="mt-3 text-[11px] font-semibold text-slate-400">valorización del stock actual</p>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Tendencia + condición de pago -->
                    <div class="grid gap-4 lg:grid-cols-3">
                        <Card class="border-slate-200 shadow-sm lg:col-span-2">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <TrendingUp class="h-4 w-4 text-violet-500" /> Tendencia Diaria de Mercantiles
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="mercantil.daily_trend.length"
                                    height="250"
                                    :options="mercTrendOptions"
                                    :series="mercTrendSeries"
                                />
                                <div v-else class="flex h-56 items-center justify-center text-sm text-slate-400">
                                    Sin ventas de mercantil en el período
                                </div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <Wallet class="h-4 w-4 text-emerald-500" /> Contado vs Crédito
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="conditionSeries.some((v) => v > 0)"
                                    type="donut"
                                    height="210"
                                    :options="conditionOptions"
                                    :series="conditionSeries"
                                />
                                <div v-else class="flex h-40 items-center justify-center text-sm text-slate-400">Sin datos</div>
                                <div class="mt-2 space-y-2">
                                    <div
                                        v-for="c in mercantil.by_condition"
                                        :key="c.label"
                                        class="flex items-center gap-2 rounded-lg bg-slate-50 px-2.5 py-2"
                                    >
                                        <span class="flex-1 text-[11px] font-semibold text-slate-600">{{ c.label }}</span>
                                        <span class="text-[10px] text-slate-400">{{ num(c.count) }} ventas</span>
                                        <span class="text-[11px] font-black text-slate-700">{{ fmt(c.revenue) }}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Productos + categorías -->
                    <div class="grid gap-4 lg:grid-cols-3">
                        <Card class="border-slate-200 shadow-sm lg:col-span-2">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <Package class="h-4 w-4 text-violet-500" /> Top Productos por Ingreso
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="mercantil.top_products.length"
                                    type="bar"
                                    height="300"
                                    :options="productOptions"
                                    :series="productSeries"
                                />
                                <div v-else class="flex h-56 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <PieChart class="h-4 w-4 text-indigo-500" /> Ingresos por Categoría
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="categorySeries.some((v) => v > 0)"
                                    type="donut"
                                    height="240"
                                    :options="categoryOptions"
                                    :series="categorySeries"
                                />
                                <div v-else class="flex h-56 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Por mercantil + método de pago + hora -->
                    <div class="grid gap-4 lg:grid-cols-3">
                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <Store class="h-4 w-4 text-teal-500" /> Ingresos por Mercantil
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="mercantil.by_mercantil.length"
                                    type="bar"
                                    height="230"
                                    :options="mercantilBarOptions"
                                    :series="mercantilBarSeries"
                                />
                                <div v-else class="flex h-52 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <CreditCard class="h-4 w-4 text-indigo-500" /> Método de Pago
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="mercantil.by_payment_method.length"
                                    type="bar"
                                    height="230"
                                    :options="methodOptions"
                                    :series="methodSeries"
                                />
                                <div v-else class="flex h-52 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <AlarmClock class="h-4 w-4 text-amber-500" /> Ventas por Hora
                                </CardTitle>
                                <p v-if="peakHour && peakHour.sales > 0" class="text-[11px] text-slate-400">
                                    Hora pico: {{ peakHour.hour }} con {{ num(peakHour.sales) }} ventas
                                </p>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="peakHour && peakHour.sales > 0"
                                    type="bar"
                                    height="230"
                                    :options="hourOptions"
                                    :series="hourSeries"
                                />
                                <div v-else class="flex h-52 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Cobranza + compradores -->
                    <div class="grid gap-4 lg:grid-cols-2">
                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <CreditCard class="h-4 w-4 text-rose-500" /> Cuentas por Cobrar por Subconcesionaria
                                </CardTitle>
                                <p class="text-[11px] text-slate-400">Mercadería entregada al crédito y aún no cobrada</p>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="mercantil.credit_by_subdealership.length"
                                    type="bar"
                                    height="230"
                                    :options="creditOptions"
                                    :series="creditSeries"
                                />
                                <div v-else class="flex h-52 items-center justify-center text-sm text-slate-400">
                                    Sin ventas al crédito en el período
                                </div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <Users class="h-4 w-4 text-violet-500" /> Top Compradores
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="max-h-72 overflow-y-auto">
                                <div v-if="mercantil.top_buyers.length" class="space-y-1.5">
                                    <div
                                        v-for="(b, idx) in mercantil.top_buyers"
                                        :key="b.dni"
                                        class="flex items-center gap-2.5 rounded-lg px-2.5 py-2"
                                        :class="idx % 2 === 0 ? 'bg-slate-50' : 'bg-white'"
                                    >
                                        <span
                                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-black"
                                            :class="rankClass(idx)"
                                            >{{ idx + 1 }}</span
                                        >
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-[11px] font-bold text-slate-700 uppercase">{{ b.name }}</p>
                                            <p class="font-mono text-[9px] text-slate-400">{{ b.dni }}</p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="text-[11px] font-black text-violet-600">{{ b.purchases }}x</p>
                                            <p class="text-[9px] font-semibold text-slate-400">{{ fmt(b.spent) }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex h-40 items-center justify-center text-sm text-slate-400">Sin compradores identificados</div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Alertas de inventario -->
                    <div class="grid gap-4 lg:grid-cols-2">
                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <AlertTriangle class="h-4 w-4 text-rose-500" /> Reposición Urgente
                                </CardTitle>
                                <p class="text-[11px] text-slate-400">Productos activos con 10 unidades o menos en stock</p>
                            </CardHeader>
                            <CardContent class="max-h-72 overflow-y-auto">
                                <div v-if="mercantil.low_stock.length" class="space-y-1.5">
                                    <div
                                        v-for="p in mercantil.low_stock"
                                        :key="p.name + p.mercantil"
                                        class="flex items-center gap-3 rounded-lg border border-slate-100 px-3 py-2"
                                        :class="p.stock === 0 ? 'bg-rose-50' : 'bg-slate-50/60'"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-[11px] font-bold text-slate-700">{{ p.name }}</p>
                                            <p class="truncate text-[9px] text-slate-400">{{ p.mercantil }} · {{ p.category }}</p>
                                        </div>
                                        <span
                                            class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-black"
                                            :class="p.stock === 0 ? 'bg-rose-500 text-white' : 'bg-amber-100 text-amber-700'"
                                        >
                                            {{ p.stock === 0 ? 'AGOTADO' : p.stock + ' u.' }}
                                        </span>
                                    </div>
                                </div>
                                <div v-else class="flex h-40 items-center justify-center text-sm text-slate-400">Sin alertas de stock</div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <Boxes class="h-4 w-4 text-amber-500" /> Capital Inmovilizado
                                </CardTitle>
                                <p class="text-[11px] text-slate-400">Con stock y sin una sola venta en el período</p>
                            </CardHeader>
                            <CardContent class="max-h-72 overflow-y-auto">
                                <div v-if="mercantil.slow_movers.length" class="space-y-1.5">
                                    <div
                                        v-for="p in mercantil.slow_movers"
                                        :key="p.name + p.mercantil"
                                        class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-[11px] font-bold text-slate-700">{{ p.name }}</p>
                                            <p class="truncate text-[9px] text-slate-400">{{ p.mercantil }} · {{ p.stock }} u. en stock</p>
                                        </div>
                                        <span class="shrink-0 text-[11px] font-black text-amber-600">{{ fmt(p.tied_up) }}</span>
                                    </div>
                                </div>
                                <div v-else class="flex h-40 items-center justify-center text-sm text-slate-400">
                                    Todo el catálogo rotó en el período
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- ═══════════════════════════════════════════════
                   | CONSOLIDADO
                   ═══════════════════════════════════════════════ -->
                <TabsContent value="consolidado" class="space-y-5">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <Card class="overflow-hidden border-none bg-gradient-to-br from-slate-800 to-slate-900 text-white shadow-lg">
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-[10px] font-bold tracking-widest text-slate-300 uppercase">Ingreso Total Consolidado</p>
                                        <p class="mt-1.5 text-3xl leading-none font-black">{{ fmt(consolidated.total_revenue) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/20 p-2"><Layers class="h-5 w-5" /></div>
                                </div>
                                <p class="mt-3 text-[11px] font-semibold text-slate-300">Comedores + mercantiles en el período filtrado</p>
                            </CardContent>
                        </Card>

                        <Card
                            class="overflow-hidden border-none bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/20"
                        >
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-[10px] font-bold tracking-widest text-emerald-100 uppercase">Canal Comedores</p>
                                        <p class="mt-1.5 text-3xl leading-none font-black">{{ fmt(consolidated.cafe_revenue) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/20 p-2"><Utensils class="h-5 w-5" /></div>
                                </div>
                                <p class="mt-3 text-[11px] font-semibold text-emerald-100">{{ consolidated.cafe_share }}% del ingreso total</p>
                            </CardContent>
                        </Card>

                        <Card
                            class="overflow-hidden border-none bg-gradient-to-br from-violet-500 to-purple-600 text-white shadow-lg shadow-violet-500/20"
                        >
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-[10px] font-bold tracking-widest text-violet-100 uppercase">Canal Mercantiles</p>
                                        <p class="mt-1.5 text-3xl leading-none font-black">{{ fmt(consolidated.merc_revenue) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/20 p-2"><Store class="h-5 w-5" /></div>
                                </div>
                                <p class="mt-3 text-[11px] font-semibold text-violet-100">{{ consolidated.merc_share }}% del ingreso total</p>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-3">
                        <Card class="border-slate-200 shadow-sm lg:col-span-2">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <TrendingUp class="h-4 w-4 text-emerald-500" /> Ingreso Diario por Canal
                                </CardTitle>
                                <p class="text-[11px] text-slate-400">Apilado: la altura total es el ingreso del día</p>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="consolidated.daily.labels.length"
                                    type="area"
                                    height="270"
                                    :options="channelDailyOptions"
                                    :series="channelDailySeries"
                                />
                                <div v-else class="flex h-56 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <PieChart class="h-4 w-4 text-indigo-500" /> Participación por Canal
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="consolidated.total_revenue > 0"
                                    type="donut"
                                    height="270"
                                    :options="channelSplitOptions"
                                    :series="channelSplitSeries"
                                />
                                <div v-else class="flex h-56 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <Building2 class="h-4 w-4 text-indigo-500" /> Aporte por Unidad
                                </CardTitle>
                                <p class="text-[11px] text-slate-400">Qué unidades sostienen el ingreso y con qué mezcla de canales</p>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts
                                    v-if="consolidated.by_unit.length"
                                    type="bar"
                                    height="300"
                                    :options="unitStackOptions"
                                    :series="unitStackSeries"
                                />
                                <div v-else class="flex h-56 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>

                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                    <Layers class="h-4 w-4 text-slate-500" /> Detalle por Unidad
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="max-h-80 overflow-y-auto">
                                <table v-if="consolidated.by_unit.length" class="w-full text-left">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                                            <th class="py-2">Unidad</th>
                                            <th class="py-2 text-right">Comedores</th>
                                            <th class="py-2 text-right">Mercantiles</th>
                                            <th class="py-2 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="u in consolidated.by_unit" :key="u.unit" class="border-b border-slate-50">
                                            <td class="py-2 text-[11px] font-bold text-slate-700">{{ u.unit }}</td>
                                            <td class="py-2 text-right text-[11px] text-emerald-600">{{ fmt(u.comedores) }}</td>
                                            <td class="py-2 text-right text-[11px] text-violet-600">{{ fmt(u.mercantiles) }}</td>
                                            <td class="py-2 text-right text-[11px] font-black text-slate-800">{{ fmt(u.total) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-else class="flex h-40 items-center justify-center text-sm text-slate-400">Sin datos</div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
