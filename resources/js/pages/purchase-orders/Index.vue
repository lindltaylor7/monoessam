<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { PurchaseOrder, WeeklyProgram } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import 'dayjs/locale/es';
import { ChevronDown, FileSpreadsheet, FileText, Package, ShoppingCart, TrendingUp } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';

dayjs.locale('es');

interface Props {
    orders: PurchaseOrder[];
    levels?: { id: number; name: string }[];
    cities?: { id: number; name: string }[];
}

const props = defineProps<Props>();

const search = ref('');

// Programaciones consolidadas en el quebrado; las órdenes previas al pivote solo traen `program`.
const orderPrograms = (order: PurchaseOrder): WeeklyProgram[] => {
    if (order.programs?.length) return order.programs;
    return order.program ? [order.program] : [];
};

const orderProgramIds = (order: PurchaseOrder): number[] => {
    const ids = orderPrograms(order).map((p) => p.id);
    return ids.length ? ids : [order.weekly_program_id];
};

/** Periodo cubierto por todas las programaciones del quebrado. */
const orderPeriod = (order: PurchaseOrder): { start: string; end: string } | null => {
    const programs = orderPrograms(order).filter((p) => p.start_date && p.end_date);
    if (!programs.length) return null;
    const start = programs.map((p) => p.start_date).sort()[0];
    const end = programs
        .map((p) => p.end_date)
        .sort()
        .at(-1)!;
    return { start, end };
};
const filterStatus = ref('todos');

// Filtros Mina → Unidad → Comedor (mismo bloque que Programaciones en Planificación). Las opciones salen
// de los comedores que aparecen en las órdenes, así no se ofrecen ubicaciones sin órdenes.
const filters = ref({ mine: '', unit: '', cafe: '', createdFrom: '', createdTo: '' });

type Option = { id: number; name: string };
const byName = (a: Option, b: Option) => a.name.localeCompare(b.name);
const uniqueOptions = (items: (Option | undefined | null)[]) =>
    [...new Map(items.filter((i): i is Option => !!i?.id).map((i) => [i.id, { id: i.id, name: i.name }])).values()].sort(byName);

const orderCafes = computed(() => props.orders.flatMap((order) => orderPrograms(order).map((p) => p.cafe).filter(Boolean)) as any[]);

const mineOptions = computed(() => uniqueOptions(orderCafes.value.map((c) => c.unit?.mine)));
const unitOptions = computed(() =>
    uniqueOptions(orderCafes.value.filter((c) => !filters.value.mine || String(c.unit?.mine?.id) === filters.value.mine).map((c) => c.unit)),
);
const cafeOptions = computed(() =>
    uniqueOptions(
        orderCafes.value.filter(
            (c) =>
                (!filters.value.mine || String(c.unit?.mine?.id) === filters.value.mine) &&
                (!filters.value.unit || String(c.unit?.id) === filters.value.unit),
        ),
    ),
);

// Al cambiar la mina/unidad, se limpian los filtros hijos que ya no pertenecen a ella.
watch(
    () => filters.value.mine,
    () => {
        if (!unitOptions.value.some((u) => String(u.id) === filters.value.unit)) filters.value.unit = '';
    },
);
watch(
    () => filters.value.unit,
    () => {
        if (!cafeOptions.value.some((c) => String(c.id) === filters.value.cafe)) filters.value.cafe = '';
    },
);

const hasFilters = computed(() => !!search.value || filterStatus.value !== 'todos' || Object.values(filters.value).some(Boolean));
const clearFilters = () => {
    search.value = '';
    filterStatus.value = 'todos';
    filters.value = { mine: '', unit: '', cafe: '', createdFrom: '', createdTo: '' };
};

