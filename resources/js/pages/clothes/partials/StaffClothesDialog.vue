<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    open: boolean;
    staff: any; // Using any to accommodate the ExtendedStaff type flexibly
}>();

const emit = defineEmits(['update:open']);

const updateStatus = (clothEntryId: number, status: string) => {
    router.post(route('clothes.status'), {
        id: clothEntryId,
        status: status
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['staff'] 
    });
}
const getStatusColor = (status: string) => {
    switch (status) {
        case 'Entregado': return 'bg-red-100 text-red-700 border-red-200';
        case 'Devuelto': return 'bg-green-100 text-green-700 border-green-200';
        default: return 'bg-yellow-100 text-yellow-700 border-yellow-200';
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-[700px]">
            <DialogHeader>
                <DialogTitle>Tallas Asignadas</DialogTitle>
                <DialogDescription>
                    Resumen de tallas y estado de entrega para {{ staff?.name }}
                </DialogDescription>
            </DialogHeader>
            
            <div class="grid gap-4 py-4" v-if="staff">
                <div v-if="staff.staff_clothes && staff.staff_clothes.length > 0" class="border rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Prenda</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-500">Talla</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="item in staff.staff_clothes" :key="item.id">
                                <td class="px-4 py-2">
                                    {{ item.cloth ? item.cloth.name : (item.clothe_name || 'Prenda Desconocida') }}
                                </td>
                                <td class="px-4 py-2 text-center font-medium uppercase">
                                    {{ item.clothing_size || '-' }}
                                </td>
                                <td class="px-4 py-2">
                                    <Select :model-value="item.status || 'Pendiente'" @update:model-value="(val) => updateStatus(item.id, val)">
                                        <SelectTrigger :class="['h-8 w-[140px]', getStatusColor(item.status || 'Pendiente')]">
                                            <SelectValue placeholder="Estado" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Pendiente">Pendiente</SelectItem>
                                            <SelectItem value="Entregado">Entregado</SelectItem>
                                            <SelectItem value="Devuelto">Devuelto</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="text-center py-6 text-gray-500 bg-gray-50 rounded-lg border border-dashed">
                    No hay tallas registradas.
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="$emit('update:open', false)">
                    Cerrar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
