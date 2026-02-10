<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { PurchaseOrder } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import dayjs from 'dayjs';

interface Props {
    orders: PurchaseOrder[];
}

defineProps<Props>();
</script>

<template>
    <Head title="Órdenes de Compra" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Órdenes de Compra (Quebrados)</h1>
                <Link :href="route('planning.index')">
                    <Button variant="outline" class="rounded-xl">Volver a Planificación</Button>
                </Link>
            </div>

            <Card class="rounded-2xl border-none shadow-sm">
                <CardHeader>
                    <CardTitle>Historial de Pedidos</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>ID</TableHead>
                                <TableHead>Café / Comedor</TableHead>
                                <TableHead>Periodo Programado</TableHead>
                                <TableHead>Fecha de Generación</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="order in orders" :key="order.id">
                                <TableCell class="font-medium">#{{ order.id }}</TableCell>
                                <TableCell>{{ order.program?.cafe?.name }}</TableCell>
                                <TableCell>
                                    {{ dayjs(order.program?.start_date).format('DD/MM/YYYY') }} - 
                                    {{ dayjs(order.program?.end_date).format('DD/MM/YYYY') }}
                                </TableCell>
                                <TableCell>{{ dayjs(order.created_at).format('DD/MM/YYYY HH:mm') }}</TableCell>
                                <TableCell>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                                        :class="order.status === 'pendiente' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'"
                                    >
                                        {{ order.status }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Link :href="route('purchase_orders.show', order.id)">
                                        <Button variant="ghost" size="sm" class="rounded-lg">Ver Detalle</Button>
                                    </Link>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="orders.length === 0">
                                <TableCell colspan="6" class="py-10 text-center text-muted-foreground">
                                    No se han generado órdenes de compra todavía.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
