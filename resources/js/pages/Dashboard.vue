<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    CalendarClock,
    CheckCircle2,
    FileWarning,
    LayoutGrid,
    ShoppingCart,
    TrendingUp,
    Users,
    Utensils,
    House,
    Receipt,
    BookOpen,
    Building2,
    HandHelping,
    ClipboardList,
} from 'lucide-vue-next';
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inicio', href: '/dashboard' }];

// ── Types ──────────────────────────────────────────────────────────────────
interface SalesStats   { todayCount: number; todayAmount: number }
interface SalesChartPt { date: string; count: number; amount: number }
interface StaffStats   { total: number; active: number; expiring: number; expired: number }
interface DocAlert     { staff_name: string; file_type: string; expiration_date: string; days_left: number }

const props = defineProps<{
    salesStats:    SalesStats   | null;
    salesChart:    SalesChartPt[] | null;
    staffStats:    StaffStats   | null;
    docAlerts:     DocAlert[]   | null;
    totalServices: number;
}>();

// ── Auth context ───────────────────────────────────────────────────────────
const page        = usePage();
const user        = computed(() => (page.props.auth as any)?.user);
const permissions = computed(() => (page.props.auth as any)?.permissions ?? []);
const roles       = computed(() => (page.props.auth as any)?.roles ?? []);

// ── Greeting ───────────────────────────────────────────────────────────────
const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Buenos días';
    if (h < 19) return 'Buenas tardes';
    return 'Buenas noches';
});

const todayLabel = computed(() =>
    new Date().toLocaleDateString('es-PE', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    }),
);

// ── Quick-access modules (from user's permissions) ──────────────────────────
const iconMap: Record<string, any> = {
    House, Users, Utensils, ClipboardList, Building2,
    HandHelping, LayoutGrid, ShoppingCart, Receipt, BookOpen,
};

const quickLinks = computed(() =>
    permissions.value
        .filter((p: any) => p.route_name && p.sidebar_name)
        .map((p: any) => ({
            title: p.sidebar_name,
            href:  '/' + p.route_name,
            icon:  iconMap[p.icon_class] ?? LayoutGrid,
        })),
);

// ── Visibility flags ───────────────────────────────────────────────────────
const hasSalesSection     = computed(() => props.salesStats !== null);
const hasHeadcountSection = computed(() => props.staffStats !== null);
const hasAnySection       = computed(() => hasSalesSection.value || hasHeadcountSection.value);

// ── KPI cards array (built only from available data) ──────────────────────
const kpiCards = computed(() => {
    const cards = [];

    if (hasHeadcountSection.value) {
        cards.push({
            key:       'staff',
            label:     'Personal total',
            value:     props.staffStats!.total.toLocaleString('es-PE'),
            sub:       `${props.staffStats!.active.toLocaleString('es-PE')} activos`,
            subColor:  'text-green-600',
            subIcon:   CheckCircle2,
            iconBg:    'bg-blue-50 dark:bg-blue-900/30',
            iconColor: 'text-blue-600 dark:text-blue-400',
            icon:      Users,
        });
        cards.push({
            key:       'docs',
            label:     'Docs. por vencer',
            value:     props.staffStats!.expiring.toLocaleString('es-PE'),
            sub:       props.staffStats!.expired > 0
                ? `${props.staffStats!.expired} ya vencidos`
                : 'Próximos 30 días',
            subColor:  props.staffStats!.expired > 0 ? 'text-red-500 font-medium' : 'text-gray-500',
            subIcon:   props.staffStats!.expired > 0 ? AlertTriangle : null,
            iconBg:    'bg-amber-50 dark:bg-amber-900/30',
            iconColor: 'text-amber-600 dark:text-amber-400',
            icon:      CalendarClock,
        });
    }

    if (hasSalesSection.value) {
        cards.push({
            key:       'sales',
            label:     'Ventas hoy',
            value:     props.salesStats!.todayCount.toLocaleString('es-PE'),
            sub:       `S/ ${props.salesStats!.todayAmount.toLocaleString('es-PE', { minimumFractionDigits: 2 })}`,
            subColor:  'text-foreground/80 font-semibold',
            subIcon:   null,
            iconBg:    'bg-red-50 dark:bg-red-900/30',
            iconColor: 'text-red-600 dark:text-red-400',
            icon:      ShoppingCart,
        });
    }

    cards.push({
        key:       'services',
        label:     'Servicios',
        value:     props.totalServices.toLocaleString('es-PE'),
        sub:       'Tipos de servicio',
        subColor:  'text-gray-500',
        subIcon:   null,
        iconBg:    'bg-purple-50 dark:bg-purple-900/30',
        iconColor: 'text-purple-600 dark:text-purple-400',
        icon:      Utensils,
    });

    return cards;
});

