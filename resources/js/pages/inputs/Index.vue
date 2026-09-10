<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Boxes, FileSpreadsheet, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface InputRow {
    id: number;
    code: string | null;
    name: string;
    unit_of_measure: string | null;
    unit: number;
    cost: number;
    total: number;
}

const props = defineProps<{ inputs: InputRow[] }>();

// ── Filtro + paginación ────────────────────────────────────────────────────
const search = ref('');
const pageSize = 20;
const currentPage = ref(1);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.inputs;
    return props.inputs.filter(
        (i) =>
            i.name.toLowerCase().includes(q) ||
            (i.code ?? '').toLowerCase().includes(q) ||
            (i.unit_of_measure ?? '').toLowerCase().includes(q),
    );
});

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pageSize)));
const paginated = computed(() => filtered.value.slice((currentPage.value - 1) * pageSize, currentPage.value * pageSize));

watch(search, () => (currentPage.value = 1));
watch(totalPages, (t) => {
    if (currentPage.value > t) currentPage.value = t;
});

const goToPage = (p: number) => (currentPage.value = Math.min(Math.max(p, 1), totalPages.value));

// ── Modal crear / editar ──────────────────────────────────────────────────
const showModal = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    code: '',
    name: '',
    unit_of_measure: '',
    unit: 0 as number | string,
    cost: 0 as number | string,
    total: 0 as number | string,
});

// total = unidad * costo, recalculado en vivo salvo que el usuario lo edite a mano.
const totalTouched = ref(false);
watch(
    () => [form.unit, form.cost],
    () => {
        if (!totalTouched.value) form.total = Number((Number(form.unit || 0) * Number(form.cost || 0)).toFixed(4));
    },
);

const openCreate = () => {
    editingId.value = null;
    form.reset();
    totalTouched.value = false;
    showModal.value = true;
};

const openEdit = (row: InputRow) => {
    editingId.value = row.id;
    form.code = row.code ?? '';
    form.name = row.name;
    form.unit_of_measure = row.unit_of_measure ?? '';
    form.unit = row.unit;
    form.cost = row.cost;
    form.total = row.total;
    totalTouched.value = true;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
};

const save = () => {
    const opts = { preserveScroll: true, onSuccess: closeModal };
    if (editingId.value) {
        form.put(route('inputs.update', editingId.value), opts);
    } else {
        form.post(route('inputs.store'), opts);
    }
};

const destroy = (row: InputRow) => {
    if (!confirm(`¿Eliminar el insumo "${row.name}"?`)) return;
    router.delete(route('inputs.destroy', row.id), { preserveScroll: true });
};

// ── Importación de Excel ──────────────────────────────────────────────────
const importForm = useForm({ excel_file: null as File | null });
const fileInput = ref<HTMLInputElement | null>(null);

