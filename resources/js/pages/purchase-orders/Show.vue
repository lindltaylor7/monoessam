<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { PurchaseOrder } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import dayjs from 'dayjs';

interface Props {
    order: PurchaseOrder;
}

defineProps<Props>();

const exportExcel = () => {
    // Logic for excel export could be added here later
    alert('Función de exportación a Excel en desarrollo');
};
</script>

<template>
    <Head :title="'Pedido #' + order.id" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <Link :href="route('purchase_orders.index')" class="text-sm text-muted-foreground hover:underline">
                        ← Volver a la lista
                    </Link>
                    <h1 class="text-2xl font-bold">Detalle de Pedido #{{ order.id }}</h1>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="exportExcel" class="rounded-xl">Exportar Excel</Button>
                    <Button class="rounded-xl">Confirmar Pedido</Button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <Card class="rounded-2xl border-none shadow-sm md:col-span-1">
                    <CardHeader>
                        <CardTitle class="text-lg">Información del Plan</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <div class="flex flex-col">
                            <span class="text-xs font-semibold uppercase text-muted-foreground">Café / Comedor</span>
                            <span class="text-lg font-bold">{{ order.program?.cafe?.name }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs font-semibold uppercase text-muted-foreground">Periodo de Consumo</span>
                            <span>
                                {{ dayjs(order.program?.start_date).format('DD/MM/YYYY') }} al 
                                {{ dayjs(order.program?.end_date).format('DD/MM/YYYY') }}
                            </span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs font-semibold uppercase text-muted-foreground">Estado del Pedido</span>
                            <span class="capitalize">{{ order.status }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card class="rounded-2xl border-none shadow-sm md:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-lg">Consolidado de Insumos (Quebrados)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Ingrediente / Insumo</TableHead>
                                    <TableHead class="text-center">Cantidad Total</TableHead>
                                    <TableHead class="text-center">Unidad</TableHead>
                                    <TableHead class="text-right">Costo Est. (S/.)</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in order.items" :key="item.id">
                                    <TableCell class="font-medium">{{ item.ingredient?.name }}</TableCell>
                                    <TableCell class="text-center">{{ Number(item.total_amount).toFixed(3) }}</TableCell>
                                    <TableCell class="text-center">{{ item.unit }}</TableCell>
                                    <TableCell class="text-right">
                                        {{ item.estimated_cost ? Number(item.estimated_cost).toFixed(2) : '-' }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
