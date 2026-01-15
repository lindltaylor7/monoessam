<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link as InertiaLink } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import SaleDetailsPopover from './SaleDetailsPopover.vue';

const sendToPrint = (ticketId, businnessId) => {
    window.open('/print-ticket/' + ticketId + '/' + businnessId, '_blank');
};

const props = defineProps({
    sales: {
        type: Array,
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

const totalSales = ref([]);

watch(() => props.cafeId, () => {
    if(props.cafeId != 0){
        totalSales.value = props.paginateData.data.filter((sale) => sale.cafe_id == props.cafeId);
    }else{
        totalSales.value = [];
    }
})


// Función para traducir las etiquetas de paginación
const translatePaginationLabel = (label) => {
    if (label.includes('Previous')) return 'Anterior';
    if (label.includes('Next')) return 'Siguiente';
    if (label.includes('&laquo;')) return 'Primera';
    if (label.includes('&raquo;')) return 'Última';
    return label;
};
</script>

<template>
    <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden overflow-y-visible rounded-xl border">
        <Table>
            <TableCaption>
                <div class="pagination mt-4 flex items-center justify-center gap-1">
                    <template v-for="link in paginateData.links" :key="link.url">
                        <InertiaLink
                            v-if="link.url"
                            :href="link.url"
                            class="rounded-md border border-gray-300 px-3 py-1 transition-colors hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700"
                            :class="{
                                'bg-primary border-primary dark:border-primary text-white': link.active,
                                'text-gray-500 dark:text-gray-400': !link.active,
                            }"
                        >
                            {{ translatePaginationLabel(link.label) }}
                        </InertiaLink>
                        <span v-else class="px-3 py-1 text-gray-400 dark:text-gray-500" v-html="translatePaginationLabel(link.label)"></span>
                    </template>
                </div>
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead class="w-[100px]">Fecha</TableHead>
                    <TableHead class="w-[100px]">DNI</TableHead>
                    <TableHead class="w-[200px]">Nombre</TableHead>
                    <TableHead class="w-[200px]">Total</TableHead>
                    <TableHead class="text-right">Opciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
           
                <TableRow v-for="sale in totalSales" :key="sale.id">
                    <TableCell class="w-[100px]">{{ sale.date }}</TableCell>
                    <TableCell class="w-[100px]">{{ sale?.tickets[0].dinner.dni }}</TableCell>
                    <TableCell class="w-[200px] font-medium" :title="sale.name">{{ sale?.tickets[0].dinner.name }}</TableCell>
                    <TableCell class="w-[200px] font-medium" :title="sale.total">
                        S./{{ Number(sale.total).toFixed(2) }}
                    </TableCell>
                    <TableCell class="space-x-2 text-right">
                        <Button @click="sendToPrint(sale.id, 1)">Imprimir</Button>
                        <SaleDetailsPopover :ticket="sale?.tickets[0]" />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>

<style scoped>
.pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    justify-content: center;
    align-items: center;
    margin-top: 1rem;
}

.pagination a,
.pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    transition: all 0.2s ease;
}

.pagination a:hover {
    background-color: #f3f4f6;
    color: #1f2937;
}

.pagination .active {
    background-color: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.dark .pagination a:hover {
    background-color: #374151;
    color: #f3f4f6;
}
</style>
