<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, ToggleLeft, ToggleRight, Trash2, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface MercantilItem {
    id: number;
    name: string;
    address: string | null;
    is_active: boolean;
    unit_id: number;
}

const props = defineProps<{
    mercantiles: MercantilItem[];
    unitId: number;
}>();

const localMercantiles = ref<MercantilItem[]>(props.mercantiles.map((m) => ({ ...m })));

watch(
    () => props.mercantiles,
    (v) => {
        localMercantiles.value = v.map((m) => ({ ...m }));
    },
    { deep: true },
);

const createForm = useForm({
    name: '',
    address: '',
});

const submitCreate = () => {
    if (!createForm.name.trim() || !props.unitId) return;

    createForm
        .transform((data) => ({ ...data, unit_id: props.unitId, is_active: true }))
        .post(route('mercantiles.store'), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
};

const editingId = ref<number | null>(null);
const editForm = useForm({
    name: '',
    address: '',
});

const startEdit = (m: MercantilItem) => {
    editingId.value = m.id;
    editForm.name = m.name;
    editForm.address = m.address ?? '';
};

const cancelEdit = () => {
    editingId.value = null;
};

const saveEdit = (m: MercantilItem) => {
    editForm
        .transform((data) => ({ ...data, unit_id: m.unit_id, is_active: m.is_active }))
        .put(route('mercantiles.update', m.id), {
            preserveScroll: true,
            onSuccess: () => (editingId.value = null),
        });
};

const toggleActive = (m: MercantilItem) => {
    router.put(
        route('mercantiles.update', m.id),
        { name: m.name, address: m.address, unit_id: m.unit_id, is_active: !m.is_active },
        { preserveScroll: true },
    );
};

const destroy = (m: MercantilItem) => {
    if (!confirm(`¿Eliminar el mercantil "${m.name}"?`)) return;
    router.delete(route('mercantiles.destroy', m.id), { preserveScroll: true });
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Mercantiles 🏪</CardTitle>
        </CardHeader>
        <CardContent class="max-h-[80vh] space-y-4 overflow-y-auto">
            <form @submit.prevent="submitCreate" class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="mb-1 block text-xs text-gray-500">Nombre</label>
                    <Input v-model="createForm.name" placeholder="Nuevo Mercantil..." />
                </div>
                <div class="flex-1">
                    <label class="mb-1 block text-xs text-gray-500">Dirección</label>
                    <Input v-model="createForm.address" placeholder="Dirección (opcional)" />
                </div>
                <Button
                    type="submit"
                    size="icon"
                    :disabled="!createForm.name.trim() || createForm.processing"
                    title="Crear Mercantil en esta Unidad"
                >
                    <Plus class="h-4 w-4" />
                </Button>
            </form>

            <div v-if="localMercantiles && localMercantiles.length > 0">
                <Table>
                    <TableCaption>Lista de Mercantiles en la Unidad seleccionada.</TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nombre / Dirección</TableHead>
                            <TableHead class="text-center">Estado</TableHead>
                            <TableHead class="text-right">Opciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="m in localMercantiles" :key="m.id">
                            <TableCell class="font-medium">
                                <div v-if="editingId === m.id" class="space-y-1">
                                    <Input v-model="editForm.name" class="h-8 text-sm" placeholder="Nombre" />
                                    <Input v-model="editForm.address" class="h-8 text-xs" placeholder="Dirección" />
                                </div>
                                <div v-else>
                                    <div>{{ m.name }}</div>
                                    <div class="text-xs text-gray-500">{{ m.address || 'Sin dirección' }}</div>
                                </div>
                            </TableCell>
                            <TableCell class="text-center">
                                <button @click="toggleActive(m)" :title="m.is_active ? 'Desactivar' : 'Activar'">
                                    <ToggleRight v-if="m.is_active" class="h-6 w-6 text-emerald-500" />
                                    <ToggleLeft v-else class="h-6 w-6 text-gray-300" />
                                </button>
                            </TableCell>
                            <TableCell class="flex flex-row justify-end gap-2 text-right">
                                <template v-if="editingId === m.id">
                                    <Button size="icon" variant="outline" @click="saveEdit(m)" title="Guardar">
                                        <span class="text-xs">✓</span>
                                    </Button>
                                    <Button size="icon" variant="outline" @click="cancelEdit" title="Cancelar">
                                        <X class="h-4 w-4" />
                                    </Button>
                                </template>
                                <template v-else>
                                    <Button size="icon" variant="outline" @click="startEdit(m)" title="Editar">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button size="icon" variant="outline" @click="destroy(m)" title="Eliminar">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </template>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
            <div v-else class="p-4 text-center text-gray-500">Esta Unidad no tiene Mercantiles.</div>
        </CardContent>
    </Card>
</template>
