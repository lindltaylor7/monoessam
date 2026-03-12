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

interface StaffCloth {
    id: number;
    cloth_id: number | null;
    clothe_name: string;
    clothing_size: string;
    status: string | null;
    color_id: number | null;
    cloth?: { name: string };
}

const props = defineProps<{
    open: boolean;
    staff: {
        name: string;
        staff_clothes: StaffCloth[];
    } | null; 
    colors: Array<{ id: number, name: string }>;
}>();

const emit = defineEmits(['update:open']);

const updateStatus = (clothEntryId: number, status: string, colorId?: number | null) => {
    router.post(route('clothes.status'), {
        id: clothEntryId,
        status: status,
        color_id: colorId
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
            
            <div class="grid gap-6 py-4" v-if="staff">
                <!-- Perfil de Tallas (Reference) -->
                <div v-if="staff.staff_clothes && staff.staff_clothes.filter((c: StaffCloth) => !c.cloth_id).length > 0" class="space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 px-1">Perfil de Tallas (Referencia)</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <div 
                            v-for="item in staff.staff_clothes.filter((c: StaffCloth) => !c.cloth_id)" 
                            :key="item.id"
                            class="flex flex-col p-2 rounded-lg bg-zinc-50 border border-zinc-100"
                        >
                            <span class="text-[10px] text-zinc-500 truncate">{{ item.clothe_name }}</span>
                            <span class="text-sm font-bold text-zinc-900 uppercase">{{ item.clothing_size || '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Prenda Asignaciones (Actual tracking) -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 px-1">Seguimiento de Prendas (Asignación)</h3>
                    <div v-if="staff.staff_clothes && staff.staff_clothes.filter((c: StaffCloth) => c.cloth_id).length > 0" class="border rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">Prenda</th>
                                    <th class="px-4 py-2 text-center font-medium text-gray-500">Talla</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">Color</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="item in staff.staff_clothes.filter((c: StaffCloth) => c.cloth_id)" :key="item.id">
                                    <td class="px-4 py-2">
                                        {{ item.cloth ? item.cloth.name : (item.clothe_name || 'Prenda Desconocida') }}
                                    </td>
                                    <td class="px-4 py-2 text-center font-medium uppercase">
                                        {{ item.clothing_size || '-' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <Select :model-value="String(item.color_id || '')" @update:model-value="(val: any) => updateStatus(item.id, item.status || 'Pendiente', parseInt(val))">
                                            <SelectTrigger class="h-8 w-[140px]">
                                                <SelectValue placeholder="Color" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="color in colors" :key="color.id" :value="String(color.id)">
                                                    {{ color.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </td>
                                    <td class="px-4 py-2">
                                        <Select :model-value="item.status || 'Pendiente'" @update:model-value="(val: any) => updateStatus(item.id, val, item.color_id)">
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
                        No hay prendas asignadas todavía.
                    </div>
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