const filteredOrders = computed(() => {
    const term = search.value.toLowerCase();
    const f = filters.value;
    return props.orders.filter((order) => {
        const programs = orderPrograms(order);
        const matchesSearch =
            !term ||
            String(order.id).includes(term) ||
            programs.some((p) => [p.cafe?.name, p.cafe?.unit?.name, p.cafe?.unit?.mine?.name].some((name) => name?.toLowerCase().includes(term)));
        const matchesStatus = filterStatus.value === 'todos' || order.status === filterStatus.value;
        // Un consolidado coincide si alguna de sus programaciones es de la ubicación elegida.
        const matchesLocation =
            (!f.mine && !f.unit && !f.cafe) ||
            programs.some(
                (p) =>
                    (!f.mine || String(p.cafe?.unit?.mine?.id) === f.mine) &&
                    (!f.unit || String(p.cafe?.unit?.id) === f.unit) &&
                    (!f.cafe || String(p.cafe?.id) === f.cafe),
            );
        const created = dayjs(order.created_at).format('YYYY-MM-DD');
        const matchesDate = (!f.createdFrom || created >= f.createdFrom) && (!f.createdTo || created <= f.createdTo);
        return matchesSearch && matchesStatus && matchesLocation && matchesDate;
    });
});

const totalPendiente = computed(() => props.orders.filter((o) => o.status === 'pendiente').length);
const totalEnviada = computed(() => props.orders.filter((o) => o.status === 'enviada').length);