// ── Sales chart config ─────────────────────────────────────────────────────
const chartLabels = computed(() =>
    (props.salesChart ?? []).map(d =>
        new Date(d.date + 'T00:00:00').toLocaleDateString('es-PE', { weekday: 'short' }),
    ),
);

const chartSeries = computed(() => [{
    name: 'Ventas',
    data: (props.salesChart ?? []).map(d => d.count),
}]);

const chartOptions = computed(() => ({
    chart: {
        type:     'area' as const,
        height:   200,
        toolbar:  { show: false },
        fontFamily: 'inherit',
    },
    dataLabels: { enabled: false },
    stroke:     { curve: 'smooth' as const, width: 2.5 },
    colors:     ['#dc2626'],
    fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.01 },
    },
    xaxis: {
        categories: chartLabels.value,
        labels:     { style: { fontSize: '11px', colors: '#9ca3af' } },
        axisBorder: { show: false },
        axisTicks:  { show: false },
    },
    yaxis: {
        labels: {
            style:     { fontSize: '11px', colors: '#9ca3af' },
            formatter: (v: number) => v.toFixed(0),
        },
    },
    grid: {
        borderColor:    '#f3f4f6',
        strokeDashArray: 4,
        yaxis: { lines: { show: true } },
        xaxis: { lines: { show: false } },
    },
    tooltip: {
        y: {
            formatter: (_val: number, opts: any) => {
                const pt = (props.salesChart ?? [])[opts.dataPointIndex];
                return `${_val} ventas · S/ ${pt?.amount?.toLocaleString('es-PE', { minimumFractionDigits: 2 })}`;
            },
        },
    },
}));

const weekTotals = computed(() => ({
    count:  (props.salesChart ?? []).reduce((s, d) => s + d.count, 0),
    amount: (props.salesChart ?? []).reduce((s, d) => s + d.amount, 0),
}));

// ── Doc-alert helpers ──────────────────────────────────────────────────────
function alertBadgeClass(days: number) {
    if (days <= 7)  return 'bg-red-50 text-red-700 border-red-200';
    if (days <= 15) return 'bg-amber-50 text-amber-700 border-amber-200';
    return 'bg-yellow-50 text-yellow-700 border-yellow-200';
}
function alertDotClass(days: number) {
    if (days <= 7)  return 'bg-red-500';
    if (days <= 15) return 'bg-amber-500';
    return 'bg-yellow-400';
}
function fmtDate(d: string) {
    return new Date(d + 'T00:00:00').toLocaleDateString('es-PE', {
        day: '2-digit', month: 'short', year: 'numeric',
    });
}
</script>

