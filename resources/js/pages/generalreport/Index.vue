<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Angry,
    ArrowDownRight,
    ArrowUpRight,
    BarChart3,
    Building2,
    Coffee,
    DollarSign,
    Filter,
    Frown,
    Laugh,
    Meh,
    Moon,
    ShoppingBag,
    Smile,
    SmilePlus,
    Sunrise,
    TrendingUp,
    UserCheck,
    Users,
    Utensils,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

interface Mine { id: number; name: string }
interface Satisfaction {
    total_votes: number;
    avg_score: number | null;
    by_score: Record<string, number>;
    by_cafe: { cafe: string; avg_score: number; votes: number }[];
    trend: { date: string; avg_score: number; votes: number }[];
}
interface Filters { start_date: string; end_date: string; mine_id: string | null }
interface Kpis {
    total_revenue: number; total_sales: number; total_diners: number;
    total_visitors: number; revenue_growth: number | null; sales_growth: number | null; avg_ticket: number;
}

const props = defineProps<{
    mines: Mine[];
    filters: Filters;
    kpis: Kpis;
    daily_trend: any[];
    revenue_by_mine: any[];
    revenue_by_cafe: any[];
    by_service_type: any[];
    by_subdealership: any[];
    top_diners: any[];
    visitor_ratio: any[];
    satisfaction: Satisfaction;
}>();

/* ── Filters ── */
const startDate    = ref(props.filters.start_date);
const endDate      = ref(props.filters.end_date);
const selectedMine = ref<string>(props.filters.mine_id?.toString() ?? 'all');

const applyFilters = () => {
    router.get(route('generalreport.index'), {
        start_date: startDate.value,
        end_date:   endDate.value,
        mine_id:    selectedMine.value !== 'all' ? selectedMine.value : null,
    }, { preserveState: true, preserveScroll: true });
};

/* ── Helpers ── */
const fmt = (n: number) => 'S/' + n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const svcIcon = (label: string) => {
    if (label.includes('Desayuno'))  return Sunrise;
    if (label.includes('Almuerzo'))  return Utensils;
    if (label.includes('Cena'))      return Moon;
    return Coffee;
};
const svcColor = (label: string) => {
    if (label.includes('Desayuno'))  return { bg: 'bg-amber-50',  text: 'text-amber-600',  bar: '#f59e0b' };
    if (label.includes('Almuerzo'))  return { bg: 'bg-blue-50',   text: 'text-blue-600',   bar: '#3b82f6' };
    if (label.includes('Cena'))      return { bg: 'bg-indigo-50', text: 'text-indigo-600', bar: '#6366f1' };
    return { bg: 'bg-emerald-50', text: 'text-emerald-600', bar: '#10b981' };
};

/* ── Chart: daily revenue line ── */
const lineOptions = computed(() => ({
    chart: { type: 'area' as const, toolbar: { show: false }, sparkline: { enabled: false }, fontFamily: 'inherit' },
    stroke: { curve: 'smooth' as const, width: 3 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02 } },
    colors: ['#10b981', '#6366f1'],
    xaxis: {
        categories: props.daily_trend.map(d => d.date),
        axisBorder: { show: false }, axisTicks: { show: false },
        labels: { style: { fontSize: '10px', colors: '#94a3b8' } },
    },
    yaxis: {
        labels: {
            style: { fontSize: '10px', colors: '#94a3b8' },
            formatter: (v: number) => 'S/' + v.toFixed(0),
        },
    },
    grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
    dataLabels: { enabled: false },
    legend: { position: 'top' as const, fontSize: '11px' },
    tooltip: { y: { formatter: (v: number) => 'S/' + v.toFixed(2) } },
}));
const lineSeries = computed(() => [
    { name: 'Ingresos', data: props.daily_trend.map(d => d.revenue) },
    { name: 'Ventas',   data: props.daily_trend.map(d => d.count) },
]);

/* ── Chart: revenue by mine bar ── */
const mineBarOptions = computed(() => ({
    chart: { type: 'bar' as const, toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '60%' } },
    colors: ['#6366f1'],
    xaxis: {
        categories: props.revenue_by_mine.map(r => r.mine),
        labels: { style: { fontSize: '10px', colors: '#94a3b8' }, formatter: (v: number) => 'S/' + v.toFixed(0) },
    },
    yaxis: { labels: { style: { fontSize: '10px', colors: '#64748b' } } },
    grid: { borderColor: '#f1f5f9' },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (v: number) => 'S/' + v.toFixed(2) } },
}));
const mineBarSeries = computed(() => [
    { name: 'Ingresos', data: props.revenue_by_mine.map(r => r.revenue) },
]);