const onFilePicked = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    if (!file) return;
    importForm.excel_file = file;
    importForm.post(route('inputs.import'), {
        preserveScroll: true,
        onFinish: () => {
            importForm.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

const fmt = (n: number) => Number(n ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
</script>

<template>
    <Head title="Inputs" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-4 pb-8">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Inputs</h1>
                    <p class="text-muted-foreground mt-1 text-sm">Maestro de insumos: código, descripción, unidad de medida y costos.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onFilePicked" />
                    <Button variant="outline" class="gap-2" :disabled="importForm.processing" @click="fileInput?.click()">
                        <FileSpreadsheet class="h-4 w-4" /> {{ importForm.processing ? 'Importando…' : 'Importar Excel' }}
                    </Button>
                    <Button class="gap-2 bg-indigo-600 text-white hover:bg-indigo-700" @click="openCreate">
                        <Plus class="h-4 w-4" /> Nuevo Insumo
                    </Button>
                </div>
            </div>

            <!-- Filtro -->
            <div class="bg-card flex flex-wrap items-center gap-3 rounded-xl border p-4 shadow-sm">
                <div class="relative max-w-sm min-w-[200px] flex-1">
                    <Search class="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                    <Input v-model="search" placeholder="Buscar por descripción, código o unidad…" class="pl-9" />
                </div>
                <span class="text-muted-foreground ml-auto text-sm">{{ filtered.length }} insumo{{ filtered.length !== 1 ? 's' : '' }}</span>
            </div>

            <!-- Tabla -->
            <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Código</th>
                                <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Descripción</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">U. Medida</th>
                                <th class="p-4 text-right text-xs font-bold tracking-wider text-zinc-500 uppercase">Unidad</th>
                                <th class="p-4 text-right text-xs font-bold tracking-wider text-zinc-500 uppercase">Costo</th>
                                <th class="p-4 text-right text-xs font-bold tracking-wider text-zinc-500 uppercase">Total</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in paginated" :key="row.id" class="hover:bg-muted/30 border-t transition-colors">
                                <td class="p-4">
                                    <span class="font-mono text-xs text-zinc-500">{{ row.code || '—' }}</span>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                            <Boxes class="h-4 w-4" />
                                        </div>
                                        <span class="text-sm font-semibold text-zinc-900">{{ row.name }}</span>
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <span
                                        v-if="row.unit_of_measure"
                                        class="inline-flex items-center rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-semibold text-violet-700"
                                    >
                                        {{ row.unit_of_measure }}
                                    </span>
                                    <span v-else class="text-xs text-zinc-400">—</span>
                                </td>
                                <td class="p-4 text-right text-sm text-zinc-700">{{ fmt(row.unit) }}</td>
                                <td class="p-4 text-right text-sm text-zinc-700">{{ fmt(row.cost) }}</td>
                                <td class="p-4 text-right">
                                    <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-sm font-bold text-emerald-700">{{ fmt(row.total) }}</span>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-lg text-zinc-500 hover:bg-indigo-50 hover:text-indigo-600"
                                            title="Editar"
                                            @click="openEdit(row)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-lg text-zinc-500 hover:bg-red-50 hover:text-red-600"
                                            title="Eliminar"
                                            @click="destroy(row)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="filtered.length === 0">
                                <td colspan="7" class="p-12 text-center">
                                    <div class="flex flex-col items-center gap-3 text-zinc-400">
                                        <Boxes class="h-10 w-10 opacity-30" />
                                        <p class="font-medium">Sin insumos</p>
                                        <p class="text-sm">Crea el primero o importa el Excel de compras.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Paginación -->
            <div v-if="totalPages > 1" class="flex items-center justify-between">
                <Button variant="outline" size="sm" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">Anterior</Button>
                <span class="text-muted-foreground text-sm">Página {{ currentPage }} de {{ totalPages }}</span>
                <Button variant="outline" size="sm" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">Siguiente</Button>
            </div>
        </div>

        <!-- Modal crear / editar -->
        <Dialog v-model:open="showModal">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Editar Insumo' : 'Nuevo Insumo' }}</DialogTitle>
                </DialogHeader>

                <form class="mt-2 space-y-4" @submit.prevent="save">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Código</Label>
                            <Input v-model="form.code" placeholder="Ej. 010000002" />
                            <p v-if="form.errors.code" class="text-xs text-red-500">{{ form.errors.code }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Unidad de medida</Label>
                            <Input v-model="form.unit_of_measure" placeholder="Ej. KGM, NIU" />
                            <p v-if="form.errors.unit_of_measure" class="text-xs text-red-500">{{ form.errors.unit_of_measure }}</p>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Descripción <span class="text-red-500">*</span></Label>
                        <Input v-model="form.name" placeholder="Ej. ACELGA X 1 KG." />
                        <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="space-y-1.5">
                            <Label>Unidad</Label>
                            <Input v-model="form.unit" type="number" step="0.0001" min="0" />
                            <p v-if="form.errors.unit" class="text-xs text-red-500">{{ form.errors.unit }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Costo</Label>
                            <Input v-model="form.cost" type="number" step="0.0001" min="0" />
                            <p v-if="form.errors.cost" class="text-xs text-red-500">{{ form.errors.cost }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Total</Label>
                            <Input v-model="form.total" type="number" step="0.0001" min="0" @input="totalTouched = true" />
                            <p v-if="form.errors.total" class="text-xs text-red-500">{{ form.errors.total }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="closeModal">Cancelar</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-indigo-600 text-white hover:bg-indigo-700">
                            {{ form.processing ? 'Guardando…' : editingId ? 'Guardar cambios' : 'Crear insumo' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
