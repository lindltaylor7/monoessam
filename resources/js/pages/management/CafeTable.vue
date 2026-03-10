<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Service } from '@/types';
import { Pencil, ShieldCheck } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';

// Definición de las props
const props = defineProps<{
    cafes: {
        id: number;
        name: string;
        // Asumiendo que `unit` y `mine` están disponibles si se necesitan en la tabla
        unit: {
            name: string;
            mine: {
                name: string;
            };
            services: Service[];
        };
    }[];
    services: Service[];
}>();

// Constante para el tipo de lugar (Cafetería)
const PLACE_TYPE_CAFE = 3;
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Cafeterías ☕</CardTitle>
        </CardHeader>
        <CardContent class="max-h-[80vh] space-y-4 overflow-y-auto">
            <div v-if="cafes && cafes.length > 0">
                <Table>
                    <TableCaption>Lista de Cafeterías en la Unidad seleccionada.</TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-[100px]">Nombre</TableHead>
                            <TableHead class="text-right">Opciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="cafe in cafes" :key="cafe.id">
                            <TableCell class="font-medium">{{ cafe.name }}</TableCell>
                            <TableCell class="flex flex-row justify-end gap-2 text-right">
                                <Button size="icon" variant="outline" as-child>
                                    <Link :href="route('cafes.roles.index')">
                                        <ShieldCheck class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button size="icon" variant="outline">
                                    <Pencil class="h-4 w-4" />
                                </Button>

                                <!-- <ServiceablesPopover :services="cafe.unit.services" :business="cafe" :placeType="PLACE_TYPE_CAFE" /> -->
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
            <div v-else class="p-4 text-center text-gray-500">Selecciona una Unidad o esta Unidad no tiene Cafeterías.</div>
        </CardContent>
    </Card>
</template>
