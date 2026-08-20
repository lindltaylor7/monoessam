<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { PurchaseOrder } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import 'dayjs/locale/es';
import { ChevronDown, FileSpreadsheet, FileText, Package, ShoppingCart, TrendingUp } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref } from 'vue';

dayjs.locale('es');

interface Props {
    orders: PurchaseOrder[];
    levels?: { id: number; name: string }[];
    cities?: { id: number; name: string }[];
}

const props = defineProps<Props>();

const search = ref('');
const filterStatus = ref('todos');

const filteredOrders = computed(() => {
    return props.orders.filter((order) => {
        const matchesSearch =
            !search.value ||
            order.program?.cafe?.name?.toLowerCase().includes(search.value.toLowerCase()) ||
            String(order.id).includes(search.value);
        const matchesStatus = filterStatus.value === 'todos' || order.status === filterStatus.value;
        return matchesSearch && matchesStatus;
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
    const programId = order.program?.id ?? order.weekly_program_id;
    const levelId = await promptForLevel(
        'Quebrado Semanal (PDF)',
        'Seleccione el nivel de receta a usar para calcular los ingredientes.',
    );
    if (levelId) {
        window.open(route('planning.quebrado-pdf', { id: programId, level_id: levelId }), '_blank');
    }
};

const generateWeeklyRequirementPdf = async (order: PurchaseOrder) => {
    const programId = order.program?.id ?? order.weekly_program_id;
    const levelId = await promptForLevel(
        'Requerimiento x Producto (PDF)',
        'Seleccione el nivel de receta a usar para calcular cuánto insumo se necesita.',
    );
    if (levelId) {
        window.open(route('planning.requerimiento-pdf', { id: programId, level_id: levelId }), '_blank');
    }
};

const generateWeeklyPurchaseOrderExcel = async (order: PurchaseOrder) => {
    if (!props.levels?.length || !props.cities?.length) {
        Swal.fire('Atención', 'Faltan niveles o ciudades configuradas en el sistema.', 'warning');
        return;
    }
    const programId = order.program?.id ?? order.weekly_program_id;
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
            route('planning.orden-pedido-excel', { id: programId, level_id: formValues.levelId, city_id: formValues.cityId }),
            '_blank',
        );
    }
};

const deleteOrder = (order: PurchaseOrder) => {
    Swal.fire({
        title: '¿Eliminar orden?',
        text: `Se eliminará la orden #${order.id} del comedor "${order.program?.cafe?.name || '—'}". Esta acción no se puede deshacer.`,
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
                    <Button variant="outline" class="rounded-xl border-slate-200 text-slate-600 hover:bg-slate-50">
                        Volver a Planificación
                    </Button>
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
            <div class="flex flex-wrap items-center gap-3">
                <Input
                    v-model="search"
                    placeholder="Buscar por comedor o ID..."
                    class="h-9 w-64 rounded-xl border-slate-200 text-sm focus-visible:ring-[#FF5A1F]"
                />
                <Select v-model="filterStatus">
                    <SelectTrigger class="h-9 w-44 rounded-xl border-slate-200 text-sm focus:ring-[#FF5A1F]">
                        <SelectValue placeholder="Estado" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="todos">Todos los estados</SelectItem>
                        <SelectItem value="pendiente">Pendiente</SelectItem>
                        <SelectItem value="enviada">Enviada</SelectItem>
                    </SelectContent>
                </Select>
                <span class="ml-auto text-xs text-slate-400">
                    {{ filteredOrders.length }} {{ filteredOrders.length === 1 ? 'orden' : 'órdenes' }}
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
                <template v-else>
                    No se encontraron órdenes con los filtros aplicados.
                </template>
            </div>

            <!-- Tabla -->
            <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow class="bg-slate-50/80 hover:bg-slate-50/80">
                                <TableHead class="w-16 font-semibold text-slate-500">#</TableHead>
                                <TableHead class="font-semibold text-slate-600">Comedor</TableHead>
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
                                    <span class="inline-flex h-7 w-11 items-center justify-center rounded-lg bg-orange-100 text-xs font-bold text-orange-600">
                                        #{{ order.id }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <div class="font-semibold text-slate-800">{{ order.program?.cafe?.name || '—' }}</div>
                                    <div v-if="order.program?.cafe?.unit?.name" class="text-xs text-slate-400">
                                        {{ order.program.cafe.unit.name }}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <template v-if="order.program?.start_date && order.program?.end_date">
                                        <div class="text-sm font-medium text-slate-700">
                                            {{ dayjs(order.program.start_date).format('DD/MM/YY') }}
                                            <span class="text-slate-400"> – </span>
                                            {{ dayjs(order.program.end_date).format('DD/MM/YY') }}
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            {{ dayjs(order.program.end_date).diff(dayjs(order.program.start_date), 'day') + 1 }} días
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
