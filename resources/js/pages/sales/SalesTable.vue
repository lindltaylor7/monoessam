<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link as InertiaLink } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import SaleDetailsPopover from './SaleDetailsPopover.vue';

const props = defineProps({
    sales: {
        type: Array as () => any[],
        required: true,
    },
    paginateData: {
        type: Object,
        required: true,
    },
    cafeId: {
        type: Number,
        required: true,
    },
});

const totalSales = ref<any[]>([]);

watch(
    () => props.sales,
    (newVal) => {
        totalSales.value = [...newVal];
    },
    { immediate: true },
);

const sendToPrint = (ticketId: number, businessId: number) => {
    window.open('/print-ticket/' + ticketId + '/' + businessId, '_blank');
};

const translatePaginationLabel = (label: string) => {
    if (label.includes('Previous')) return 'Anterior';
    if (label.includes('Next')) return 'Siguiente';
    return label;
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
                    <TableHead class="py-4 text-[11px] font-bold tracking-wider text-slate-600 uppercase">Importe</TableHead>
                    <TableHead class="py-4 text-right text-[11px] font-bold tracking-wider text-slate-600 uppercase">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow
                    v-for="sale in totalSales"
                    :key="sale.id"
                    class="group border-b-slate-100 transition-all last:border-0 hover:bg-slate-50/50"
                >
                    <TableCell class="py-3">
                        <div class="flex flex-col">
                            <span class="text-[13px] font-bold text-slate-900">{{ sale.date }}</span>
                            <span class="text-[10px] font-medium tracking-tight text-slate-400 uppercase">Registro #{{ sale.id }}</span>
                        </div>
                    </TableCell>
                    <TableCell class="py-3">
                        <Badge variant="secondary" class="border-none bg-slate-100 text-[10px] font-bold text-slate-600">
                            <Icon name="id-card" size="10" class="mr-1" />
                            {{ sale?.tickets[0]?.dinner?.dni }}
                        </Badge>
                    </TableCell>
                    <TableCell class="py-3">
                        <div class="flex items-center gap-3">
                            <div class="bg-primary/10 text-primary flex h-8 w-8 items-center justify-center rounded-lg text-xs font-bold">
                                {{ sale?.tickets[0]?.dinner?.name?.charAt(0) }}
                            </div>
                            <span class="max-w-[150px] truncate text-[13px] font-bold text-slate-700" :title="sale?.tickets[0]?.dinner?.name">
                                {{ sale?.tickets[0]?.dinner?.name }}
                            </span>
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

                <TableRow v-if="totalSales.length === 0">
                    <TableCell colspan="5" class="h-32 text-center text-sm font-medium text-slate-400 italic">
                        No hay ventas registradas en esta cafetería para el día de hoy.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <div
            v-if="paginateData.links && paginateData.links.length > 3"
            class="flex items-center justify-center gap-1.5 border-t border-slate-100 bg-slate-50/50 px-4 py-3"
        >
            <template v-for="link in paginateData.links" :key="link.label">
                <InertiaLink
                    v-if="link.url"
                    :href="link.url"
                    class="flex h-8 min-w-[32px] items-center justify-center rounded-lg border px-2.5 text-[11px] font-bold shadow-sm transition-all"
                    :class="{
                        'bg-primary text-primary-foreground border-primary': link.active,
                        'border-slate-200 bg-white text-slate-600 hover:bg-slate-50': !link.active,
                    }"
                    v-html="translatePaginationLabel(link.label)"
                    preserve-scroll
                />
                <span
                    v-else
                    class="pointer-events-none flex h-8 min-w-[32px] items-center justify-center rounded-lg px-2.5 text-[11px] font-bold text-slate-300"
                    v-html="translatePaginationLabel(link.label)"
                ></span>
            </template>
        </div>
    </div>
</template>