/* ── Chart: service type donut ── */
const donutOptions = computed(() => ({
    chart: { type: 'donut' as const, fontFamily: 'inherit' },
    labels: props.by_service_type.map(s => s.label),
    colors: props.by_service_type.map(s => svcColor(s.label).bar),
    legend: { position: 'bottom' as const, fontSize: '11px' },
    dataLabels: { style: { fontSize: '11px' } },
    plotOptions: { pie: { donut: { size: '65%', labels: {
        show: true,
        total: { show: true, label: 'Total', fontSize: '12px', fontWeight: 700,
            formatter: (w: any) => w.globals.seriesTotals.reduce((a: number, b: number) => a + b, 0).toLocaleString() }
    } } } },
    tooltip: { y: { formatter: (v: number) => v + ' consumos' } },
}));
const donutSeries = computed(() => props.by_service_type.map(s => s.qty));

/* ── Chart: subdealership revenue bar ── */
const sdBarOptions = computed(() => ({
    chart: { type: 'bar' as const, toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
    colors: ['#10b981'],
    xaxis: {
        categories: props.by_subdealership.map(s => s.name),
        labels: { style: { fontSize: '10px', colors: '#94a3b8' }, rotate: -30 },
    },
    yaxis: { labels: { style: { fontSize: '10px', colors: '#94a3b8' }, formatter: (v: number) => 'S/' + v.toFixed(0) } },
    grid: { borderColor: '#f1f5f9' },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (v: number) => 'S/' + v.toFixed(2) } },
}));
const sdBarSeries = computed(() => [
    { name: 'Ingresos', data: props.by_subdealership.map(s => s.revenue) },
]);

/* ── Chart: visitor ratio ── */
const ratioOptions = computed(() => ({
    chart: { type: 'pie' as const, fontFamily: 'inherit' },
    labels: props.visitor_ratio.map(r => r.label),
    colors: ['#6366f1', '#a78bfa'],
    legend: { position: 'bottom' as const, fontSize: '11px' },
    dataLabels: { style: { fontSize: '11px' } },
    tooltip: { y: { formatter: (v: number) => v + ' registros' } },
}));
const ratioSeries = computed(() => props.visitor_ratio.map(r => r.count));

/* ── KPI growth badge ── */
const growthIcon = (v: number | null) => v !== null && v >= 0 ? ArrowUpRight : ArrowDownRight;

/* ── Satisfacción ── */
const satFaces = [
    { score: 1, icon: Angry, label: 'Muy insatisfecho', color: '#ef4444', bg: 'bg-red-50',    text: 'text-red-600' },
    { score: 2, icon: Frown, label: 'Insatisfecho',     color: '#fb923c', bg: 'bg-orange-50', text: 'text-orange-500' },
    { score: 3, icon: Meh,   label: 'Neutral',          color: '#facc15', bg: 'bg-yellow-50', text: 'text-yellow-500' },
    { score: 4, icon: Smile, label: 'Satisfecho',       color: '#4ade80', bg: 'bg-green-50',  text: 'text-green-500' },
    { score: 5, icon: Laugh, label: 'Muy satisfecho',   color: '#22c55e', bg: 'bg-green-50',  text: 'text-green-600' },
];

const satDistribution = computed(() => {
    const total = props.satisfaction.total_votes || 0;
    return satFaces.map(f => {
        const count = Number(props.satisfaction.by_score?.[String(f.score)] ?? 0);
        return { ...f, count, pct: total > 0 ? Math.round((count / total) * 100) : 0 };
    });
});

const faceForScore = (score: number | null) => {
    if (score === null) return satFaces[2];
    const idx = Math.min(4, Math.max(0, Math.round(score) - 1));
    return satFaces[idx];
};

const satTrendOptions = computed(() => ({
    chart: { type: 'area' as const, toolbar: { show: false }, sparkline: { enabled: true }, fontFamily: 'inherit' },
    stroke: { curve: 'smooth' as const, width: 2.5 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.02 } },
    colors: ['#22c55e'],
    yaxis: { min: 1, max: 5 },
    tooltip: {
        x: { show: false },
        y: { formatter: (v: number) => v.toFixed(2) + ' / 5' },
    },
    xaxis: { categories: props.satisfaction.trend.map(t => t.date) },
}));
const satTrendSeries = computed(() => [
    { name: 'Promedio', data: props.satisfaction.trend.map(t => t.avg_score) },
]);
</script>

