<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link as InertiaLink } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Cafe {
    id: number;
    name: string;
    unit?: {
        id: number;
        name: string;
    };
}

interface Sale {
    id: number;
    date: string;
    total: number;
    cafe: Cafe;
    tickets: any[];
}

const props = defineProps<{
    sales: Sale[];
    paginateData: any;
}>();

const emit = defineEmits<{
    deleteSale: [saleId: number];
}>();

const totalSales = ref<Sale[]>([]);

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

const formatDate = (dateString: string) => {
    const date = new Date(dateString.replace(/-/g, '\/'));

    return date.toLocaleDateString('es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const formatTime = (dateString: string) => {
    // Assuming the date string contains time information
    // If not, this will need to be adjusted based on your data structure
    return new Date(dateString).toLocaleTimeString('es-PE', {
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <div class="overflow-x-auto">
        <Table>
            <TableHeader>
                <TableRow class="border-b-slate-200 bg-slate-50/80 hover:bg-slate-50/80">
                    <TableHead class="py-4 text-[11px] font-bold tracking-wider text-slate-600 uppercase">Fecha</TableHead>
                    <TableHead class="py-4 text-[11px] font-bold tracking-wider text-slate-600 uppercase">Cafetería</TableHead>
                    <TableHead class="py-4 text-[11px] font-bold tracking-wider text-slate-600 uppercase">DNI</TableHead>
                    <TableHead class="py-4 text-[11px] font-bold tracking-wider text-slate-600 uppercase">Comensal</TableHead>
                    <TableHead class="py-4 text-[11px] font-bold tracking-wider text-slate-600 uppercase">Servicio</TableHead>
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
                            <span class="text-[13px] font-bold text-slate-900">{{ formatDate(sale.date) }}</span>
                            <span class="text-[10px] font-medium tracking-tight text-slate-400 uppercase">Registro #{{ sale.id }}</span>
                        </div>
                    </TableCell>
                    <TableCell class="py-3">
                        <div class="flex flex-col">
                            <span class="text-[13px] font-bold text-slate-700">{{ sale.cafe.name }}</span>
                            <span v-if="sale.cafe.unit" class="text-[10px] font-medium text-slate-400">
                                {{ sale.cafe.unit.name }}
                            </span>
                        </div>
                    </TableCell>
                    <TableCell class="py-3">
                        <Badge variant="secondary" class="border-none bg-slate-100 text-[10px] font-bold text-slate-600">
                            <Icon name="id-card" size="10" class="mr-1" />
                            {{ sale?.tickets[0]?.dinner?.dni || 'N/A' }}
                        </Badge>
                    </TableCell>
                    <TableCell class="py-3">
                        <div class="flex items-center gap-3">
                            <div class="bg-primary/10 text-primary flex h-8 w-8 items-center justify-center rounded-lg text-xs font-bold">
                                {{ sale?.tickets[0]?.dinner?.name?.charAt(0) || '?' }}
                            </div>
                            <span class="max-w-[150px] truncate text-[13px] font-bold text-slate-700" :title="sale?.tickets[0]?.dinner?.name">
                                {{ sale?.tickets[0]?.dinner?.name || 'Sin nombre' }}
                            </span>
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
                        <!-- Print Button -->
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="sendToPrint(sale.id, 1)"
                            title="Imprimir Ticket"
                            class="hover:bg-primary/10 hover:text-primary h-8 w-8 rounded-lg transition-colors"
                        >
                            <Icon name="printer" size="14" />
                        </Button>

                        <!-- Details Popover -->
                        <Popover>
                            <PopoverTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    title="Ver Detalles"
                                    class="h-8 w-8 rounded-lg transition-colors hover:bg-blue-50 hover:text-blue-600"
                                >
                                    <Icon name="eye" size="14" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-80">
                                <div class="space-y-3">
                                    <div class="border-b pb-2">
                                        <h4 class="text-sm font-bold text-slate-900">Detalles de la Venta</h4>
                                        <p class="text-xs text-slate-500">Venta #{{ sale.id }}</p>
                                    </div>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-slate-600">Fecha:</span>
                                            <span class="font-semibold">{{ formatDate(sale.date) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-600">Cafetería:</span>
                                            <span class="font-semibold">{{ sale.cafe.name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-600">Comensal:</span>
                                            <span class="font-semibold">{{ sale?.tickets[0]?.dinner?.name || 'N/A' }}</span>
                                        </div>
                                        <div class="flex justify-between border-t pt-2">
                                            <span class="font-bold text-slate-600">Total:</span>
                                            <span class="font-black text-emerald-600">S/{{ Number(sale.total).toFixed(2) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </PopoverContent>
                        </Popover>

                        <!-- Delete Button with Confirmation -->
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="emit('deleteSale', sale.id)"
                            title="Eliminar Venta"
                            class="h-8 w-8 rounded-lg transition-colors hover:bg-red-50 hover:text-red-600"
                        >
                            <Icon name="trash" size="14" />
                        </Button>
                    </TableCell>
                </TableRow>

                <TableRow v-if="totalSales.length === 0">
                    <TableCell colspan="7" class="h-32 text-center text-sm font-medium text-slate-400 italic">
                        No hay ventas registradas con los filtros seleccionados.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <!-- Pagination -->
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