const statusConfig: Record<string, { label: string; class: string }> = {
    pendiente: { label: 'Pendiente', class: 'border-amber-200 bg-amber-50 text-amber-700' },
    enviada: { label: 'Enviada', class: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
};

const promptForLevel = async (title: string, prompt: string): Promise<string | null> => {
    if (!props.levels?.length) {
        Swal.fire('Atención', 'No hay niveles de receta configurados en el sistema.', 'warning');
        return null;
    }
    const { value: levelId } = await Swal.fire({
        title,
        html:
            `<p class="mb-3 text-left text-sm text-gray-600">${prompt}</p>` +
            '<select id="swal-level" class="swal2-select" style="display:block;width:100%;">' +
            props.levels.map((l) => `<option value="${l.id}">${l.name}</option>`).join('') +
            '</select>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Generar PDF',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#FF5A1F',
        preConfirm: () => (document.getElementById('swal-level') as HTMLSelectElement)?.value,
    });
    return levelId || null;
};

const generateWeeklyQuebradoPdf = async (order: PurchaseOrder) => {
    const programIds = orderProgramIds(order);
    const levelId = await promptForLevel('Quebrado Semanal (PDF)', 'Seleccione el nivel de receta a usar para calcular los ingredientes.');
    if (levelId) {
        window.open(route('planning.quebrado-pdf', { program_ids: programIds, level_id: levelId }), '_blank');
    }
};

const generateWeeklyRequirementPdf = async (order: PurchaseOrder) => {
    const programIds = orderProgramIds(order);
    const levelId = await promptForLevel(
        'Requerimiento x Producto (PDF)',
        'Seleccione el nivel de receta a usar para calcular cuánto insumo se necesita.',
    );
    if (levelId) {
        window.open(route('planning.requerimiento-pdf', { program_ids: programIds, level_id: levelId }), '_blank');
    }
};

const generateWeeklyPurchaseOrderExcel = async (order: PurchaseOrder) => {
    if (!props.levels?.length || !props.cities?.length) {
        Swal.fire('Atención', 'Faltan niveles o ciudades configuradas en el sistema.', 'warning');
        return;
    }
    const programIds = orderProgramIds(order);
    const { value: formValues } = await Swal.fire({
        title: 'Orden de Pedido Semanal (Excel)',
        html:
            '<p class="mb-3 text-left text-sm text-gray-600">Seleccione el nivel de receta y la ciudad de precios.</p>' +
            '<label class="mb-1 block text-left text-xs font-semibold text-gray-500">Nivel de receta</label>' +
            '<select id="swal-level" class="swal2-select" style="display:block;width:100%;margin-bottom:12px;">' +
            props.levels.map((l) => `<option value="${l.id}">${l.name}</option>`).join('') +
            '</select>' +
            '<label class="mb-1 block text-left text-xs font-semibold text-gray-500">Ciudad (precios)</label>' +
            '<select id="swal-city" class="swal2-select" style="display:block;width:100%;">' +
            props.cities.map((c) => `<option value="${c.id}">${c.name}</option>`).join('') +
            '</select>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Generar Excel',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#FF5A1F',
        preConfirm: () => ({
            levelId: (document.getElementById('swal-level') as HTMLSelectElement)?.value,
            cityId: (document.getElementById('swal-city') as HTMLSelectElement)?.value,
        }),
    });
    if (formValues?.levelId && formValues?.cityId) {
        window.open(
            route('planning.orden-pedido-excel', { program_ids: programIds, level_id: formValues.levelId, city_id: formValues.cityId }),
            '_blank',
        );
    }
};

const deleteOrder = (order: PurchaseOrder) => {
    Swal.fire({
        title: '¿Eliminar orden?',
        text: `Se eliminará la orden #${order.id} de "${
            orderPrograms(order)
                .map((p) => p.cafe?.name)
                .filter(Boolean)
                .join(', ') || '—'
        }". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#FF5A1F',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('purchase_orders.destroy', order.id), {
                onSuccess: () => Swal.fire({ icon: 'success', title: 'Orden eliminada', timer: 1500, showConfirmButton: false }),
                onError: () => Swal.fire({ icon: 'error', title: 'No se pudo eliminar', confirmButtonColor: '#FF5A1F' }),
            });
        }
    });
};
</script>

<template>
    <Head title="Órdenes de Compra" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <!-- Encabezado -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Órdenes de Compra</h1>
                    <p class="mt-0.5 text-xs text-slate-500">Historial de quebrados generados desde las programaciones semanales.</p>
                </div>
                <Link :href="route('planning.index')">
                    <Button variant="outline" class="rounded-xl border-slate-200 text-slate-600 hover:bg-slate-50"> Volver a Planificación </Button>
                </Link>
            </div>

            <!-- Tarjetas de resumen -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="flex items-center gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-orange-100">
                        <ShoppingCart class="h-5 w-5 text-orange-500" />
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500">Total Órdenes</p>
                        <p class="text-2xl font-bold text-slate-800">{{ orders.length }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 rounded-2xl border border-amber-100 bg-amber-50 p-5 shadow-sm">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-200">
                        <Package class="h-5 w-5 text-amber-700" />
                    </div>
                    <div>
                        <p class="text-xs font-medium text-amber-600">Pendientes</p>
                        <p class="text-2xl font-bold text-amber-800">{{ totalPendiente }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 rounded-2xl border border-emerald-100 bg-emerald-50 p-5 shadow-sm">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-200">
                        <TrendingUp class="h-5 w-5 text-emerald-700" />
                    </div>
                    <div>
                        <p class="text-xs font-medium text-emerald-600">Enviadas</p>
                        <p class="text-2xl font-bold text-emerald-800">{{ totalEnviada }}</p>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div
                class="grid grid-cols-2 items-end gap-3 rounded-2xl border border-l-4 border-slate-100 border-l-[#FF5A1F] bg-white p-4 shadow-sm sm:flex sm:flex-wrap"
            >
                <div class="col-span-2 flex flex-col gap-1 sm:min-w-[220px]">
                    <label class="text-[10px] font-bold tracking-wider text-[#FF5A1F]/75 uppercase">Buscar</label>
                    <Input
                        v-model="search"
                        placeholder="Mina, unidad, comedor o ID..."
                        class="h-9 w-full rounded-lg border-slate-200 text-xs focus-visible:ring-[#FF5A1F] sm:w-[220px]"
                    />
                </div>
                <div class="flex flex-col gap-1 sm:min-w-[140px]">
                    <label class="text-[10px] font-bold tracking-wider text-[#FF5A1F]/75 uppercase">Mina</label>
                    <select
                        v-model="filters.mine"
                        class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-[#FF5A1F] focus:ring-1 focus:ring-[#FF5A1F]/25 focus:outline-none sm:w-auto sm:min-w-[150px]"
                    >
                        <option value="">Todas</option>
                        <option v-for="m in mineOptions" :key="m.id" :value="String(m.id)">{{ m.name }}</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1 sm:min-w-[140px]">
                    <label class="text-[10px] font-bold tracking-wider text-[#FF5A1F]/75 uppercase">Unidad</label>
                    <select
                        v-model="filters.unit"
                        class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-[#FF5A1F] focus:ring-1 focus:ring-[#FF5A1F]/25 focus:outline-none sm:w-auto sm:min-w-[150px]"
                    >
                        <option value="">Todas</option>
                        <option v-for="u in unitOptions" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1 sm:min-w-[140px]">
                    <label class="text-[10px] font-bold tracking-wider text-[#FF5A1F]/75 uppercase">Café / Comedor</label>
                    <select
                        v-model="filters.cafe"
                        class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-[#FF5A1F] focus:ring-1 focus:ring-[#FF5A1F]/25 focus:outline-none sm:w-auto sm:min-w-[140px]"
                    >
                        <option value="">Todos</option>
                        <option v-for="c in cafeOptions" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold tracking-wider text-[#FF5A1F]/75 uppercase">Estado</label>
                    <Select v-model="filterStatus">
                        <SelectTrigger class="h-9 w-full rounded-lg border-slate-200 text-xs focus:ring-[#FF5A1F] sm:w-[150px]">
                            <SelectValue placeholder="Estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="todos">Todos</SelectItem>
                            <SelectItem value="pendiente">Pendiente</SelectItem>
                            <SelectItem value="enviada">Enviada</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold tracking-wider text-[#FF5A1F]/75 uppercase">Generado desde</label>
                    <Input
                        type="date"
                        v-model="filters.createdFrom"
                        class="h-9 w-full rounded-lg border-slate-200 text-xs focus-visible:ring-[#FF5A1F] sm:w-[150px]"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold tracking-wider text-[#FF5A1F]/75 uppercase">Generado hasta</label>
                    <Input
                        type="date"
                        v-model="filters.createdTo"
                        class="h-9 w-full rounded-lg border-slate-200 text-xs focus-visible:ring-[#FF5A1F] sm:w-[150px]"
                    />
                </div>
                <button
                    v-if="hasFilters"
                    type="button"
                    @click="clearFilters"
                    class="col-span-2 h-9 rounded-lg px-2.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700 sm:col-auto"
                >
                    Limpiar filtros
                </button>
                <span class="col-span-2 flex h-9 items-center text-xs text-slate-400 tabular-nums sm:col-auto sm:ml-auto">
                    {{ filteredOrders.length }} de {{ orders.length }}
                </span>
            </div>

            <!-- Estado vacío -->
            <div
                v-if="filteredOrders.length === 0"
                class="rounded-2xl border border-dashed border-slate-200 bg-white p-12 text-center text-sm text-slate-400"
            >
                <ShoppingCart class="mx-auto mb-3 h-8 w-8 text-slate-300" />
                <template v-if="orders.length === 0">
                    Aún no se han generado órdenes de compra.<br />
                    Vaya a Planificación y use "Quebrado (PO)" en una programación guardada.
                </template>
                <template v-else> No se encontraron órdenes con los filtros aplicados. </template>
            </div>

            <!-- Tabla -->
            <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow class="bg-slate-50/80 hover:bg-slate-50/80">
                                <TableHead class="w-16 font-semibold text-slate-500">#</TableHead>
                                <TableHead class="font-semibold text-slate-600">Comedor · Mina · Unidad</TableHead>
                                <TableHead class="font-semibold text-slate-600">Periodo</TableHead>
                                <TableHead class="font-semibold text-slate-600">Generado</TableHead>
                                <TableHead class="font-semibold text-slate-600">Estado</TableHead>
                                <TableHead class="text-right font-semibold text-slate-600">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="order in filteredOrders"
                                :key="order.id"
                                class="hover:bg-slate-50/50"
                                :class="order.status === 'pendiente' ? 'border-l-2 border-l-amber-400' : 'border-l-2 border-l-emerald-400'"
                            >
                                <TableCell>
                                    <span
                                        class="inline-flex h-7 w-11 items-center justify-center rounded-lg bg-orange-100 text-xs font-bold text-orange-600"
                                    >
                                        #{{ order.id }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <template v-if="orderPrograms(order).length">
                                        <div
                                            v-for="program in orderPrograms(order)"
                                            :key="program.id"
                                            class="leading-tight [&:not(:first-child)]:mt-2"
                                        >
                                            <div class="font-semibold text-slate-800">{{ program.cafe?.name || '—' }}</div>
                                            <div class="text-xs text-slate-500">
                                                <span class="font-medium">{{ program.cafe?.unit?.mine?.name || '—' }}</span>
                                                <span class="text-slate-300"> · </span>
                                                {{ program.cafe?.unit?.name || '—' }}
                                            </div>
                                        </div>
                                        <div v-if="orderPrograms(order).length > 1" class="mt-1.5 text-[11px] font-medium text-orange-500">
                                            Consolidado · {{ orderPrograms(order).length }} programaciones
                                        </div>
                                    </template>
                                    <template v-else>—</template>
                                </TableCell>
                                <TableCell>
                                    <template v-if="orderPeriod(order)">
                                        <div class="text-sm font-medium text-slate-700">
                                            {{ dayjs(orderPeriod(order)!.start).format('DD/MM/YY') }}
                                            <span class="text-slate-400"> – </span>
                                            {{ dayjs(orderPeriod(order)!.end).format('DD/MM/YY') }}
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            {{ dayjs(orderPeriod(order)!.end).diff(dayjs(orderPeriod(order)!.start), 'day') + 1 }} días
                                        </div>
                                    </template>
                                    <template v-else>—</template>
                                </TableCell>
                                <TableCell>
                                    <div class="text-sm text-slate-700">{{ dayjs(order.created_at).format('DD/MM/YYYY') }}</div>
                                    <div class="text-xs text-slate-400">{{ dayjs(order.created_at).format('HH:mm') }}</div>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        variant="outline"
                                        class="font-medium capitalize"
                                        :class="statusConfig[order.status]?.class ?? 'border-slate-200 bg-slate-50 text-slate-600'"
                                    >
                                        {{ statusConfig[order.status]?.label ?? order.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="route('purchase_orders.show', order.id)">
                                            <Button size="sm" variant="outline" class="rounded-lg border-slate-200 text-slate-600 hover:bg-slate-50">
                                                Ver Detalle
                                            </Button>
                                        </Link>

                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button
                                                    size="sm"
                                                    class="flex items-center gap-1 rounded-lg bg-[#FF5A1F] text-white hover:bg-[#e04a17]"
                                                >
                                                    Reportes
                                                    <ChevronDown class="h-3.5 w-3.5" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-64">
                                                <DropdownMenuItem class="cursor-pointer gap-2" @select="generateWeeklyQuebradoPdf(order)">
                                                    <FileText class="h-4 w-4 text-rose-500" />
                                                    Quebrado Semanal (PDF)
                                                </DropdownMenuItem>
                                                <DropdownMenuItem class="cursor-pointer gap-2" @select="generateWeeklyRequirementPdf(order)">
                                                    <FileText class="h-4 w-4 text-blue-500" />
                                                    Requerimiento x Producto (PDF)
                                                </DropdownMenuItem>
                                                <DropdownMenuItem class="cursor-pointer gap-2" @select="generateWeeklyPurchaseOrderExcel(order)">
                                                    <FileSpreadsheet class="h-4 w-4 text-emerald-500" />
                                                    Orden de Pedido Semanal (Excel)
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>

                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="rounded-lg text-red-400 hover:bg-red-50 hover:text-red-600"
                                            @click="deleteOrder(order)"
                                        >
                                            Eliminar
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
