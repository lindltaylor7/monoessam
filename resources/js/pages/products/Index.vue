<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, CalendarClock, Layers, Package, Pencil, Plus, Search, Store, Tag, ToggleLeft, ToggleRight, Trash2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// ── Types ──────────────────────────────────────────────────────────────────
interface Mercantil {
    id: number;
    name: string;
    unit?: { id: number; name: string };
}
interface Batch {
    id: number;
    batch_code: string | null;
    quantity: number;
    expiration_date: string | null;
    received_at: string | null;
    notes: string | null;
    expiration_status: 'expired' | 'expiring_soon' | 'ok' | null;
}
interface Product {
    id: number;
    mercantil_id: number;
    mercantil?: { id: number; name: string };
    name: string;
    marca?: string | null;
    description?: string;
    sku?: string;
    category?: string;
    price: number;
    stock: number;
    is_active: boolean;
    batches: Batch[];
    worst_batch_status?: 'expired' | 'expiring_soon' | null;
}

const props = defineProps<{ products: Product[]; mercantiles: Mercantil[] }>();

// ── Filters ────────────────────────────────────────────────────────────────
const search = ref('');
const mercantilFilter = ref('__all__');
const onlyAlerts = ref(false);

const expiringCount = computed(() => props.products.filter((p) => p.worst_batch_status).length);

const filtered = computed(() => {
    let list = props.products;
    if (mercantilFilter.value !== '__all__') list = list.filter((p) => p.mercantil_id === Number(mercantilFilter.value));
    if (onlyAlerts.value) list = list.filter((p) => p.worst_batch_status);
    if (search.value.trim())
        list = list.filter(
            (p) =>
                p.name.toLowerCase().includes(search.value.toLowerCase()) ||
                (p.sku ?? '').toLowerCase().includes(search.value.toLowerCase()) ||
                (p.category ?? '').toLowerCase().includes(search.value.toLowerCase()) ||
                (p.marca ?? '').toLowerCase().includes(search.value.toLowerCase()),
        );
    return list;
});

// ── Pagination ─────────────────────────────────────────────────────────────
const pageSize = 20;
const currentPage = ref(1);

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pageSize)));

const paginated = computed(() => {
    const start = (currentPage.value - 1) * pageSize;
    return filtered.value.slice(start, start + pageSize);
});

watch([search, mercantilFilter, onlyAlerts], () => {
    currentPage.value = 1;
});

watch(totalPages, (total) => {
    if (currentPage.value > total) currentPage.value = total;
});

const goToPage = (page: number) => {
    currentPage.value = Math.min(Math.max(page, 1), totalPages.value);
};

// ── Categories from all products ───────────────────────────────────────────
const existingCategories = computed<string[]>(() => [...new Set(props.products.map((p) => p.category ?? '').filter(Boolean))].sort());

// ── Modal: Crear / Editar producto ──────────────────────────────────────────
const showModal = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    mercantil_id: '' as string | number,
    name: '',
    marca: '',
    description: '',
    sku: '',
    category: '',
    price: '' as string | number,
    stock: 0 as string | number,
    is_active: true as boolean,
});

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.mercantil_id = props.mercantiles[0] ? String(props.mercantiles[0].id) : '';
    form.stock = 0;
    form.is_active = true;
    showModal.value = true;
};

const openEdit = (p: Product) => {
    editingId.value = p.id;
    // El Select compara valores como string (SelectItem usa :value="String(m.id)") — asignar el
    // id como number aquí hacía que ninguna opción quedara marcada como seleccionada al editar.
    form.mercantil_id = String(p.mercantil_id);
    form.name = p.name;
    form.marca = p.marca ?? '';
    form.description = p.description ?? '';
    form.sku = p.sku ?? '';
    form.category = p.category ?? '';
    form.price = p.price;
    form.stock = p.stock;
    form.is_active = p.is_active;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
};