<template>
    <Head title="Inicio" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 pb-8">

            <!-- ── HERO ──────────────────────────────────────────────────── -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-red-700 via-red-600 to-rose-500 px-7 py-8 shadow-lg">
                <div class="pointer-events-none absolute -top-16 -right-16 h-56 w-56 rounded-full bg-white/10 blur-3xl" />
                <div class="pointer-events-none absolute -bottom-12 left-1/3 h-40 w-40 rounded-full bg-rose-300/20 blur-2xl" />

                <div class="relative z-10 flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                    <!-- Greeting -->
                    <div>
                        <p class="text-sm font-medium capitalize text-red-200">{{ todayLabel }}</p>
                        <h1 class="mt-1 text-2xl font-bold text-white sm:text-3xl">
                            {{ greeting }}, {{ user?.name?.split(' ')[0] ?? 'Usuario' }}
                        </h1>
                        <div class="mt-2.5 flex flex-wrap items-center gap-2">
                            <span
                                v-for="role in roles" :key="role"
                                class="rounded-full bg-white/20 px-3 py-0.5 text-xs font-semibold text-white"
                            >
                                {{ role }}
                            </span>
                            <span
                                v-if="user?.business?.name"
                                class="rounded-full bg-white/15 px-3 py-0.5 text-xs font-medium text-red-100"
                            >
                                {{ user.business.name }}
                            </span>
                            <span
                                v-if="user?.mine?.name"
                                class="rounded-full bg-white/15 px-3 py-0.5 text-xs font-medium text-red-100"
                            >
                                {{ user.mine.name }}
                            </span>
                        </div>
                    </div>

                    <!-- Hero snapshot — only when user has sales/headcount data -->
                    <div v-if="hasAnySection" class="flex flex-wrap gap-4 md:gap-6">
                        <template v-if="hasHeadcountSection">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-white">{{ staffStats!.active.toLocaleString('es-PE') }}</p>
                                <p class="text-xs font-medium text-red-200">Personal activo</p>
                            </div>
                            <div class="hidden h-10 w-px self-center bg-white/20 md:block" />
                        </template>
                        <template v-if="hasSalesSection">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-white">{{ salesStats!.todayCount.toLocaleString('es-PE') }}</p>
                                <p class="text-xs font-medium text-red-200">Ventas hoy</p>
                            </div>
                            <div class="hidden h-10 w-px self-center bg-white/20 md:block" />
                        </template>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-white">{{ totalServices.toLocaleString('es-PE') }}</p>
                            <p class="text-xs font-medium text-red-200">Servicios</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── KPI CARDS ─────────────────────────────────────────────── -->
            <div
                :class="[
                    'grid gap-4',
                    kpiCards.length === 1 ? 'grid-cols-1 max-w-xs' :
                    kpiCards.length === 2 ? 'grid-cols-2 lg:grid-cols-2' :
                    kpiCards.length === 3 ? 'grid-cols-2 lg:grid-cols-3' :
                                            'grid-cols-2 lg:grid-cols-4',
                ]"
            >
                <div
                    v-for="card in kpiCards" :key="card.key"
                    class="bg-card rounded-xl border p-5 shadow-sm transition-shadow hover:shadow-md"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground text-xs font-semibold uppercase tracking-wider">
                            {{ card.label }}
                        </span>
                        <div :class="['rounded-lg p-2', card.iconBg]">
                            <component :is="card.icon" :class="['h-4 w-4', card.iconColor]" />
                        </div>
                    </div>
                    <p class="mt-3 text-3xl font-bold">{{ card.value }}</p>
                    <p class="mt-1 flex items-center gap-1 text-xs dark:text-gray-400" :class="card.subColor">
                        <component :is="card.subIcon" v-if="card.subIcon" class="h-3 w-3" />
                        {{ card.sub }}
                    </p>
                </div>
            </div>

            <!-- ── CHART + QUICK ACCESS ──────────────────────────────────── -->
            <div class="grid grid-cols-1 gap-4" :class="hasSalesSection ? 'lg:grid-cols-5' : ''">

                <!-- Sales chart (only for sales roles) -->
                <div
                    v-if="hasSalesSection && salesChart"
                    class="bg-card lg:col-span-3 rounded-xl border p-5 shadow-sm"
                >
                    <div class="mb-3 flex items-start justify-between">
                        <div>
                            <h2 class="font-semibold">Ventas — últimos 7 días</h2>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ weekTotals.count.toLocaleString('es-PE') }} transacciones ·
                                <span class="font-semibold text-foreground/80">
                                    S/ {{ weekTotals.amount.toLocaleString('es-PE', { minimumFractionDigits: 2 }) }}
                                </span>
                            </p>
                        </div>
                        <div class="flex items-center gap-1 rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 dark:bg-red-900/30 dark:text-red-400">
                            <TrendingUp class="h-3.5 w-3.5" />
                            <span>Tendencia</span>
                        </div>
                    </div>
                    <VueApexCharts type="area" height="200" :options="chartOptions" :series="chartSeries" />
                </div>

                <!-- Quick access modules -->
                <div
                    :class="[
                        'bg-card rounded-xl border p-5 shadow-sm',
                        hasSalesSection ? 'lg:col-span-2' : 'lg:col-span-full',
                    ]"
                >
                    <h2 class="mb-4 font-semibold">Mis módulos</h2>

                    <div v-if="quickLinks.length > 0" class="grid gap-2.5" :class="hasSalesSection ? 'grid-cols-2' : 'grid-cols-3 sm:grid-cols-4 lg:grid-cols-5'">
                        <a
                            v-for="link in quickLinks" :key="link.href"
                            :href="link.href"
                            class="group flex flex-col items-center gap-2 rounded-xl border p-3 text-center transition-all hover:border-red-200 hover:bg-red-50 dark:hover:border-red-800 dark:hover:bg-red-900/20"
                        >
                            <div class="bg-muted text-muted-foreground flex h-10 w-10 items-center justify-center rounded-lg transition-colors group-hover:bg-red-600 group-hover:text-white">
                                <component :is="link.icon" class="h-5 w-5" />
                            </div>
                            <span class="text-xs font-medium leading-tight text-foreground/80">
                                {{ link.title }}
                            </span>
                        </a>
                    </div>

                    <!-- Empty state for operational staff with no permissions yet -->
                    <div v-else class="flex flex-col items-center justify-center gap-3 py-8 text-center text-gray-400">
                        <LayoutGrid class="h-10 w-10 opacity-30" />
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Sin módulos asignados</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Contacta al administrador para solicitar acceso a los módulos correspondientes a tu rol.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── DOC ALERTS (only for headcount roles) ──────────────────── -->
            <div
                v-if="hasHeadcountSection && docAlerts && docAlerts.length > 0"
                class="bg-card rounded-xl border border-amber-100 shadow-sm dark:border-amber-900/30"
            >
                <div class="flex items-center justify-between border-b px-5 py-4">
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-lg bg-amber-100 p-1.5 dark:bg-amber-900/40">
                            <FileWarning class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div>
                            <h2 class="font-semibold">Documentos próximos a vencer</h2>
                            <p class="text-xs text-muted-foreground">
                                Próximos 30 días · {{ staffStats!.expiring }} documento(s)
                            </p>
                        </div>
                    </div>
                    <a
                        href="/headcount"
                        class="flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                    >
                        Ver personal <ArrowUpRight class="h-3.5 w-3.5" />
                    </a>
                </div>

                <div class="divide-y divide-gray-50 dark:divide-gray-700">
                    <div
                        v-for="(alert, i) in docAlerts" :key="i"
                        class="flex items-center justify-between px-5 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/40"
                    >
                        <div class="flex items-center gap-3">
                            <span :class="['h-2 w-2 flex-shrink-0 rounded-full', alertDotClass(alert.days_left)]" />
                            <div>
                                <p class="text-sm font-semibold">{{ alert.staff_name }}</p>
                                <p class="text-xs text-muted-foreground">{{ alert.file_type }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-medium text-foreground/80">{{ fmtDate(alert.expiration_date) }}</p>
                            <span :class="['inline-block rounded-full border px-2 py-0.5 text-[10px] font-bold', alertBadgeClass(alert.days_left)]">
                                {{ alert.days_left === 0 ? 'Vence hoy' : `${alert.days_left}d restantes` }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── HEADCOUNT: no alerts state ─────────────────────────────── -->
            <div
                v-else-if="hasHeadcountSection && docAlerts !== null && docAlerts.length === 0"
                class="flex items-center gap-3 rounded-xl border border-green-100 bg-green-50 px-5 py-4 dark:border-green-900/30 dark:bg-green-900/10"
            >
                <CheckCircle2 class="h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                <p class="text-sm font-medium text-green-700 dark:text-green-400">
                    Todos los documentos del personal están al día para los próximos 30 días.
                </p>
            </div>

        </div>
    </AppLayout>
</template>
