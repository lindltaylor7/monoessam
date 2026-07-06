<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, MapPin, Pencil, Plus, Search, Store, Trash2, ToggleLeft, ToggleRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// ── Types ──────────────────────────────────────────────────────────────────
interface Unit { id: number; name: string }
interface Mercantil {
    id: number;
    unit_id: number;
    unit?: { id: number; name: string };
    name: string;
    address?: string;
    is_active: boolean;
}

const props = defineProps<{ mercantiles: Mercantil[]; units: Unit[] }>();

// ── Filters ────────────────────────────────────────────────────────────────
const search     = ref('');
const unitFilter = ref('__all__');

const filtered = computed(() => {
    let list = props.mercantiles;
    if (unitFilter.value !== '__all__')
        list = list.filter(m => m.unit_id === Number(unitFilter.value));
    if (search.value.trim())
        list = list.filter(m => m.name.toLowerCase().includes(search.value.toLowerCase()));
    return list;
});

// ── Modal ──────────────────────────────────────────────────────────────────
const showModal = ref(false);
const editingId  = ref<number | null>(null);

const form = useForm({
    unit_id:    '' as string | number,
    name:       '',
    address:    '',
    is_active:  true as boolean,
});

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.unit_id   = props.units[0]?.id ?? '';
    form.is_active = true;
    showModal.value = true;
};

const openEdit = (m: Mercantil) => {
    editingId.value = m.id;
    form.unit_id    = m.unit_id;
    form.name       = m.name;
    form.address    = m.address ?? '';
    form.is_active  = m.is_active;
    showModal.value = true;
};

const closeModal = () => { showModal.value = false; form.reset(); };

