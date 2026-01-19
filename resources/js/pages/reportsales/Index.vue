<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import Icon from '@/components/Icon.vue';
import SalesReportTable from './SalesReportTable.vue';
import { Badge } from '@/components/ui/badge';
import { ChartBar } from 'lucide-vue-next';

interface Cafe {
    id: number;
    name: string;
    unit?: {
        id: number;
        name: string;
    };
}

interface Filters {
    start_date: string;
    end_date: string;
    cafe_id: number | null;
}

interface Statistics {
    total_amount: number;
    total_sales: number;
    average_sale: number;
}

interface Sale {
    id: number;
    date: string;
    total: number;
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

const props = defineProps<{
    sales: PaginatedSales;
    cafes: Cafe[];
    filters: Filters;
    statistics: Statistics;
}>();

// Filtros locales
const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);
const selectedCafe = ref<string>(props.filters.cafe_id?.toString() || 'all');

// Aplicar filtros
const applyFilters = () => {
    router.get(route('reportsales.index'), {
        start_date: startDate.value,
        end_date: endDate.value,
        cafe_id: selectedCafe.value !== 'all' ? selectedCafe.value : null,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Resetear filtros
const resetFilters = () => {
    const today = new Date().toISOString().split('T')[0];
    startDate.value = today;
    endDate.value = today;
    selectedCafe.value = 'all';
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

// Exportar a Excel
const exportToExcel = () => {
    // TODO: Implementar exportación a Excel
    alert('Funcionalidad de exportación en desarrollo');
};
</script>

<template>
    <Head title="Reporte de ventas" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 shadow-lg shadow-emerald-500/30">
                        <ChartBar class="text-white"/>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Reporte de Ventas</h1>
                        <p class="text-sm text-slate-500">Consulta y gestiona las ventas registradas</p>
                    </div>
                </div>
                <Button @click="exportToExcel" variant="outline" class="gap-2">
                    <Icon name="download" size="16" />
                    Exportar a Excel
                </Button>
            </div>

            <!-- Statistics Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card class="border-slate-200 bg-gradient-to-br from-white to-slate-50">
                    <CardHeader class="pb-3">
                        <CardDescription class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <Icon name="currency-dollar" size="14" />
                            Total Vendido
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-black text-emerald-600">
                            S/{{ Number(statistics.total_amount).toFixed(2) }}
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-slate-200 bg-gradient-to-br from-white to-slate-50">
                    <CardHeader class="pb-3">
                        <CardDescription class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
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
                        <CardDescription class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <Icon name="chart-line" size="14" />
                            Promedio por Venta
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-black text-purple-600">
                            S/{{ Number(statistics.average_sale).toFixed(2) }}
                        </div>
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
                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="space-y-2">
                            <Label for="start-date" class="text-xs font-bold uppercase tracking-wider text-slate-600">
                                Fecha Inicio
                            </Label>
                            <Input
                                id="start-date"
                                v-model="startDate"
                                type="date"
                                class="border-slate-300"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="end-date" class="text-xs font-bold uppercase tracking-wider text-slate-600">
                                Fecha Fin
                            </Label>
                            <Input
                                id="end-date"
                                v-model="endDate"
                                type="date"
                                class="border-slate-300"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="cafe" class="text-xs font-bold uppercase tracking-wider text-slate-600">
                                Cafetería
                            </Label>
                            <Select v-model="selectedCafe">
                                <SelectTrigger id="cafe" class="border-slate-300">
                                    <SelectValue placeholder="Todas las cafeterías" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas las cafeterías</SelectItem>
                                    <SelectItem
                                        v-for="cafe in cafes"
                                        :key="cafe.id"
                                        :value="cafe.id.toString()"
                                    >
                                        {{ cafe.name }} {{ cafe.unit ? `- ${cafe.unit.name}` : '' }}
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
                        <Badge variant="secondary" class="font-bold">
                            {{ sales.total }} resultados
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <SalesReportTable
                        :sales="sales.data"
                        :paginate-data="sales"
                        @delete-sale="deleteSale"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>