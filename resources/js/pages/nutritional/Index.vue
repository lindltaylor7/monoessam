<script setup lang="ts">
import EmptyState from '@/components/shared/EmptyState.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Activity, Apple, FileSpreadsheet, Layers, Loader2, Pencil, Plus, Search, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const fileInput = ref<HTMLInputElement | null>(null);

const props = defineProps<{
    groupedIngredients: any;
    ingredients: any[];
    filters: any;
}>();

const isModalOpen = ref(false);
const isEditing = ref(false);

const ingredientSearch = ref('');
const showIngredientDropdown = ref(false);

const tableSearch = ref(props.filters.search || '');

// Buscador de la tabla con debounce
let searchTimeout: any = null;
watch(tableSearch, (value) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            route('nutritional.index'),
            { search: value },
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 300);
});

const form = useForm({
    id: null as number | null,
    ingredient_id: '' as number | string,
    nfactorcal: '',
    composition: '',
});

const filteredIngredients = computed(() => {
    if (!ingredientSearch.value) return props.ingredients.slice(0, 50);
    const search = ingredientSearch.value.toLowerCase();
    return props.ingredients.filter((ing) => ing.name.toLowerCase().includes(search)).slice(0, 50);
});

const selectIngredient = (ing: any) => {
    form.ingredient_id = ing.id;
    ingredientSearch.value = ing.name;
    showIngredientDropdown.value = false;
};

const hideIngredientDropdownDelay = () => {
    setTimeout(() => {
        showIngredientDropdown.value = false;
        const found = props.ingredients.find((i) => i.id === form.ingredient_id);
        if (found) {
            ingredientSearch.value = found.name;
        } else {
            ingredientSearch.value = '';
            form.ingredient_id = '';
        }
    }, 200);
};

const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    ingredientSearch.value = '';
    isEditing.value = false;
    isModalOpen.value = true;
};

const openEditModal = (factor: any) => {
    form.reset();
    form.clearErrors();
    form.id = factor.id;
    form.ingredient_id = factor.ingredient_id;
    form.nfactorcal = factor.nfactorcal || '';
    form.composition = factor.composition || '';
    const found = props.ingredients.find((i) => i.id === factor.ingredient_id);
    ingredientSearch.value = found ? found.name : '';
    isEditing.value = true;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
};

