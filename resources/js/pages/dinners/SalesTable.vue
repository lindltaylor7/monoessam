<script setup lang="ts">
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link as InertiaLink } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import SaleDetailsPopover from './SaleDetailsPopover.vue';
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

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

watch(() => props.sales, (newVal) => {
    totalSales.value = [...newVal];
}, { immediate: true });

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
                <TableRow class="bg-slate-50/80 hover:bg-slate-50/80 border-b-slate-200">
                    <TableHead class="py-4 text-slate-600 font-bold uppercase tracking-wider text-[11px]">Hora / Fecha</TableHead>
                    <TableHead class="py-4 text-slate-600 font-bold uppercase tracking-wider text-[11px]">Identificación</TableHead>
                    <TableHead class="py-4 text-slate-600 font-bold uppercase tracking-wider text-[11px]">Comensal</TableHead>
                    <TableHead class="py-4 text-slate-600 font-bold uppercase tracking-wider text-[11px]">Importe</TableHead>
                    <TableHead class="text-right py-4 text-slate-600 font-bold uppercase tracking-wider text-[11px]">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="sale in totalSales" :key="sale.id" class="hover:bg-slate-50/50 transition-all border-b-slate-100 last:border-0 group">
                    <TableCell class="py-3">
                        <div class="flex flex-col">
                            <span class="text-[13px] font-bold text-slate-900">{{ sale.date }}</span>
                            <span class="text-[10px] text-slate-400 font-medium uppercase tracking-tight">Registro #{{ sale.id }}</span>
                        </div>
                    </TableCell>
                    <TableCell class="py-3">
                        <Badge variant="secondary" class="font-bold text-[10px] bg-slate-100 text-slate-600 border-none">
                            <Icon name="id-card" size="10" class="mr-1" />
                            {{ sale?.tickets[0]?.dinner?.dni }}
                        </Badge>
                    </TableCell>
                    <TableCell class="py-3">
                        <div class="flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary font-bold text-xs">
                                {{ sale?.tickets[0]?.dinner?.name?.charAt(0) }}
                            </div>
                            <span class="text-[13px] font-bold text-slate-700 truncate max-w-[150px]" :title="sale?.tickets[0]?.dinner?.name">
                                {{ sale?.tickets[0]?.dinner?.name }}
                            </span>
                        </div>
                    </TableCell>
                    <TableCell class="py-3 font-black text-slate-900 text-[13px]">
                        S/{{ Number(sale.total).toFixed(2) }}
                    </TableCell>
                    <TableCell class="text-right py-3 space-x-1">
                        <Button variant="ghost" size="icon" @click="sendToPrint(sale.id, 1)" title="Imprimir Ticket" class="h-8 w-8 rounded-lg hover:bg-primary/10 hover:text-primary transition-colors">
                            <Icon name="printer" size="14" />
                        </Button>
                        <SaleDetailsPopover :ticket="sale?.tickets[0]" />
                    </TableCell>
                </TableRow>

                <TableRow v-if="totalSales.length === 0">
                    <TableCell colspan="5" class="h-32 text-center text-slate-400 text-sm italic font-medium">
                        No hay ventas registradas en esta cafetería para el día de hoy.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <div v-if="paginateData.links && paginateData.links.length > 3" class="px-4 py-3 border-t border-slate-100 bg-slate-50/50 flex items-center justify-center gap-1.5">
            <template v-for="link in paginateData.links" :key="link.label">
                <InertiaLink
                    v-if="link.url"
                    :href="link.url"
                    class="h-8 min-w-[32px] px-2.5 flex items-center justify-center text-[11px] font-bold rounded-lg transition-all border shadow-sm"
                    :class="{
                        'bg-primary text-primary-foreground border-primary': link.active,
                        'bg-white hover:bg-slate-50 border-slate-200 text-slate-600': !link.active,
                    }"
                    v-html="translatePaginationLabel(link.label)"
                    preserve-scroll
                />
                <span 
                    v-else 
                    class="h-8 min-w-[32px] px-2.5 flex items-center justify-center text-[11px] font-bold text-slate-300 rounded-lg pointer-events-none" 
                    v-html="translatePaginationLabel(link.label)"
                ></span>
            </template>
        </div>
    </div>
</template>
