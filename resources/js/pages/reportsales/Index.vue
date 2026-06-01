<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ChartBar } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import SalesReportTable from './SalesReportTable.vue';

interface Cafe {
    id: number;
    name: string;
    unit?: {
        id: number;
        name: string;
    };
}

interface Subdealership {
    id: number;
    name: string;
    ruc: string;
}

interface Filters {
    start_date: string;
    end_date: string;
    cafe_id: number | null;
    subdealership_id: number | null;
}

interface Statistics {
    total_amount: number;
    total_sales: number;
    average_sale: number;
}

interface Sale {
    id: number;
    date: string;
    created_at: string;
    total: number;
    is_visitor: boolean;
    cafe: Cafe;
    tickets: any[];
}

interface PaginatedSales {
    data: Sale[];
    links: any[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const businessName = computed(() => (usePage<any>().props.auth.user as any)?.business?.name ?? '');

const props = defineProps<{
    sales: PaginatedSales;
    cafes: Cafe[];
    subdealerships: Subdealership[];
    filters: Filters;
    statistics: Statistics;
}>();

// Filtros locales
const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);
const selectedCafe = ref<string>(props.filters.cafe_id?.toString() || 'all');
const selectedSubdealership = ref<string>(props.filters.subdealership_id?.toString() || 'all');

// Aplicar filtros
const applyFilters = () => {
    router.get(
        route('reportsales.index'),
        {
            start_date: startDate.value,
            end_date: endDate.value,
            cafe_id: selectedCafe.value !== 'all' ? selectedCafe.value : null,
            subdealership_id: selectedSubdealership.value !== 'all' ? selectedSubdealership.value : null,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

// Resetear filtros
const resetFilters = () => {
    const today = new Date().toISOString().split('T')[0];
    startDate.value = today;
    endDate.value = today;
    selectedCafe.value = 'all';
    selectedSubdealership.value = 'all';
    applyFilters();
};

// Eliminar venta
const deleteSale = (saleId: number) => {
    if (confirm('¿Estás seguro de que deseas eliminar esta venta? Esta acción no se puede deshacer.')) {
        router.delete(route('reportsales.destroy', saleId), {
            preserveScroll: true,
        });
    }
};

const buildParams = () => {
    const params = new URLSearchParams();
    params.set('start_date', startDate.value);
    params.set('end_date',   endDate.value);
    if (selectedCafe.value !== 'all')          params.set('cafe_id',          selectedCafe.value);
    if (selectedSubdealership.value !== 'all') params.set('subdealership_id', selectedSubdealership.value);
    return params.toString();
};

// Reporte resumen (por subdealership, columnas D/A/C agrupado)
const exportToExcel = () => {
    window.location.href = route('reportsales.export') + '?' + buildParams();
};

// Reporte valorización (matriz diaria: VLZ / SISTEMA / VISITAS / REFRIGERIOS)
const exportValorizacion = () => {
    window.location.href = route('reportsales.export-vlz') + '?' + buildParams();
};

// Detalle de consumo — una fila por servicio consumido
const exportDetail = () => {
    window.location.href = route('reportsales.export-detail') + '?' + buildParams();
};
</script>

<template>
    <Head title="Reporte de ventas" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 shadow-lg shadow-emerald-500/30"
                    >
                        <ChartBar class="text-white" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                            Reporte de Ventas {{ businessName ? ` - ${businessName}` : '' }}
                        </h1>
                        <p class="text-sm text-slate-500">Consulta y gestiona las ventas registradas</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button @click="exportToExcel" variant="outline" class="gap-2">
                        <Icon name="download" size="16" />
                        Resumen por empresa
                    </Button>
                    <Button @click="exportValorizacion" class="gap-2 bg-emerald-600 hover:bg-emerald-700">
                        <Icon name="table-2" size="16" />
                        Valorización
                    </Button>
                    <Button @click="exportDetail" variant="outline" class="gap-2 border-violet-300 text-violet-700 hover:bg-violet-50 hover:border-violet-400">
                        <Icon name="list-details" size="16" />
                        Detalle de consumo
                    </Button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card class="border-slate-200 bg-gradient-to-br from-white to-slate-50">
                    <CardHeader class="pb-3">
                        <CardDescription class="flex items-center gap-2 text-xs font-semibold tracking-wider text-slate-500 uppercase">
                            <Icon name="currency-dollar" size="14" />
                            Total sin IGV
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-black text-emerald-600">S/{{ Number(statistics.total_amount).toFixed(2) }}</div>
                    </CardContent>
                </Card>

                <Card class="border-slate-200 bg-gradient-to-br from-white to-slate-50">
                    <CardHeader class="pb-3">
                        <CardDescription class="flex items-center gap-2 text-xs font-semibold tracking-wider text-slate-500 uppercase">
                            <Icon name="shopping-cart" size="14" />
                            Total de Ventas
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-black text-blue-600">
                            {{ statistics.total_sales }}
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-slate-200 bg-gradient-to-br from-white to-slate-50">
                    <CardHeader class="pb-3">
                        <CardDescription class="flex items-center gap-2 text-xs font-semibold tracking-wider text-slate-500 uppercase">
                            <Icon name="chart-line" size="14" />
                            Promedio por Venta
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-black text-purple-600">S/{{ Number(statistics.average_sale).toFixed(2) }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <Card class="border-slate-200 shadow-sm">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Icon name="filter" size="18" />
                        Filtros de Búsqueda
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                        <div class="space-y-2">
                            <Label for="start-date" class="text-xs font-bold tracking-wider text-slate-600 uppercase">Fecha Inicio</Label>
                            <Input id="start-date" v-model="startDate" type="date" class="border-slate-300" />
                        </div>

                        <div class="space-y-2">
                            <Label for="end-date" class="text-xs font-bold tracking-wider text-slate-600 uppercase">Fecha Fin</Label>
                            <Input id="end-date" v-model="endDate" type="date" class="border-slate-300" />
                        </div>

                        <div class="space-y-2">
                            <Label for="cafe" class="text-xs font-bold tracking-wider text-slate-600 uppercase">Cafetería</Label>
                            <Select v-model="selectedCafe">
                                <SelectTrigger id="cafe" class="border-slate-300">
                                    <SelectValue placeholder="Todas las cafeterías" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas las cafeterías</SelectItem>
                                    <SelectItem v-for="cafe in cafes" :key="cafe.id" :value="cafe.id.toString()">
                                        {{ cafe.name }} {{ cafe.unit ? `- ${cafe.unit.name}` : '' }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label for="subdealership" class="text-xs font-bold tracking-wider text-slate-600 uppercase">Subconcesionaria</Label>
                            <Select v-model="selectedSubdealership">
                                <SelectTrigger id="subdealership" class="border-slate-300">
                                    <SelectValue placeholder="Todas" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas</SelectItem>
                                    <SelectItem v-for="sd in subdealerships" :key="sd.id" :value="sd.id.toString()">
                                        {{ sd.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex items-end gap-2">
                            <Button @click="applyFilters" class="flex-1 gap-2">
                                <Icon name="search" size="16" />
                                Buscar
                            </Button>
                            <Button @click="resetFilters" variant="outline" size="icon">
                                <Icon name="x" size="16" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Sales Table -->
            <Card class="border-slate-200 shadow-sm">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Icon name="table" size="18" />
                            Ventas Registradas
                        </CardTitle>
                        <Badge variant="secondary" class="font-bold"> {{ sales.total }} resultados </Badge>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <SalesReportTable :sales="sales.data" :paginate-data="sales" @delete-sale="deleteSale" />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