const save = () => {
    const payload = {
        ...form.data(),
        mercantil_id: Number(form.mercantil_id),
        price: Number(form.price),
        stock: Number(form.stock),
    };

    if (editingId.value) {
        form.transform(() => payload).put(route('products.update', editingId.value!), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    } else {
        form.transform(() => payload).post(route('products.store'), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    }
};

const destroy = (id: number, name: string) => {
    if (!confirm(`¿Eliminar el producto "${name}"?`)) return;
    router.delete(route('products.destroy', id), { preserveScroll: true });
};

const toggleActive = (p: Product) => {
    router.put(
        route('products.update', p.id),
        {
            mercantil_id: p.mercantil_id,
            name: p.name,
            marca: p.marca ?? '',
            description: p.description ?? '',
            sku: p.sku ?? '',
            category: p.category ?? '',
            price: p.price,
            stock: p.stock,
            is_active: !p.is_active,
        },
        { preserveScroll: true },
    );
};

const adjustStock = (p: Product, delta: number) => {
    if (delta < 0 && p.stock + delta < 0) return;
    router.patch(route('products.stock', p.id), { delta }, { preserveScroll: true });
};

// ── Modal: Lotes (agregar por lote + fecha de vencimiento) ─────────────────
const showBatchesModal = ref(false);
const batchesProductId = ref<number | null>(null);

// Se resuelve contra `props.products` (no una copia) para que quede reactivo tras cada
// recarga parcial de Inertia al agregar/eliminar un lote.
const batchesProduct = computed(() => props.products.find((p) => p.id === batchesProductId.value) ?? null);

const batchForm = useForm({
    quantity: 1 as number | string,
    expiration_date: '',
    batch_code: '',
    notes: '',
});

const openBatchesModal = (p: Product) => {
    batchesProductId.value = p.id;
    batchForm.reset();
    batchForm.quantity = 1;
    showBatchesModal.value = true;
};

const closeBatchesModal = () => {
    showBatchesModal.value = false;
    batchesProductId.value = null;
    batchForm.reset();
};

const submitBatch = () => {
    if (!batchesProductId.value) return;
    batchForm
        .transform((data) => ({ ...data, quantity: Number(data.quantity) }))
        .post(route('products.batches.store', batchesProductId.value), {
            preserveScroll: true,
            onSuccess: () => {
                batchForm.reset();
                batchForm.quantity = 1;
            },
        });
};

const deleteBatch = (batchId: number) => {
    if (!confirm('¿Eliminar este lote? Su cantidad se descontará del stock total del producto.')) return;
    router.delete(route('products.batches.destroy', batchId), { preserveScroll: true });
};

// ── Helpers ────────────────────────────────────────────────────────────────
const fmt = (n: number) => `S/ ${Number(n).toFixed(2)}`;

const formatDate = (d: string | null) => {
    if (!d) return '—';
    const [y, m, day] = d.split('-');
    return `${day}/${m}/${y}`;
};

const BATCH_STATUS_INFO: Record<string, { label: string; cls: string; icon: typeof AlertTriangle }> = {
    expired: { label: 'Vencido', cls: 'bg-red-100 text-red-700 border-red-200', icon: AlertTriangle },
    expiring_soon: { label: 'Por vencer', cls: 'bg-amber-100 text-amber-700 border-amber-200', icon: CalendarClock },
    ok: { label: 'Vigente', cls: 'bg-emerald-100 text-emerald-700 border-emerald-200', icon: CalendarClock },
};
</script>

<template>
    <Head title="Productos" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-4 pb-8">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Productos</h1>
                    <p class="text-muted-foreground mt-1 text-sm">Gestión de productos por mercantil para el Punto de Venta.</p>
                </div>
                <Button @click="openCreate" class="gap-2 bg-indigo-600 text-white hover:bg-indigo-700">
                    <Plus class="h-4 w-4" /> Nuevo Producto
                </Button>
            </div>

            <!-- Alerta de vencimientos -->
            <button
                v-if="expiringCount > 0"
                @click="onlyAlerts = !onlyAlerts"
                class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-left shadow-sm transition-colors hover:bg-amber-100"
                :class="onlyAlerts ? 'ring-2 ring-amber-400' : ''"
            >
                <AlertTriangle class="h-5 w-5 shrink-0 text-amber-500" />
                <span class="text-sm font-semibold text-amber-800">
                    {{ expiringCount }} producto{{ expiringCount !== 1 ? 's' : '' }} con lotes vencidos o por vencer
                </span>
                <span class="text-xs text-amber-600 underline">{{ onlyAlerts ? 'Ver todos' : 'Ver solo estos' }}</span>
            </button>

            <!-- Filters -->
            <div class="bg-card flex flex-wrap items-center gap-3 rounded-xl border p-4 shadow-sm">
                <div class="relative max-w-sm min-w-[200px] flex-1">
                    <Search class="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                    <Input v-model="search" placeholder="Buscar por nombre, marca, SKU o categoría…" class="pl-9" />
                </div>

                <Select v-model="mercantilFilter">
                    <SelectTrigger class="w-56">
                        <SelectValue placeholder="Todos los mercantiles" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__all__">Todos los mercantiles</SelectItem>
                        <SelectItem v-for="m in mercantiles" :key="m.id" :value="String(m.id)">
                            {{ m.name }}<span v-if="m.unit" class="text-muted-foreground"> · {{ m.unit.name }}</span>
                        </SelectItem>
                    </SelectContent>
                </Select>

                <span class="text-muted-foreground ml-auto text-sm"> {{ filtered.length }} producto{{ filtered.length !== 1 ? 's' : '' }} </span>
            </div>

            <p v-if="filtered.length" class="text-muted-foreground -mb-2 text-sm">
                Mostrando {{ (currentPage - 1) * pageSize + 1 }}–{{ Math.min(currentPage * pageSize, filtered.length) }} de {{ filtered.length }}
            </p>

            <!-- Table -->
            <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse">
                        <thead class="bg-muted/50 sticky top-0">
                            <tr>
                                <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Producto</th>
                                <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Mercantil</th>
                                <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Categoría</th>
                                <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">SKU</th>
                                <th class="p-4 text-right text-xs font-bold tracking-wider text-zinc-500 uppercase">Precio</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Stock</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Estado</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in paginated" :key="p.id" class="group hover:bg-muted/30 border-t transition-colors">
                                <!-- Nombre + marca + descripción -->
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                            <Package class="h-4 w-4" />
                                        </div>
                                        <div>
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <p class="text-sm font-semibold text-zinc-900">{{ p.name }}</p>
                                                <span v-if="p.marca" class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-bold text-zinc-500">
                                                    {{ p.marca }}
                                                </span>
                                                <span
                                                    v-if="p.worst_batch_status"
                                                    :title="p.worst_batch_status === 'expired' ? 'Tiene lotes vencidos' : 'Tiene lotes por vencer'"
                                                    :class="[
                                                        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-bold',
                                                        BATCH_STATUS_INFO[p.worst_batch_status].cls,
                                                    ]"
                                                >
                                                    <component :is="BATCH_STATUS_INFO[p.worst_batch_status].icon" class="h-3 w-3" />
                                                    {{ BATCH_STATUS_INFO[p.worst_batch_status].label }}
                                                </span>
                                            </div>
                                            <p v-if="p.description" class="max-w-[260px] truncate text-xs text-zinc-400">
                                                {{ p.description }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <!-- Mercantil -->
                                <td class="p-4">
                                    <div class="flex items-center gap-1.5">
                                        <Store class="h-3.5 w-3.5 text-zinc-400" />
                                        <span class="text-sm text-zinc-700">{{ p.mercantil?.name ?? '—' }}</span>
                                    </div>
                                </td>
                                <!-- Categoría -->
                                <td class="p-4">
                                    <span
                                        v-if="p.category"
                                        class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-semibold text-violet-700"
                                    >
                                        <Tag class="h-3 w-3" /> {{ p.category }}
                                    </span>
                                    <span v-else class="text-xs text-zinc-400">—</span>
                                </td>
                                <!-- SKU -->
                                <td class="p-4">
                                    <span class="font-mono text-xs text-zinc-500">{{ p.sku || '—' }}</span>
                                </td>
                                <!-- Precio -->
                                <td class="p-4 text-right">
                                    <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-sm font-bold text-emerald-700">
                                        {{ fmt(p.price) }}
                                    </span>
                                </td>
                                <!-- Stock -->
                                <td class="p-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <!-- <button
                                            @click="adjustStock(p, -1)"
                                            :disabled="p.stock <= 0"
                                            class="flex h-6 w-6 items-center justify-center rounded-full bg-zinc-100 text-zinc-600 transition-colors hover:bg-red-100 hover:text-red-600 disabled:opacity-30"
                                        >
                                            <Minus class="h-3.5 w-3.5" />
                                        </button> -->
                                        <span
                                            :class="[
                                                'w-8 text-center text-sm font-bold',
                                                p.stock === 0 ? 'text-red-500' : p.stock <= 5 ? 'text-amber-500' : 'text-zinc-700',
                                            ]"
                                        >
                                            {{ p.stock }}
                                        </span>
                                        <!--  <button
                                            @click="adjustStock(p, 1)"
                                            class="flex h-6 w-6 items-center justify-center rounded-full bg-zinc-100 text-zinc-600 transition-colors hover:bg-emerald-100 hover:text-emerald-600"
                                        >
                                            <Plus class="h-3.5 w-3.5" />
                                        </button> -->
                                    </div>
                                </td>
                                <!-- Estado -->
                                <td class="p-4 text-center">
                                    <button
                                        @click="toggleActive(p)"
                                        :title="p.is_active ? 'Desactivar' : 'Activar'"
                                        class="transition-opacity hover:opacity-70"
                                    >
                                        <ToggleRight v-if="p.is_active" class="h-6 w-6 text-emerald-500" />
                                        <ToggleLeft v-else class="h-6 w-6 text-zinc-300" />
                                    </button>
                                </td>
                                <!-- Acciones -->
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-lg text-zinc-500 hover:bg-amber-50 hover:text-amber-600"
                                            @click="openBatchesModal(p)"
                                            title="Lotes / Vencimientos"
                                        >
                                            <Layers class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-lg text-zinc-500 hover:bg-indigo-50 hover:text-indigo-600"
                                            @click="openEdit(p)"
                                            title="Editar"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-lg text-zinc-500 hover:bg-red-50 hover:text-red-600"
                                            @click="destroy(p.id, p.name)"
                                            title="Eliminar"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="filtered.length === 0">
                                <td colspan="8" class="p-12 text-center">
                                    <div class="flex flex-col items-center gap-3 text-zinc-400">
                                        <Package class="h-10 w-10 opacity-30" />
                                        <p class="font-medium">Sin productos</p>
                                        <p class="text-sm">Crea el primer producto con el botón de arriba.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="totalPages > 1" class="flex items-center justify-between">
                <Button variant="outline" size="sm" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)"> Anterior </Button>

                <div class="flex items-center gap-1">
                    <Button
                        v-for="page in totalPages"
                        :key="page"
                        size="sm"
                        :variant="page === currentPage ? 'default' : 'outline'"
                        :class="page === currentPage ? 'bg-indigo-600 text-white hover:bg-indigo-700' : ''"
                        class="h-8 w-8 p-0"
                        @click="goToPage(page)"
                    >
                        {{ page }}
                    </Button>
                </div>

                <Button variant="outline" size="sm" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)"> Siguiente </Button>
            </div>
        </div>

        <!-- ── MODAL CREATE / EDIT ──────────────────────────────────────── -->
        <Dialog v-model:open="showModal">
            <DialogContent class="sm:max-w-[520px]">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Editar Producto' : 'Nuevo Producto' }}</DialogTitle>
                </DialogHeader>

                <form @submit.prevent="save" class="mt-2 space-y-4">
                    <!-- Mercantil -->
                    <div class="space-y-1.5">
                        <Label>Mercantil <span class="text-red-500">*</span></Label>
                        <template v-if="editingId">
                            <!-- Solo lectura al editar: el mercantil de un producto no se reasigna desde acá. -->
                            <div class="border-input bg-muted/40 flex h-9 w-full items-center rounded-md border px-3 text-sm text-zinc-600">
                                {{ mercantiles.find((m) => String(m.id) === String(form.mercantil_id))?.name ?? '—' }}
                                <span v-if="mercantiles.find((m) => String(m.id) === String(form.mercantil_id))?.unit" class="text-muted-foreground">
                                    &nbsp;· {{ mercantiles.find((m) => String(m.id) === String(form.mercantil_id))?.unit?.name }}
                                </span>
                            </div>
                        </template>
                        <Select v-else v-model="form.mercantil_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Seleccionar mercantil" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="m in mercantiles" :key="m.id" :value="String(m.id)">
                                    {{ m.name }}<span v-if="m.unit" class="text-muted-foreground"> · {{ m.unit.name }}</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.mercantil_id" class="text-xs text-red-500">{{ form.errors.mercantil_id }}</p>
                    </div>

                    <!-- Nombre + Marca -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Nombre <span class="text-red-500">*</span></Label>
                            <Input v-model="form.name" placeholder="Ej. Agua mineral 500ml" />
                            <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Marca</Label>
                            <Input v-model="form.marca" placeholder="Ej. San Luis" />
                            <p v-if="form.errors.marca" class="text-xs text-red-500">{{ form.errors.marca }}</p>
                        </div>
                    </div>

                    <!-- Categoría + SKU -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Categoría</Label>
                            <Input v-model="form.category" placeholder="Ej. Bebidas" list="category-list" />
                            <datalist id="category-list">
                                <option v-for="cat in existingCategories" :key="cat" :value="cat" />
                            </datalist>
                        </div>
                        <div class="space-y-1.5">
                            <Label>SKU / Código</Label>
                            <Input v-model="form.sku" placeholder="Ej. BEB-001" />
                        </div>
                    </div>

                    <!-- Precio + Stock -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Precio (S/) <span class="text-red-500">*</span></Label>
                            <Input v-model="form.price" type="number" step="0.01" min="0" placeholder="0.00" />
                            <p v-if="form.errors.price" class="text-xs text-red-500">{{ form.errors.price }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Stock</Label>
                            <Input v-model="form.stock" type="number" step="1" min="0" placeholder="0" disabled />
                            <p v-if="form.errors.stock" class="text-xs text-red-500">{{ form.errors.stock }}</p>
                            <p class="text-[11px] text-zinc-400">Para stock con vencimiento, usa "Lotes" desde la tabla.</p>
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div class="space-y-1.5">
                        <Label>Descripción</Label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            placeholder="Descripción opcional del producto…"
                            class="border-input bg-background focus:ring-ring w-full resize-none rounded-md border px-3 py-2 text-sm outline-none focus:ring-2"
                        />
                    </div>

                    <!-- Estado -->
                    <div class="flex items-center gap-3">
                        <button type="button" @click="form.is_active = !form.is_active">
                            <ToggleRight v-if="form.is_active" class="h-8 w-8 text-emerald-500" />
                            <ToggleLeft v-else class="h-8 w-8 text-zinc-300" />
                        </button>
                        <Label class="cursor-pointer" @click="form.is_active = !form.is_active">
                            {{ form.is_active ? 'Activo (visible en el POS)' : 'Inactivo (oculto en el POS)' }}
                        </Label>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="closeModal">Cancelar</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-indigo-600 text-white hover:bg-indigo-700">
                            {{ form.processing ? 'Guardando…' : editingId ? 'Guardar cambios' : 'Crear producto' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ── MODAL LOTES / VENCIMIENTOS ───────────────────────────────── -->
        <Dialog :open="showBatchesModal" @update:open="(v) => (v ? null : closeBatchesModal())">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-[560px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Layers class="h-5 w-5 text-amber-500" />
                        Lotes de {{ batchesProduct?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Registra ingresos por lote con su fecha de vencimiento. Cada lote agregado suma al stock total del producto (actualmente
                        <strong>{{ batchesProduct?.stock }}</strong
                        >).
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <!-- Form: agregar lote -->
                    <form @submit.prevent="submitBatch" class="space-y-3 rounded-xl border border-amber-100 bg-amber-50/50 p-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label class="text-xs">Cantidad <span class="text-red-500">*</span></Label>
                                <Input v-model="batchForm.quantity" type="number" min="1" step="1" placeholder="0" />
                                <p v-if="batchForm.errors.quantity" class="text-xs text-red-500">{{ batchForm.errors.quantity }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs">Fecha de vencimiento</Label>
                                <Input v-model="batchForm.expiration_date" type="date" />
                                <p v-if="batchForm.errors.expiration_date" class="text-xs text-red-500">{{ batchForm.errors.expiration_date }}</p>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">Código de lote (opcional)</Label>
                            <Input v-model="batchForm.batch_code" placeholder="Ej. L-2026-08-001" />
                        </div>
                        <Button type="submit" :disabled="batchForm.processing" class="w-full gap-2 bg-amber-500 text-white hover:bg-amber-600">
                            <Plus class="h-4 w-4" /> {{ batchForm.processing ? 'Agregando…' : 'Agregar Lote' }}
                        </Button>
                    </form>

                    <!-- Lista de lotes existentes -->
                    <div class="space-y-2">
                        <p class="text-xs font-bold tracking-wider text-zinc-400 uppercase">
                            Lotes registrados ({{ batchesProduct?.batches.length ?? 0 }})
                        </p>

                        <div v-if="!batchesProduct?.batches.length" class="rounded-xl border border-dashed py-8 text-center text-sm text-zinc-400">
                            Sin lotes registrados todavía.
                        </div>

                        <div
                            v-for="b in batchesProduct?.batches"
                            :key="b.id"
                            class="flex items-center justify-between gap-3 rounded-xl border bg-white p-3 shadow-sm"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="font-mono text-xs text-zinc-500">{{ b.batch_code || `Lote #${b.id}` }}</span>
                                    <span
                                        v-if="b.expiration_status"
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-bold',
                                            BATCH_STATUS_INFO[b.expiration_status].cls,
                                        ]"
                                    >
                                        <component :is="BATCH_STATUS_INFO[b.expiration_status].icon" class="h-3 w-3" />
                                        {{ BATCH_STATUS_INFO[b.expiration_status].label }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-zinc-700">
                                    Cant. <strong>{{ b.quantity }}</strong>
                                    <span v-if="b.expiration_date"> · Vence {{ formatDate(b.expiration_date) }}</span>
                                    <span v-else class="text-zinc-400"> · Sin fecha de vencimiento</span>
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8 shrink-0 rounded-lg text-zinc-400 hover:bg-red-50 hover:text-red-600"
                                @click="deleteBatch(b.id)"
                                title="Eliminar lote"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <Button variant="outline" @click="closeBatchesModal" class="gap-2"> <X class="h-4 w-4" /> Cerrar </Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
