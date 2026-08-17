<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { BookOpenText, Loader2, Search } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';

interface AtwaterRow {
    name: string;
    protein: string;
    fat: string;
    carb: string;
}

interface AtwaterGroup {
    title: string;
    rows: AtwaterRow[];
}

interface Factor {
    id: number;
    group: string;
    name: string;
}

interface SearchedIngredient {
    id: number;
    name: string;
    atwater_factor_id: number | null;
}

const props = defineProps<{
    groups: AtwaterGroup[];
    factors: Factor[];
}>();

const showReferenceTable = ref(false);

const groupedFactors = computed(() => {
    const groups = new Map<string, Factor[]>();
    props.factors.forEach((factor) => {
        if (!groups.has(factor.group)) groups.set(factor.group, []);
        groups.get(factor.group)!.push(factor);
    });
    return Array.from(groups.entries()).map(([title, items]) => ({ title, items }));
});

const ingredientSearch = ref('');
const searchedIngredients = ref<SearchedIngredient[]>([]);
const isSearchingIngredients = ref(false);
const factorAssignments = ref<Record<number, string>>({});
const savingIngredientId = ref<number | null>(null);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const searchIngredients = async (query: string) => {
    if (!query || query.length < 2) {
        searchedIngredients.value = [];
        return;
    }

    isSearchingIngredients.value = true;
    try {
        const response = await axios.get(route('ingredients.search', { word: query }));
        searchedIngredients.value = response.data;
        response.data.forEach((ingredient: SearchedIngredient) => {
            factorAssignments.value[ingredient.id] = ingredient.atwater_factor_id ? String(ingredient.atwater_factor_id) : 'none';
        });
    } catch (error) {
        console.error('Error searching ingredients:', error);
    } finally {
        isSearchingIngredients.value = false;
    }
};

watch(ingredientSearch, (newVal) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => searchIngredients(newVal), 300);
});

const assignAtwater = async (ingredient: SearchedIngredient, value: string) => {
    factorAssignments.value[ingredient.id] = value;

    savingIngredientId.value = ingredient.id;
    try {
        const atwaterFactorId = factorAssignments.value[ingredient.id];

        await axios.put(route('atwater.ingredients.assign', ingredient.id), {
            atwater_factor_id: atwaterFactorId === 'none' ? null : Number(atwaterFactorId),
        });
        ingredient.atwater_factor_id = atwaterFactorId === 'none' ? null : Number(atwaterFactorId);
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: `"${ingredient.name}" actualizado`,
            showConfirmButton: false,
            timer: 1800,
            timerProgressBar: true,
        });
    } catch (error: any) {
        console.error('Error updating ingredient atwater assignment:', error);
        factorAssignments.value[ingredient.id] = ingredient.atwater_factor_id ? String(ingredient.atwater_factor_id) : 'none';

        const status = error?.response?.status;
        const serverMessage = error?.response?.data?.message;
        const validationErrors = error?.response?.data?.errors;
        const detail = validationErrors
            ? Object.values(validationErrors).flat().join(' ')
            : serverMessage || error?.message || 'Error desconocido.';

        Swal.fire({
            title: 'Error',
            text: `No se pudo actualizar el ingrediente.${status ? ` (HTTP ${status})` : ''} ${detail}`,
            icon: 'error',
            confirmButtonText: 'Entendido',
        });
    } finally {
        savingIngredientId.value = null;
    }
};
</script>

