<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import { AlertTriangle, ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

interface ProviderPrice {
    provider_id: number;
    provider_name: string;
    price: number;
}

interface ReportRow {
    id: number;
    name: string;
    category: string;
    unit: string;
    by_cafe: Record<string, number>;
    total_kg: number;
    stock_kg: number;
    providers: ProviderPrice[];
    best_price: number | null;
    subtotal: number | null;
}

interface Category {
    name: string;
    rows: ReportRow[];
}

interface ProgramSummary {
    id: number;
    cafe: string | null;
    chain: string | null;
    start_date: string;
    end_date: string;
}

interface Destination {
    id: number;
    name: string;
}

interface Props {
    programs: ProgramSummary[];
    destinations: Destination[];
    level: { id: number; name: string };
    city: { id: number; name: string };
    categories: Category[];
    max_providers: number;
    grand_total: number;
    missing_price_count: number;
    generated_at: string;
}

const props = defineProps<Props>();

const providerColumns = computed(() => Array.from({ length: props.max_providers }, (_, i) => i));
const totalColumns = computed(() => 6 + props.destinations.length + props.max_providers);
const colspanBeforeSubtotal = computed(() => totalColumns.value - 1);

const categoryTotal = (rows: ReportRow[]) => rows.reduce((sum, r) => sum + (r.subtotal ?? 0), 0);

const formatCurrency = (value: number | null) =>
    value === null ? '—' : `S/. ${value.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const formatQty = (value: number) => value.toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: 3 });

const printPage = () => window.print();
</script>

<template>
    <Head title="Reporte de Compras Semanales" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-4 sm:p-6 print:p-0">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between print:hidden">
                <div class="flex flex-col gap-1">
                    <Link :href="route('planning.index')" class="text-muted-foreground inline-flex items-center gap-1 text-sm hover:underline">
                        <ArrowLeft class="h-3.5 w-3.5" /> Volver a Planificación
                    </Link>
                    <h1 class="text-2xl font-bold tracking-tight">Reporte de Compras Semanales</h1>
                    <p class="text-muted-foreground text-sm">
                        Nivel: <span class="font-semibold text-slate-700">{{ level.name }}</span> · Ciudad de precios:
                        <span class="font-semibold text-slate-700">{{ city.name }}</span> · Generado
                        {{ dayjs(generated_at).format('DD/MM/YYYY HH:mm') }}
                    </p>
                </div>
                <Button variant="outline" class="gap-2 self-start" @click="printPage"> <Printer class="h-4 w-4" /> Imprimir </Button>
            </div>

            <div
                v-if="missing_price_count > 0"
                class="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 print:hidden"
            >
                <AlertTriangle class="h-4 w-4 shrink-0" />
                {{ missing_price_count }} insumo{{ missing_price_count === 1 ? '' : 's' }} sin precio registrado en {{ city.name }} — excluido{{
                    missing_price_count === 1 ? '' : 's'
                }}
                del total.
            </div>

            <Card class="rounded-2xl border-none shadow-sm print:hidden">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Programaciones incluidas ({{ programs.length }})</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-2">
                    <Badge v-for="p in programs" :key="p.id" variant="outline" class="border-slate-200 bg-slate-50 font-normal text-slate-600">
                        {{ p.chain || p.cafe }} · {{ dayjs(p.start_date).format('DD/MM') }} – {{ dayjs(p.end_date).format('DD/MM') }}
                    </Badge>
                </CardContent>
            </Card>

            <Card class="overflow-hidden rounded-2xl border-none shadow-sm">
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow class="bg-slate-50/80">
                                    <TableHead class="sticky left-0 z-10 min-w-[220px] bg-slate-50">Insumo</TableHead>
                                    <TableHead class="text-center">Unidad</TableHead>
                                    <TableHead v-for="d in destinations" :key="d.id" class="text-center whitespace-nowrap">{{ d.name }}</TableHead>
                                    <TableHead class="text-center font-bold">Total</TableHead>
                                    <TableHead class="text-center">Stock</TableHead>
                                    <TableHead v-for="i in providerColumns" :key="i" class="text-right whitespace-nowrap"
                                        >Proveedor {{ i + 1 }}</TableHead
                                    >
                                    <TableHead class="text-right">Mejor Precio</TableHead>
                                    <TableHead class="text-right">Subtotal</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-for="cat in categories" :key="cat.name">
                                    <TableRow class="bg-indigo-50/40">
                                        <TableCell :colspan="totalColumns" class="py-2 text-xs font-black tracking-widest text-indigo-700 uppercase">
                                            {{ cat.name }}
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-for="row in cat.rows" :key="row.id" class="hover:bg-slate-50/50">
                                        <TableCell class="sticky left-0 z-10 bg-white font-medium text-slate-800">{{ row.name }}</TableCell>
                                        <TableCell class="text-center text-xs text-slate-500">{{ row.unit }}</TableCell>
                                        <TableCell v-for="d in destinations" :key="d.id" class="text-center text-sm">
                                            {{ row.by_cafe[d.id] ? formatQty(row.by_cafe[d.id]) : '—' }}
                                        </TableCell>
                                        <TableCell class="text-center text-sm font-bold text-slate-800">{{ formatQty(row.total_kg) }}</TableCell>
                                        <TableCell class="text-center text-sm text-slate-500">{{
                                            row.stock_kg ? formatQty(row.stock_kg) : '—'
                                        }}</TableCell>
                                        <TableCell v-for="i in providerColumns" :key="i" class="text-right text-xs">
                                            <template v-if="row.providers[i]">
                                                <div :class="i === 0 ? 'font-bold text-emerald-700' : 'text-slate-500'">
                                                    {{ formatCurrency(row.providers[i].price) }}
                                                </div>
                                                <div class="max-w-[120px] truncate text-[10px] text-slate-400">
                                                    {{ row.providers[i].provider_name }}
                                                </div>
                                            </template>
                                            <span v-else class="text-slate-300">—</span>
                                        </TableCell>
                                        <TableCell class="text-right text-sm font-bold text-indigo-600">{{ formatCurrency(row.best_price) }}</TableCell>
                                        <TableCell class="text-right text-sm font-black text-indigo-700">{{ formatCurrency(row.subtotal) }}</TableCell>
                                    </TableRow>
                                    <TableRow class="bg-slate-50/50">
                                        <TableCell
                                            :colspan="colspanBeforeSubtotal"
                                            class="text-right text-xs font-bold text-slate-500 uppercase"
                                        >
                                            Subtotal {{ cat.name }}
                                        </TableCell>
                                        <TableCell class="text-right text-sm font-black text-slate-700">{{
                                            formatCurrency(categoryTotal(cat.rows))
                                        }}</TableCell>
                                    </TableRow>
                                </template>
                                <TableRow v-if="categories.length === 0">
                                    <TableCell :colspan="totalColumns" class="py-10 text-center text-sm text-slate-400">
                                        No se encontraron insumos con receta en el nivel seleccionado para estas programaciones.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <div class="w-full max-w-xs rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black tracking-widest text-indigo-700 uppercase">Total General</span>
                        <span class="font-mono text-lg font-black text-indigo-700">{{ formatCurrency(grand_total) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