const submitForm = () => {
    if (!form.ingredient_id) {
        form.setError('ingredient_id', 'Por favor, selecciona un ingrediente válido de la lista.');
        return;
    }

    if (isEditing.value && form.id) {
        form.put(route('nutritional.update', form.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('nutritional.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteFactor = (id: number) => {
    if (confirm('¿Estás seguro de que deseas eliminar este factor nutricional?')) {
        form.delete(route('nutritional.destroy', id));
    }
};

const importData = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        router.post(
            route('nutritional.import'),
            {
                file: target.files[0],
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    if (fileInput.value) fileInput.value.value = '';
                },
                onError: () => {
                    if (fileInput.value) fileInput.value.value = '';
                },
            },
        );
    }
};
</script>

<template>
    <Head title="Factores Nutricionales" />
    <AppLayout>
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header Unificado -->
            <PageHeader
                title="Factores Nutricionales"
                subtitle="Control y gestión de valores calóricos y composición nutricional por ingrediente"
                badge="Nutrición & Menú"
            >
                <template #actions>
                    <input
                        type="file"
                        ref="fileInput"
                        class="hidden"
                        accept=".xlsx,.xls,.csv"
                        @change="importData"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        class="gap-2 cursor-pointer shadow-xs hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200"
                        @click="fileInput?.click()"
                    >
                        <FileSpreadsheet class="h-4 w-4 text-emerald-600" />
                        <span>Importar Excel</span>
                    </Button>
                    <Button
                        type="button"
                        class="gap-2 shadow-xs"
                        @click="openCreateModal"
                    >
                        <Plus class="h-4 w-4" />
                        <span>Nuevo Factor</span>
                    </Button>
                </template>
            </PageHeader>

            <!-- Métricas Clave -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    title="Ingredientes Paginados"
                    :value="groupedIngredients.total || 0"
                    subtitle="Registros con factores asociados"
                    color="primary"
                >
                    <template #icon>
                        <Apple class="h-5 w-5" />
                    </template>
                </StatCard>
                <StatCard
                    title="Catálogo Total"
                    :value="ingredients.length"
                    subtitle="Ingredientes disponibles en base de datos"
                    color="info"
                >
                    <template #icon>
                        <Layers class="h-5 w-5" />
                    </template>
                </StatCard>
                <StatCard
                    title="Parámetros Activos"
                    value="Caloría & Comp."
                    subtitle="Factores nfactorcal y composición"
                    color="success"
                >
                    <template #icon>
                        <Activity class="h-5 w-5" />
                    </template>
                </StatCard>
            </div>

            <!-- Barra de Filtros -->
            <Card class="border-border/60 shadow-xs">
                <CardContent class="p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="relative w-full sm:max-w-md">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="tableSearch"
                                placeholder="Buscar ingrediente por nombre..."
                                class="pl-9 pr-8 h-9"
                            />
                            <button
                                v-if="tableSearch"
                                @click="tableSearch = ''"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Mostrando {{ groupedIngredients.from || 0 }} - {{ groupedIngredients.to || 0 }} de {{ groupedIngredients.total || 0 }} ingredientes
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Tabla Agrupada -->
            <Card class="overflow-hidden border-border/60 shadow-xs">
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader class="bg-muted/40">
                            <TableRow>
                                <TableHead class="w-2/5 font-semibold">Ingrediente</TableHead>
                                <TableHead class="font-semibold">NFactorCal</TableHead>
                                <TableHead class="font-semibold">Composición</TableHead>
                                <TableHead class="text-right font-semibold">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-for="ingredient in groupedIngredients.data" :key="ingredient.id">
                                <TableRow
                                    v-for="(factor, fIndex) in ingredient.nutritional_factors"
                                    :key="factor.id"
                                    class="hover:bg-muted/30 transition-colors"
                                >
                                    <!-- Celda combinada del ingrediente -->
                                    <TableCell
                                        v-if="fIndex === 0"
                                        :rowspan="ingredient.nutritional_factors.length"
                                        class="align-top border-r border-border/50 bg-muted/10 py-3.5 px-4"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span class="h-2 w-2 rounded-full bg-red-600 shrink-0"></span>
                                            <span class="font-semibold text-foreground text-sm">{{ ingredient.name }}</span>
                                        </div>
                                    </TableCell>

                                    <TableCell class="py-3 px-4 font-mono text-sm text-foreground/80">
                                        {{ factor.nfactorcal !== null ? Number(factor.nfactorcal).toFixed(2) : '-' }}
                                    </TableCell>
                                    <TableCell class="py-3 px-4 font-mono text-sm text-foreground/80">
                                        {{ factor.composition !== null ? Number(factor.composition).toFixed(2) : '-' }}
                                    </TableCell>
                                    <TableCell class="py-3 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                                                title="Editar Factor"
                                                @click="openEditModal(factor)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10"
                                                title="Eliminar Factor"
                                                @click="deleteFactor(factor.id)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>

                            <TableRow v-if="!groupedIngredients.data || groupedIngredients.data.length === 0">
                                <TableCell colspan="4" class="p-8">
                                    <EmptyState
                                        title="No se encontraron factores nutricionales"
                                        description="No hay registros que coincidan con la búsqueda. Intente con otro término o registre un nuevo factor."
                                    >
                                        <template #icon>
                                            <Apple class="h-7 w-7 text-muted-foreground" />
                                        </template>
                                        <template #action>
                                            <Button
                                                v-if="tableSearch"
                                                variant="outline"
                                                size="sm"
                                                @click="tableSearch = ''"
                                            >
                                                Limpiar Filtro
                                            </Button>
                                            <Button
                                                v-else
                                                size="sm"
                                                @click="openCreateModal"
                                            >
                                                <Plus class="h-4 w-4 mr-1.5" />
                                                Registrar Factor
                                            </Button>
                                        </template>
                                    </EmptyState>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Paginación -->
                <div
                    v-if="groupedIngredients.data && groupedIngredients.data.length > 0"
                    class="flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-border/60 bg-muted/10 px-4 py-3"
                >
                    <p class="text-xs text-muted-foreground order-2 sm:order-1">
                        Mostrando <span class="font-medium text-foreground">{{ groupedIngredients.from }}</span> a
                        <span class="font-medium text-foreground">{{ groupedIngredients.to }}</span> de
                        <span class="font-medium text-foreground">{{ groupedIngredients.total }}</span> registros
                    </p>
                    <div class="flex items-center gap-1 order-1 sm:order-2 flex-wrap justify-center">
                        <template v-for="(link, index) in groupedIngredients.links" :key="index">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2.5 text-xs font-medium transition-colors"
                                :class="[
                                    link.active
                                        ? 'bg-primary text-primary-foreground font-semibold shadow-xs'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                ]"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2.5 text-xs text-muted-foreground/40 cursor-not-allowed select-none"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Modal CRUD Unificado -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center p-4 text-center">
                <div class="fixed inset-0 bg-background/80 backdrop-blur-sm transition-opacity" @click="closeModal" />

                <div
                    class="relative w-full max-w-lg transform overflow-visible rounded-xl border border-border/80 bg-card p-6 text-left shadow-2xl transition-all"
                >
                    <div class="flex items-center justify-between border-b border-border/60 pb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-red-700 dark:bg-red-950/40">
                                <Apple class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-foreground" id="modal-title">
                                    {{ isEditing ? 'Editar Factor Nutricional' : 'Nuevo Factor Nutricional' }}
                                </h3>
                                <p class="text-xs text-muted-foreground">Configure las variables del ingrediente</p>
                            </div>
                        </div>
                        <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground" @click="closeModal">
                            <X class="h-4 w-4" />
                        </Button>
                    </div>

                    <form @submit.prevent="submitForm" class="mt-5 space-y-4">
                        <!-- Selector Autocomplete de Ingrediente -->
                        <div class="relative space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">Ingrediente *</label>
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                                <Input
                                    type="text"
                                    v-model="ingredientSearch"
                                    @focus="showIngredientDropdown = true"
                                    @blur="hideIngredientDropdownDelay"
                                    placeholder="Buscar ingrediente en catálogo..."
                                    class="pl-9"
                                    required
                                />
                            </div>

                            <!-- Dropdown con autocomplete -->
                            <div
                                v-if="showIngredientDropdown"
                                class="absolute z-50 left-0 right-0 mt-1 max-h-56 overflow-y-auto rounded-lg border border-border bg-popover p-1 shadow-lg"
                            >
                                <div
                                    v-for="ing in filteredIngredients"
                                    :key="ing.id"
                                    @mousedown="selectIngredient(ing)"
                                    class="cursor-pointer rounded-md px-3 py-2 text-xs font-medium text-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                                >
                                    {{ ing.name }}
                                </div>
                                <div
                                    v-if="filteredIngredients.length === 0"
                                    class="p-3 text-center text-xs text-muted-foreground"
                                >
                                    No se encontraron coincidencias en el catálogo
                                </div>
                            </div>
                            <p v-if="form.errors.ingredient_id" class="text-xs font-medium text-destructive">
                                {{ form.errors.ingredient_id }}
                            </p>
                        </div>

                        <!-- Valores Numéricos -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-foreground">NFactorCal</label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    v-model="form.nfactorcal"
                                    placeholder="0.00"
                                />
                                <p v-if="form.errors.nfactorcal" class="text-xs font-medium text-destructive">
                                    {{ form.errors.nfactorcal }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-foreground">Composición</label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    v-model="form.composition"
                                    placeholder="0.00"
                                />
                                <p v-if="form.errors.composition" class="text-xs font-medium text-destructive">
                                    {{ form.errors.composition }}
                                </p>
                            </div>
                        </div>

                        <!-- Acciones -->
                        <div class="flex items-center justify-end gap-2 pt-4 border-t border-border/60">
                            <Button
                                type="button"
                                variant="outline"
                                @click="closeModal"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                :disabled="form.processing"
                                class="gap-2"
                            >
                                <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                                <span>{{ isEditing ? 'Actualizar' : 'Guardar Factor' }}</span>
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
