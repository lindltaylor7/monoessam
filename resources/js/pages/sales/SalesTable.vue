<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { computed, ref, watch } from 'vue';
import SaleDetailsPopover from './SaleDetailsPopover.vue';

const formatTime = (createdAt: string) => {
    if (!createdAt) return '--:--';
    const d = new Date(createdAt);
    return d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', hour12: false });
};

const props = defineProps({
    sales: {
        type: Array as () => any[],
        required: true,
    },
    paginateData: {
        type: Object,
        default: () => ({}),
    },
    cafeId: {
        type: Number,
        required: true,
    },
});

const PER_PAGE = 10;
const currentPage = ref(1);

watch(
    () => props.sales,
    () => {
        currentPage.value = 1;
    },
);

const totalPages = computed(() => Math.max(1, Math.ceil(props.sales.length / PER_PAGE)));

const pagedSales = computed(() => props.sales.slice((currentPage.value - 1) * PER_PAGE, currentPage.value * PER_PAGE));

const visiblePages = computed(() => {
    const pages: number[] = [];
    const start = Math.max(1, currentPage.value - 2);
    const end = Math.min(totalPages.value, currentPage.value + 2);
    for (let i = start; i <= end; i++) pages.push(i);
    return pages;
});

const sendToPrint = (ticketId: number, businessId: number) => {
    window.open('/print-ticket/' + ticketId + '/' + businessId, '_blank');
};
</script>

<template>
    <div class="overflow-x-auto">
        <Table>
            <TableHeader>
                <TableRow class="border-b-slate-200 bg-slate-50/80 hover:bg-slate-50/80">
                    <TableHead class="py-4 text-[11px] font-bold tracking-wider text-slate-600 uppercase">Hora / Fecha</TableHead>
                    <TableHead class="py-4 text-[11px] font-bold tracking-wider text-slate-600 uppercase">Identificación</TableHead>
                    <TableHead class="py-4 text-[11px] font-bold tracking-wider text-slate-600 uppercase">Comensal</TableHead>
                    <TableHead class="py-4 text-[11px] font-bold tracking-wider text-slate-600 uppercase">Servicio</TableHead>
                    <TableHead class="py-4 text-[11px] font-bold tracking-wider text-slate-600 uppercase">Importe</TableHead>
                    <TableHead class="py-4 text-right text-[11px] font-bold tracking-wider text-slate-600 uppercase">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow
                    v-for="sale in pagedSales"
                    :key="sale.id"
                    class="group border-b-slate-100 transition-all last:border-0 hover:bg-slate-50/50"
                >
                    <TableCell class="py-3">
                        <div class="flex flex-col">
                            <span class="text-[13px] font-bold text-slate-900">{{ formatTime(sale.created_at) }} · {{ sale.date }}</span>
                            <span class="text-[10px] font-medium tracking-tight text-slate-400 uppercase">Registro #{{ sale.id }}</span>
                        </div>
                    </TableCell>
                    <TableCell class="py-3">
                        <div class="flex flex-col gap-1">
                            <Badge variant="secondary" class="border-none bg-slate-100 text-[10px] font-bold text-slate-600">
                                <Icon name="id-card" size="10" class="mr-1" />
                                {{ sale?.tickets[0]?.dni || '—' }}
                            </Badge>
                            <span
                                v-if="sale?.tickets[0]?.subdealership_name"
                                class="truncate text-[10px] font-medium text-slate-400"
                                :title="sale?.tickets[0]?.subdealership_name"
                            >
                                {{ sale?.tickets[0]?.subdealership_name }}
                            </span>
                        </div>
                    </TableCell>
                    <TableCell class="py-3">
                        <div class="flex items-center gap-3">
                            <div class="bg-primary/10 text-primary flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold">
                                {{ (sale?.tickets[0]?.dinner_name || '?').charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <span
                                    class="block max-w-[150px] truncate text-[13px] font-bold text-slate-700"
                                    :title="sale?.tickets[0]?.dinner_name"
                                >
                                    {{ sale?.tickets[0]?.dinner_name || '—' }}
                                </span>
                                <span v-if="sale?.is_visitor" class="rounded bg-violet-100 px-1.5 py-0.5 text-[9px] font-bold text-violet-600">
                                    VISITANTE
                                </span>
                            </div>
                        </div>
                    </TableCell>
                    <TableCell class="py-3">
                        <div class="flex flex-wrap gap-1">
                            <Badge
                                v-for="detail in sale?.tickets[0]?.ticket_details"
                                :key="detail.id"
                                class="bg-primary/10 text-primary border-none text-[10px] font-bold"
                            >
                                {{ detail.service_name }}
                            </Badge>
                            <span v-if="!sale?.tickets[0]?.ticket_details?.length" class="text-[11px] text-slate-400 italic">—</span>
                        </div>
                    </TableCell>
                    <TableCell class="py-3 text-[13px] font-black text-slate-900"> S/{{ Number(sale.total).toFixed(2) }} </TableCell>
                    <TableCell class="space-x-1 py-3 text-right">
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="sendToPrint(sale.id, 1)"
                            title="Imprimir Ticket"
                            class="hover:bg-primary/10 hover:text-primary h-8 w-8 rounded-lg transition-colors"
                        >
                            <Icon name="printer" size="14" />
                        </Button>
                        <SaleDetailsPopover :ticket="sale?.tickets[0]" />
                    </TableCell>
                </TableRow>

                <TableRow v-if="props.sales.length === 0">
                    <TableCell colspan="6" class="h-32 text-center text-sm font-medium text-slate-400 italic">
                        No hay ventas registradas en esta cafetería para el día de hoy.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <div v-if="totalPages > 1" class="flex items-center justify-center gap-1.5 border-t border-slate-100 bg-slate-50/50 px-4 py-3">
            <button
                :disabled="currentPage === 1"
                @click="currentPage--"
                class="flex h-8 min-w-[32px] items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 text-[11px] font-bold text-slate-600 shadow-sm transition-all hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
                Anterior
            </button>

            <button
                v-for="page in visiblePages"
                :key="page"
                @click="currentPage = page"
                class="flex h-8 min-w-[32px] items-center justify-center rounded-lg border px-2.5 text-[11px] font-bold shadow-sm transition-all"
                :class="
                    page === currentPage
                        ? 'bg-primary text-primary-foreground border-primary'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                "
            >
                {{ page }}
            </button>

            <button
                :disabled="currentPage === totalPages"
                @click="currentPage++"
                class="flex h-8 min-w-[32px] items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 text-[11px] font-bold text-slate-600 shadow-sm transition-all hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
                Siguiente
            </button>
        </div>
    </div>
</template>