<template>
    <Head title="Dashboard Gerencial" />
    <AppLayout>
        <div class="min-h-screen space-y-6 bg-slate-50 p-6">

            <!-- ── Header ── -->
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 shadow-lg shadow-emerald-500/30">
                        <BarChart3 class="h-6 w-6 text-white" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Dashboard Gerencial</h1>
                        <p class="text-sm text-slate-500">Inteligencia de negocio · visión consolidada por mina</p>
                    </div>
                </div>

                <!-- Filters -->
                <Card class="border-slate-200 shadow-sm">
                    <CardContent class="flex flex-wrap items-end gap-3 p-4">
                        <div class="space-y-1">
                            <Label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Desde</Label>
                            <Input v-model="startDate" type="date" class="h-8 w-36 text-xs" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Hasta</Label>
                            <Input v-model="endDate" type="date" class="h-8 w-36 text-xs" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Mina</Label>
                            <Select v-model="selectedMine">
                                <SelectTrigger class="h-8 w-40 text-xs">
                                    <SelectValue placeholder="Todas" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas las minas</SelectItem>
                                    <SelectItem v-for="mine in mines" :key="mine.id" :value="mine.id.toString()">
                                        {{ mine.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button @click="applyFilters" size="sm" class="h-8 gap-1.5 bg-emerald-600 hover:bg-emerald-700">
                            <Filter class="h-3.5 w-3.5" /> Aplicar
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <!-- ── KPI Cards ── -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <!-- Revenue -->
                <Card class="overflow-hidden border-none bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/20">
                    <CardContent class="p-5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-bold tracking-widest text-emerald-100 uppercase">Ingresos Totales</p>
                                <p class="mt-1.5 text-3xl font-black leading-none">{{ fmt(kpis.total_revenue) }}</p>
                            </div>
                            <div class="rounded-xl bg-white/20 p-2">
                                <DollarSign class="h-5 w-5" />
                            </div>
                        </div>
                        <div v-if="kpis.revenue_growth !== null" class="mt-3 flex items-center gap-1">
                            <component :is="growthIcon(kpis.revenue_growth)" class="h-4 w-4" :class="kpis.revenue_growth >= 0 ? 'text-emerald-200' : 'text-red-300'" />
                            <span class="text-xs font-bold" :class="kpis.revenue_growth >= 0 ? 'text-emerald-100' : 'text-red-200'">
                                {{ Math.abs(kpis.revenue_growth) }}% vs período anterior
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Sales -->
                <Card class="overflow-hidden border-none bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/20">
                    <CardContent class="p-5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-bold tracking-widest text-indigo-100 uppercase">Ventas Registradas</p>
                                <p class="mt-1.5 text-3xl font-black leading-none">{{ kpis.total_sales.toLocaleString() }}</p>
                            </div>
                            <div class="rounded-xl bg-white/20 p-2">
                                <ShoppingBag class="h-5 w-5" />
                            </div>
                        </div>
                        <div v-if="kpis.sales_growth !== null" class="mt-3 flex items-center gap-1">
                            <component :is="growthIcon(kpis.sales_growth)" class="h-4 w-4" :class="kpis.sales_growth >= 0 ? 'text-indigo-200' : 'text-red-300'" />
                            <span class="text-xs font-bold" :class="kpis.sales_growth >= 0 ? 'text-indigo-100' : 'text-red-200'">
                                {{ Math.abs(kpis.sales_growth) }}% vs período anterior
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Diners -->
                <Card class="overflow-hidden border-none bg-gradient-to-br from-blue-500 to-cyan-500 text-white shadow-lg shadow-blue-500/20">
                    <CardContent class="p-5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-bold tracking-widest text-blue-100 uppercase">Comensales Únicos</p>
                                <p class="mt-1.5 text-3xl font-black leading-none">{{ kpis.total_diners.toLocaleString() }}</p>
                            </div>
                            <div class="rounded-xl bg-white/20 p-2">
                                <Users class="h-5 w-5" />
                            </div>
                        </div>
                        <p class="mt-3 text-xs font-semibold text-blue-100">
                            + {{ kpis.total_visitors }} visitantes externos
                        </p>
                    </CardContent>
                </Card>

                <!-- Avg ticket -->
                <Card class="overflow-hidden border-none bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-lg shadow-amber-500/20">
                    <CardContent class="p-5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-bold tracking-widest text-amber-100 uppercase">Ticket Promedio</p>
                                <p class="mt-1.5 text-3xl font-black leading-none">{{ fmt(kpis.avg_ticket) }}</p>
                            </div>
                            <div class="rounded-xl bg-white/20 p-2">
                                <TrendingUp class="h-5 w-5" />
                            </div>
                        </div>
                        <p class="mt-3 text-xs font-semibold text-amber-100">por venta registrada</p>
                    </CardContent>
                </Card>
            </div>

            <!-- ── Row 2: Daily trend + Service breakdown ── -->
            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Daily trend (2/3) -->
                <Card class="border-slate-200 shadow-sm lg:col-span-2">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                            <TrendingUp class="h-4 w-4 text-emerald-500" />
                            Tendencia Diaria — Ingresos y Ventas
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <VueApexCharts v-if="daily_trend.length" type="area" height="220"
                            :options="lineOptions" :series="lineSeries" />
                        <div v-else class="flex h-48 items-center justify-center text-sm text-slate-400">
                            Sin datos para el período
                        </div>
                    </CardContent>
                </Card>

                <!-- Service donut (1/3) -->
                <Card class="border-slate-200 shadow-sm">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                            <Utensils class="h-4 w-4 text-indigo-500" />
                            Consumo por Servicio
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <VueApexCharts v-if="by_service_type.length" type="donut" height="220"
                            :options="donutOptions" :series="donutSeries" />
                        <!-- Service mini list -->
                        <div class="mt-2 space-y-2">
                            <div v-for="svc in by_service_type" :key="svc.label"
                                class="flex items-center gap-2 rounded-lg px-2 py-1.5"
                                :class="svcColor(svc.label).bg">
                                <component :is="svcIcon(svc.label)" class="h-3.5 w-3.5 shrink-0" :class="svcColor(svc.label).text" />
                                <span class="flex-1 text-[11px] font-semibold" :class="svcColor(svc.label).text">{{ svc.label }}</span>
                                <span class="text-[11px] font-black" :class="svcColor(svc.label).text">{{ svc.qty }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- ── Row 3: By mine + by cafe ── -->
            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Revenue by mine -->
                <Card class="border-slate-200 shadow-sm">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                            <Building2 class="h-4 w-4 text-indigo-500" />
                            Ingresos por Mina
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <VueApexCharts v-if="revenue_by_mine.length" type="bar" height="220"
                            :options="mineBarOptions" :series="mineBarSeries" />
                        <div v-else class="flex h-48 items-center justify-center text-sm text-slate-400">Sin datos</div>
                    </CardContent>
                </Card>

                <!-- Revenue by subdealership -->
                <Card class="border-slate-200 shadow-sm">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                            <ShoppingBag class="h-4 w-4 text-emerald-500" />
                            Ingresos por Subconcesionaria
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <VueApexCharts v-if="by_subdealership.length" type="bar" height="220"
                            :options="sdBarOptions" :series="sdBarSeries" />
                        <div v-else class="flex h-48 items-center justify-center text-sm text-slate-400">Sin datos</div>
                    </CardContent>
                </Card>
            </div>

            <!-- ── Row 4: Visitor ratio + Top cafes + Top diners ── -->
            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Visitor ratio -->
                <Card class="border-slate-200 shadow-sm">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                            <UserCheck class="h-4 w-4 text-violet-500" />
                            Tipo de Comensal
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <VueApexCharts v-if="ratioSeries.some(v => v > 0)" type="pie" height="200"
                            :options="ratioOptions" :series="ratioSeries" />
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div v-for="r in visitor_ratio" :key="r.label"
                                class="rounded-xl bg-slate-50 p-3 text-center">
                                <p class="text-xl font-black text-slate-800">{{ r.count }}</p>
                                <p class="text-[10px] font-semibold text-slate-500">{{ r.label }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Top cafes table -->
                <Card class="border-slate-200 shadow-sm">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                            <Coffee class="h-4 w-4 text-amber-500" />
                            Ranking de Cafeterías
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div v-for="(cafe, idx) in revenue_by_cafe" :key="cafe.cafe"
                                class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2.5">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-black"
                                    :class="idx === 0 ? 'bg-amber-400 text-white' : idx === 1 ? 'bg-slate-300 text-white' : idx === 2 ? 'bg-orange-300 text-white' : 'bg-slate-100 text-slate-500'">
                                    {{ idx + 1 }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[12px] font-bold text-slate-700">{{ cafe.cafe }}</p>
                                    <p class="text-[10px] text-slate-400">{{ cafe.sales }} ventas</p>
                                </div>
                                <span class="shrink-0 text-[12px] font-black text-emerald-600">{{ fmt(cafe.revenue) }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Top diners -->
                <Card class="border-slate-200 shadow-sm">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                            <Users class="h-4 w-4 text-blue-500" />
                            Top 10 Comensales
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="max-h-72 overflow-y-auto">
                        <div class="space-y-1.5">
                            <div v-for="(d, idx) in top_diners" :key="d.dni"
                                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2"
                                :class="idx % 2 === 0 ? 'bg-slate-50' : 'bg-white'">
                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-[10px] font-black text-indigo-600">
                                    {{ d.name.charAt(0) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[11px] font-bold text-slate-700 uppercase">{{ d.name }}</p>
                                    <p class="text-[9px] font-mono text-slate-400">{{ d.dni }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-[11px] font-black text-indigo-600">{{ d.visits }}x</p>
                                    <p class="text-[9px] font-semibold text-slate-400">{{ fmt(d.spent) }}</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- ── Row 5: Satisfacción de usuarios ── -->
            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Resumen -->
                <Card class="border-slate-200 shadow-sm">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                            <SmilePlus class="h-4 w-4 text-green-500" />
                            Satisfacción Promedio
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="satisfaction.total_votes > 0" class="flex flex-col items-center gap-2 py-2">
                            <span
                                class="flex h-16 w-16 items-center justify-center rounded-full"
                                :class="faceForScore(satisfaction.avg_score).bg"
                            >
                                <component
                                    :is="faceForScore(satisfaction.avg_score).icon"
                                    class="h-10 w-10"
                                    :class="faceForScore(satisfaction.avg_score).text"
                                    stroke-width="1.75"
                                />
                            </span>
                            <p class="text-3xl font-black text-slate-800">{{ satisfaction.avg_score?.toFixed(2) }}<span class="text-base font-bold text-slate-400"> / 5</span></p>
                            <p class="text-xs font-semibold text-slate-500">
                                {{ faceForScore(satisfaction.avg_score).label }} · {{ satisfaction.total_votes.toLocaleString() }} opiniones
                            </p>
                            <div v-if="satisfaction.trend.length > 1" class="mt-2 w-full">
                                <VueApexCharts type="area" height="60" :options="satTrendOptions" :series="satTrendSeries" />
                                <p class="mt-1 text-center text-[10px] text-slate-400">Tendencia diaria del período</p>
                            </div>
                        </div>
                        <div v-else class="flex h-40 items-center justify-center text-sm text-slate-400">
                            Sin opiniones en el período
                        </div>
                    </CardContent>
                </Card>

                <!-- Distribución -->
                <Card class="border-slate-200 shadow-sm">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                            <BarChart3 class="h-4 w-4 text-indigo-500" />
                            Distribución de Opiniones
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2.5">
                            <div v-for="face in satDistribution" :key="face.score" class="flex items-center gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full" :class="face.bg">
                                    <component :is="face.icon" class="h-5 w-5" :class="face.text" stroke-width="1.75" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between text-[11px]">
                                        <span class="font-semibold text-slate-600">{{ face.label }}</span>
                                        <span class="font-black text-slate-700">{{ face.count }} <span class="font-semibold text-slate-400">({{ face.pct }}%)</span></span>
                                    </div>
                                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full transition-all" :style="{ width: face.pct + '%', backgroundColor: face.color }"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Ranking por comedor -->
                <Card class="border-slate-200 shadow-sm">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-700">
                            <Coffee class="h-4 w-4 text-amber-500" />
                            Satisfacción por Comedor
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="max-h-72 overflow-y-auto">
                        <div v-if="satisfaction.by_cafe.length" class="space-y-2">
                            <div
                                v-for="row in satisfaction.by_cafe"
                                :key="row.cafe"
                                class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2.5"
                            >
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full" :class="faceForScore(row.avg_score).bg">
                                    <component :is="faceForScore(row.avg_score).icon" class="h-5 w-5" :class="faceForScore(row.avg_score).text" stroke-width="1.75" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[12px] font-bold text-slate-700">{{ row.cafe }}</p>
                                    <p class="text-[10px] text-slate-400">{{ row.votes }} opiniones</p>
                                </div>
                                <span class="shrink-0 text-[13px] font-black" :class="faceForScore(row.avg_score).text">
                                    {{ row.avg_score.toFixed(2) }}
                                </span>
                            </div>
                        </div>
                        <div v-else class="flex h-40 items-center justify-center text-sm text-slate-400">
                            Sin opiniones en el período
                        </div>
                    </CardContent>
                </Card>
            </div>

        </div>
    </AppLayout>
</template>
