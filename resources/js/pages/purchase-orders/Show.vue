<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { PurchaseOrder } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import dayjs from 'dayjs';
import { Check, Loader2, Plus, Search, Trash2, X } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';

interface Props {
    order: PurchaseOrder;
}

/** Lo que devuelve purchase_orders.search-ingredients (no es el Ingredient completo). */
interface IngredientOption {
    id: number;
    name: string;
    code: string | null;
    unit_medida: string | null;
}

const props = defineProps<Props>();
const page = usePage();

const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const totalCost = computed(() => (props.order.items ?? []).reduce((acc, item) => acc + Number(item.estimated_cost ?? 0), 0));

// --- Agregar insumo -------------------------------------------------------
// El quebrado solo trae lo que estaba en la programación semanal; compras necesita poder
// sumar un insumo puntual al pedido ya generado.
const search = ref('');
const results = ref<IngredientOption[]>([]);
const searching = ref(false);
const selected = ref<IngredientOption | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | undefined;

const form = useForm({
    ingredient_id: null as number | null,
    total_amount: '' as string | number,
    unit: 'Kg.',
    estimated_cost: '' as string | number,
});

watch(search, (value) => {
    clearTimeout(searchTimer);
    const query = value.trim();

    if (selected.value && query !== selected.value.name) {
        selected.value = null;
        form.ingredient_id = null;
    }

    if (query.length < 2) {
        results.value = [];
        return;
    }

    searchTimer = setTimeout(async () => {
        searching.value = true;
        try {
            const { data } = await axios.get(route('purchase_orders.search-ingredients'), { params: { q: query } });
            results.value = data;
        } finally {
            searching.value = false;
        }
    }, 300);
});

const selectIngredient = (ingredient: IngredientOption) => {
    selected.value = ingredient;
    search.value = ingredient.name;
    results.value = [];
    form.ingredient_id = ingredient.id;
    form.unit = ingredient.unit_medida || 'Kg.';
};

const resetForm = () => {
    form.reset();
    form.clearErrors();
    search.value = '';
    results.value = [];
    selected.value = null;
};

const addItem = () => {
    if (!form.ingredient_id) {
        Swal.fire({ icon: 'warning', title: 'Seleccione un insumo', confirmButtonColor: '#FF5A1F' });
        return;
    }

    form.post(route('purchase_orders.items.store', props.order.id), {
        preserveScroll: true,
        onSuccess: () => resetForm(),
    });
};