const save = () => {
    const payload = { ...form.data(), unit_id: Number(form.unit_id) };

    if (editingId.value) {
        form.transform(() => payload).put(route('mercantiles.update', editingId.value!), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    } else {
        form.transform(() => payload).post(route('mercantiles.store'), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    }
};

const destroy = (id: number, name: string) => {
    if (!confirm(`¿Eliminar el mercantil "${name}"?`)) return;
    router.delete(route('mercantiles.destroy', id), { preserveScroll: true });
};

const toggleActive = (m: Mercantil) => {
    router.put(route('mercantiles.update', m.id), { ...m, is_active: !m.is_active }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Mercantiles" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-4 pb-8">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Mercantiles</h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Puntos de venta por unidad. Cada mercantil tiene su propio catálogo de productos y POS.
                    </p>
                </div>
                <Button @click="openCreate" class="gap-2 bg-indigo-600 hover:bg-indigo-700 text-white">
                    <Plus class="h-4 w-4" /> Nuevo Mercantil
                </Button>
            </div>

            <!-- Filters -->
            <div class="bg-card flex flex-wrap items-center gap-3 rounded-xl border p-4 shadow-sm">
                <div class="relative flex-1 min-w-[200px] max-w-sm">
                    <Search class="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                    <Input v-model="search" placeholder="Buscar por nombre…" class="pl-9" />
                </div>

                <Select v-model="unitFilter">
                    <SelectTrigger class="w-56">
                        <SelectValue placeholder="Todas las unidades" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__all__">Todas las unidades</SelectItem>
                        <SelectItem v-for="u in units" :key="u.id" :value="String(u.id)">{{ u.name }}</SelectItem>
                    </SelectContent>
                </Select>

                <span class="text-muted-foreground ml-auto text-sm">
                    {{ filtered.length }} mercantil{{ filtered.length !== 1 ? 'es' : '' }}
                </span>
            </div>

            <!-- Table -->
            <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse">
                        <thead class="bg-muted/50 sticky top-0">
                            <tr>
                                <th class="p-4 text-left text-xs font-bold uppercase tracking-wider text-zinc-500">Mercantil</th>
                                <th class="p-4 text-left text-xs font-bold uppercase tracking-wider text-zinc-500">Unidad</th>
                                <th class="p-4 text-left text-xs font-bold uppercase tracking-wider text-zinc-500">Dirección</th>
                                <th class="p-4 text-center text-xs font-bold uppercase tracking-wider text-zinc-500">Estado</th>
                                <th class="p-4 text-center text-xs font-bold uppercase tracking-wider text-zinc-500">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in filtered" :key="m.id"
                                class="group border-t transition-colors hover:bg-muted/30">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                            <Store class="h-4 w-4" />
                                        </div>
                                        <p class="text-sm font-semibold text-zinc-900">{{ m.name }}</p>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-1.5">
                                        <Building2 class="h-3.5 w-3.5 text-zinc-400" />
                                        <span class="text-sm text-zinc-700">{{ m.unit?.name ?? '—' }}</span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div v-if="m.address" class="flex items-center gap-1.5">
                                        <MapPin class="h-3.5 w-3.5 text-zinc-400" />
                                        <span class="text-sm text-zinc-600">{{ m.address }}</span>
                                    </div>
                                    <span v-else class="text-xs text-zinc-400">—</span>
                                </td>
                                <td class="p-4 text-center">
                                    <button @click="toggleActive(m)" :title="m.is_active ? 'Desactivar' : 'Activar'"
                                        class="transition-opacity hover:opacity-70">
                                        <ToggleRight v-if="m.is_active" class="h-6 w-6 text-emerald-500" />
                                        <ToggleLeft v-else class="h-6 w-6 text-zinc-300" />
                                    </button>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <Button variant="ghost" size="icon"
                                            class="h-8 w-8 rounded-lg text-zinc-500 hover:bg-indigo-50 hover:text-indigo-600"
                                            @click="openEdit(m)" title="Editar">
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon"
                                            class="h-8 w-8 rounded-lg text-zinc-500 hover:bg-red-50 hover:text-red-600"
                                            @click="destroy(m.id, m.name)" title="Eliminar">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="filtered.length === 0">
                                <td colspan="5" class="p-12 text-center">
                                    <div class="flex flex-col items-center gap-3 text-zinc-400">
                                        <Store class="h-10 w-10 opacity-30" />
                                        <p class="font-medium">Sin mercantiles</p>
                                        <p class="text-sm">Crea el primero con el botón de arriba.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── MODAL CREATE / EDIT ──────────────────────────────────────── -->
        <Dialog v-model:open="showModal">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Editar Mercantil' : 'Nuevo Mercantil' }}</DialogTitle>
                </DialogHeader>

                <form @submit.prevent="save" class="mt-2 space-y-4">
                    <!-- Unidad -->
                    <div class="space-y-1.5">
                        <Label>Unidad <span class="text-red-500">*</span></Label>
                        <Select v-model="form.unit_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Seleccionar unidad" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="u in units" :key="u.id" :value="String(u.id)">{{ u.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.unit_id" class="text-xs text-red-500">{{ form.errors.unit_id }}</p>
                    </div>

                    <!-- Nombre -->
                    <div class="space-y-1.5">
                        <Label>Nombre <span class="text-red-500">*</span></Label>
                        <Input v-model="form.name" placeholder="Ej. Mercantil Central" />
                        <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>

                    <!-- Dirección -->
                    <div class="space-y-1.5">
                        <Label>Dirección</Label>
                        <Input v-model="form.address" placeholder="Dirección opcional" />
                    </div>

                    <!-- Estado -->
                    <div class="flex items-center gap-3">
                        <button type="button" @click="form.is_active = !form.is_active">
                            <ToggleRight v-if="form.is_active" class="h-8 w-8 text-emerald-500" />
                            <ToggleLeft v-else class="h-8 w-8 text-zinc-300" />
                        </button>
                        <Label class="cursor-pointer" @click="form.is_active = !form.is_active">
                            {{ form.is_active ? 'Activo' : 'Inactivo' }}
                        </Label>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="closeModal">Cancelar</Button>
                        <Button type="submit" :disabled="form.processing"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white">
                            {{ form.processing ? 'Guardando…' : editingId ? 'Guardar cambios' : 'Crear mercantil' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