<template>
    <Head title="Factores Atwater" />
    <AppLayout>
        <div class="flex h-full w-full flex-1 flex-col gap-4 overflow-y-auto p-4">
            <div class="mx-auto flex w-full max-w-4xl justify-end">
                <Button type="button" variant="outline" class="gap-2 text-sm" @click="showReferenceTable = true">
                    <BookOpenText class="h-4 w-4" />
                    Ver tabla de referencia (Anexo 2)
                </Button>
            </div>

            <Dialog v-model:open="showReferenceTable">
                <DialogContent class="sm:max-w-6xl">
                    <DialogHeader>
                        <DialogTitle class="text-center">
                            <p class="text-xs font-bold tracking-widest text-zinc-400 uppercase">Anexo 2</p>
                            <span class="mt-1 block text-xl font-extrabold text-zinc-900 dark:text-zinc-100">
                                Factores específicos <span class="italic">Atwater</span> para alimentos
                            </span>
                        </DialogTitle>
                        <DialogDescription class="sr-only">Tabla de referencia de factores Atwater por grupo de alimentos.</DialogDescription>
                    </DialogHeader>

                    <div class="max-h-[70vh] overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                        <Table>
                            <TableHeader>
                                <TableRow class="bg-indigo-600 hover:bg-indigo-600">
                                    <TableHead class="text-white"></TableHead>
                                    <TableHead class="text-center font-bold text-white">
                                        Proteína
                                        <div class="text-[10px] font-normal text-indigo-100">kcal/g (kJ/g)§</div>
                                    </TableHead>
                                    <TableHead class="text-center font-bold text-white">
                                        Grasa
                                        <div class="text-[10px] font-normal text-indigo-100">kcal/g (kJ/g)§</div>
                                    </TableHead>
                                    <TableHead class="text-center font-bold text-white">
                                        Carbohidrato Total
                                        <div class="text-[10px] font-normal text-indigo-100">kcal/g (kJ/g)§</div>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-for="group in groups" :key="group.title">
                                    <TableRow class="bg-zinc-50 hover:bg-zinc-50 dark:bg-zinc-900/60 dark:hover:bg-zinc-900/60">
                                        <TableCell colspan="4" class="text-xs font-extrabold text-zinc-700 dark:text-zinc-300">
                                            {{ group.title }}
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-for="row in group.rows" :key="row.name">
                                        <TableCell class="pl-6 text-sm text-zinc-700 dark:text-zinc-300">{{ row.name }}</TableCell>
                                        <TableCell class="text-center text-sm text-zinc-600 dark:text-zinc-400">{{ row.protein }}</TableCell>
                                        <TableCell class="text-center text-sm text-zinc-600 dark:text-zinc-400">{{ row.fat }}</TableCell>
                                        <TableCell class="text-center text-sm text-zinc-600 dark:text-zinc-400">{{ row.carb }}</TableCell>
                                    </TableRow>
                                </template>
                            </TableBody>
                        </Table>
                    </div>

                    <div class="space-y-1 text-[11px] leading-relaxed text-zinc-500 dark:text-zinc-400">
                        <p># Sin endulzar § Los datos entre paréntesis indican la energía expresada en kilojoules.</p>
                    </div>
                </DialogContent>
            </Dialog>

            <div class="mx-auto w-full max-w-4xl rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-950">
                <h2 class="text-base font-extrabold text-zinc-900 dark:text-zinc-100">Asociar Ingredientes a Factores Atwater</h2>
                <p class="mt-0.5 text-xs text-zinc-400">Busque un ingrediente y asígnele su factor Atwater (Anexo 2).</p>

                <div class="relative mt-4">
                    <Search v-if="!isSearchingIngredients" class="absolute top-2.5 left-3 h-4 w-4 text-indigo-500" />
                    <Loader2 v-else class="absolute top-2.5 left-3 h-4 w-4 animate-spin text-indigo-600" />
                    <Input
                        v-model="ingredientSearch"
                        placeholder="Buscar ingrediente..."
                        class="h-9 rounded-xl border-zinc-200 pl-9 text-sm dark:border-zinc-800"
                    />
                </div>

                <div class="mt-3 max-h-[400px] overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <div v-if="ingredientSearch.length < 2" class="p-6 text-center text-xs text-zinc-400">
                        Escriba al menos 2 letras para buscar.
                    </div>
                    <div
                        v-else-if="!isSearchingIngredients && searchedIngredients.length === 0"
                        class="p-6 text-center text-xs text-zinc-400"
                    >
                        No se encontraron ingredientes.
                    </div>
                    <div
                        v-for="ingredient in searchedIngredients"
                        :key="ingredient.id"
                        class="flex flex-col gap-2 border-b border-zinc-100 p-3 last:border-b-0 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800"
                    >
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ ingredient.name }}</span>
                        <Select
                            :model-value="factorAssignments[ingredient.id] ?? 'none'"
                            :disabled="savingIngredientId === ingredient.id"
                            @update:model-value="(value) => assignAtwater(ingredient, String(value))"
                        >
                            <SelectTrigger class="h-8 w-64 shrink-0 text-xs">
                                <SelectValue placeholder="Sin factor Atwater" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Sin factor Atwater</SelectItem>
                                <SelectGroup v-for="group in groupedFactors" :key="group.title">
                                    <SelectLabel>{{ group.title }}</SelectLabel>
                                    <SelectItem v-for="f in group.items" :key="f.id" :value="String(f.id)">
                                        {{ f.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