const removeItem = (itemId: number, name?: string) => {
    Swal.fire({
        title: '¿Quitar insumo?',
        text: `Se eliminará "${name || 'el insumo'}" de este pedido.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, quitar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#FF5A1F',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('purchase_orders.items.destroy', [props.order.id, itemId]), { preserveScroll: true });
        }
    });
};

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
                    <Link :href="route('purchase_orders.index')" class="text-muted-foreground text-sm hover:underline"> ← Volver a la lista </Link>
                    <h1 class="text-2xl font-bold">Detalle de Pedido #{{ order.id }}</h1>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="exportExcel" class="rounded-xl">Exportar Excel</Button>
                    <Button class="rounded-xl">Confirmar Pedido</Button>
                </div>
            </div>

            <div
                v-if="flash?.success || flash?.error"
                class="flex items-center gap-2 rounded-xl border px-4 py-3 text-sm"
                :class="flash?.error ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'"
            >
                <X v-if="flash?.error" class="h-4 w-4" />
                <Check v-else class="h-4 w-4" />
                {{ flash?.error || flash?.success }}
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="flex flex-col gap-6 md:col-span-1">
                    <Card class="rounded-2xl border-none shadow-sm">
                        <CardHeader>
                            <CardTitle class="text-lg">Información del Plan</CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-4">
                            <div class="flex flex-col">
                                <span class="text-muted-foreground text-xs font-semibold uppercase">Café / Comedor</span>
                                <span class="text-lg font-bold">{{ order.program?.cafe?.name }}</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-muted-foreground text-xs font-semibold uppercase">Periodo de Consumo</span>
                                <span>
                                    {{ dayjs(order.program?.start_date).format('DD/MM/YYYY') }} al
                                    {{ dayjs(order.program?.end_date).format('DD/MM/YYYY') }}
                                </span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-muted-foreground text-xs font-semibold uppercase">Estado del Pedido</span>
                                <span class="capitalize">{{ order.status }}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Insumos fuera del quebrado -->
                    <Card class="rounded-2xl border-none shadow-sm">
                        <CardHeader>
                            <CardTitle class="text-lg">Agregar insumo</CardTitle>
                            <p class="text-muted-foreground text-xs">Suma al pedido un producto que no venía de la programación semanal.</p>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-3">
                            <div class="relative">
                                <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <Loader2 v-if="searching" class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 animate-spin text-slate-400" />
                                <Input
                                    v-model="search"
                                    placeholder="Buscar insumo por nombre o código..."
                                    class="h-10 rounded-xl border-slate-200 pl-9 text-sm focus-visible:ring-[#FF5A1F]"
                                    autocomplete="off"
                                />
                                <div
                                    v-if="results.length"
                                    class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg"
                                >
                                    <button
                                        v-for="ingredient in results"
                                        :key="ingredient.id"
                                        type="button"
                                        class="flex w-full flex-col items-start px-3 py-2 text-left hover:bg-orange-50"
                                        @click="selectIngredient(ingredient)"
                                    >
                                        <span class="text-sm font-medium text-slate-800">{{ ingredient.name }}</span>
                                        <span class="text-xs text-slate-400">
                                            {{ ingredient.code || 'Sin código' }} · {{ ingredient.unit_medida || 'Sin unidad' }}
                                        </span>
                                    </button>
                                </div>
                                <p v-else-if="search.trim().length >= 2 && !searching && !selected" class="mt-1 text-xs text-slate-400">
                                    Sin resultados para "{{ search.trim() }}".
                                </p>
                            </div>

                            <div v-if="selected" class="flex items-center justify-between rounded-xl bg-orange-50 px-3 py-2">
                                <div class="flex flex-col">
                                    <span class="text-sm font-semibold text-orange-700">{{ selected.name }}</span>
                                    <span class="text-xs text-orange-500">{{ selected.code || 'Sin código' }}</span>
                                </div>
                                <button type="button" class="text-orange-400 hover:text-orange-600" @click="resetForm">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-slate-500 uppercase">Cantidad</label>
                                    <Input
                                        v-model="form.total_amount"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        placeholder="0.000"
                                        class="h-10 rounded-xl border-slate-200 text-sm focus-visible:ring-[#FF5A1F]"
                                    />
                                    <span v-if="form.errors.total_amount" class="text-xs text-red-500">{{ form.errors.total_amount }}</span>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-slate-500 uppercase">Unidad</label>
                                    <Input
                                        v-model="form.unit"
                                        placeholder="Kg."
                                        class="h-10 rounded-xl border-slate-200 text-sm focus-visible:ring-[#FF5A1F]"
                                    />
                                    <span v-if="form.errors.unit" class="text-xs text-red-500">{{ form.errors.unit }}</span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-slate-500 uppercase">Costo estimado (S/.) — opcional</label>
                                <Input
                                    v-model="form.estimated_cost"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    class="h-10 rounded-xl border-slate-200 text-sm focus-visible:ring-[#FF5A1F]"
                                />
                                <span v-if="form.errors.estimated_cost" class="text-xs text-red-500">{{ form.errors.estimated_cost }}</span>
                            </div>

                            <Button
                                class="flex items-center gap-2 rounded-xl bg-[#FF5A1F] text-white hover:bg-[#e04a17]"
                                :disabled="form.processing"
                                @click="addItem"
                            >
                                <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                                <Plus v-else class="h-4 w-4" />
                                Agregar al pedido
                            </Button>
                        </CardContent>
                    </Card>
                </div>

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
                                    <TableHead class="w-12"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in order.items" :key="item.id">
                                    <TableCell class="font-medium">
                                        <div class="flex items-center gap-2">
                                            {{ item.ingredient?.name }}
                                            <span
                                                v-if="item.is_manual"
                                                class="rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-bold tracking-wide text-orange-600 uppercase"
                                            >
                                                Agregado
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-center">{{ Number(item.total_amount).toFixed(3) }}</TableCell>
                                    <TableCell class="text-center">{{ item.unit }}</TableCell>
                                    <TableCell class="text-right">
                                        {{ item.estimated_cost ? Number(item.estimated_cost).toFixed(2) : '-' }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <button
                                            type="button"
                                            class="text-slate-300 transition hover:text-red-500"
                                            title="Quitar del pedido"
                                            @click="removeItem(item.id, item.ingredient?.name)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="!order.items?.length">
                                    <TableCell colspan="5" class="text-muted-foreground py-10 text-center">
                                        Este pedido no tiene insumos. Agregue uno desde el panel de la izquierda.
                                    </TableCell>
                                </TableRow>
                                <TableRow v-else-if="totalCost > 0" class="bg-slate-50/80 font-semibold">
                                    <TableCell colspan="3" class="text-right text-slate-500">Costo estimado total</TableCell>
                                    <TableCell class="text-right">{{ totalCost.toFixed(2) }}</TableCell>
                                    <TableCell></TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
