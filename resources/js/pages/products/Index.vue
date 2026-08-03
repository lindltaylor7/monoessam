<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Minus, Package, Pencil, Plus, Search, Store, Tag, ToggleLeft, ToggleRight, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// ── Types ──────────────────────────────────────────────────────────────────
interface Mercantil {
    id: number;
    name: string;
    unit?: { id: number; name: string };
}
interface Product {
    id: number;
    mercantil_id: number;
    mercantil?: { id: number; name: string };
    name: string;
    description?: string;
    sku?: string;
    category?: string;
    price: number;
    stock: number;
    is_active: boolean;
}

const props = defineProps<{ products: Product[]; mercantiles: Mercantil[] }>();

// ── Filters ────────────────────────────────────────────────────────────────
const search = ref('');
const mercantilFilter = ref('__all__');

const filtered = computed(() => {
    let list = props.products;
    if (mercantilFilter.value !== '__all__') list = list.filter((p) => p.mercantil_id === Number(mercantilFilter.value));
    if (search.value.trim())
        list = list.filter(
            (p) =>
                p.name.toLowerCase().includes(search.value.toLowerCase()) ||
                (p.sku ?? '').toLowerCase().includes(search.value.toLowerCase()) ||
                (p.category ?? '').toLowerCase().includes(search.value.toLowerCase()),
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

watch([search, mercantilFilter], () => {
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

// ── Modal ──────────────────────────────────────────────────────────────────
const showModal = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    mercantil_id: '' as string | number,
    name: '',
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
    form.mercantil_id = props.mercantiles[0]?.id ?? '';
    form.stock = 0;
    form.is_active = true;
    showModal.value = true;
};

const openEdit = (p: Product) => {
    editingId.value = p.id;
    form.mercantil_id = p.mercantil_id;
    form.name = p.name;
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
    router.put(route('products.update', p.id), { ...p, is_active: !p.is_active }, { preserveScroll: true });
};

const adjustStock = (p: Product, delta: number) => {
    if (delta < 0 && p.stock + delta < 0) return;
    router.patch(route('products.stock', p.id), { delta }, { preserveScroll: true });
};

// ── Helpers ────────────────────────────────────────────────────────────────
const fmt = (n: number) => `S/ ${Number(n).toFixed(2)}`;
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

            <!-- Filters -->
            <div class="bg-card flex flex-wrap items-center gap-3 rounded-xl border p-4 shadow-sm">
                <div class="relative max-w-sm min-w-[200px] flex-1">
                    <Search class="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                    <Input v-model="search" placeholder="Buscar por nombre, SKU o categoría…" class="pl-9" />
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
                                <!-- Nombre + descripción -->
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                            <Package class="h-4 w-4" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-zinc-900">{{ p.name }}</p>
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
                                        <button
                                            @click="adjustStock(p, -1)"
                                            :disabled="p.stock <= 0"
                                            class="flex h-6 w-6 items-center justify-center rounded-full bg-zinc-100 text-zinc-600 transition-colors hover:bg-red-100 hover:text-red-600 disabled:opacity-30"
                                        >
                                            <Minus class="h-3.5 w-3.5" />
                                        </button>
                                        <span
                                            :class="[
                                                'w-8 text-center text-sm font-bold',
                                                p.stock === 0 ? 'text-red-500' : p.stock <= 5 ? 'text-amber-500' : 'text-zinc-700',
                                            ]"
                                        >
                                            {{ p.stock }}
                                        </span>
                                        <button
                                            @click="adjustStock(p, 1)"
                                            class="flex h-6 w-6 items-center justify-center rounded-full bg-zinc-100 text-zinc-600 transition-colors hover:bg-emerald-100 hover:text-emerald-600"
                                        >
                                            <Plus class="h-3.5 w-3.5" />
                                        </button>
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
                        <Select v-model="form.mercantil_id">
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

                    <!-- Nombre -->
                    <div class="space-y-1.5">
                        <Label>Nombre <span class="text-red-500">*</span></Label>
                        <Input v-model="form.name" placeholder="Ej. Agua mineral 500ml" />
                        <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
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
                            <Input v-model="form.stock" type="number" step="1" min="0" placeholder="0" />
                            <p v-if="form.errors.stock" class="text-xs text-red-500">{{ form.errors.stock }}</p>
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
    </AppLayout>
</template>
