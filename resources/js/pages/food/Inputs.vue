<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { 
    Search, 
    Plus, 
    Pencil, 
    Trash2, 
    Utensils, 
    Info, 
    ChevronLeft, 
    ChevronRight,
    Type,
    LayoutGrid,
    Scale,
    Droplets,
    Flame,
    FileText,
    Calculator,
    Zap,
    Milk,
    Beef,
    Cookie,
    Wheat,
    Waves,
    Beaker,
    Microscope,
    FlaskConical,
    Upload,
    Loader2
} from 'lucide-vue-next';
import { watch } from 'vue';

import Swal from 'sweetalert2';

interface Ingredient {
    id: number;
    name: string;
    description: string | null;
    amount: number | null;
    waste: number | null;
    energy: number | null;
    ingredient_category_id: number | null;
    ingredient_category?: {
        id: number;
        name: string;
    };
    dosification?: Dosification | null;
}

interface Dosification {
    id: number;
    ingredient_id: number;
    energy: number | null;
    water: number | null;
    protein: number | null;
    lipid: number | null;
    carbohydrate: number | null;
    fiber: number | null;
    ash: number | null;
    calcium: number | null;
    phosphorus: number | null;
    iron: number | null;
    retinol: number | null;
    thiamine: number | null;
    riboflavin: number | null;
    niacin: number | null;
    a_asc: number | null;
    sodium: number | null;
    potassium: number | null;
    magnesium: number | null;
    zinc: number | null;
    selenium: number | null;
    a_folic: number | null;
    v_b6: number | null;
    v_e: number | null;
    v_b12: number | null;
    v_b9: number | null;
    iodine: number | null;
    cholesterol: number | null;
}

interface Category {
    id: number;
    name: string;
}

const props = defineProps<{
    ingredients: Ingredient[];
    categories: Category[];
}>();

const searchQuery = ref('');
const isDialogOpen = ref(false);
const editingIngredient = ref<Ingredient | null>(null);

const form = useForm({
    name: '',
    description: '',
    amount: null as number | null,
    waste: null as number | null,
    energy: null as number | null,
    ingredient_category_id: null as number | null,
});

const isDosificationModalOpen = ref(false);
const selectedIngredientForDosification = ref<Ingredient | null>(null);

const dosificationForm = useForm({
    energy: null as number | null,
    water: null as number | null,
    protein: null as number | null,
    lipid: null as number | null,
    carbohydrate: null as number | null,
    fiber: null as number | null,
    ash: null as number | null,
    calcium: null as number | null,
    phosphorus: null as number | null,
    iron: null as number | null,
    retinol: null as number | null,
    thiamine: null as number | null,
    riboflavin: null as number | null,
    niacin: null as number | null,
    a_asc: null as number | null,
    sodium: null as number | null,
    potassium: null as number | null,
    magnesium: null as number | null,
    zinc: null as number | null,
    selenium: null as number | null,
    a_folic: null as number | null,
    v_b6: null as number | null,
    v_e: null as number | null,
    v_b12: null as number | null,
    v_b9: null as number | null,
    iodine: null as number | null,
    cholesterol: null as number | null,
});

const openDosificationModal = (ingredient: Ingredient) => {
    selectedIngredientForDosification.value = ingredient;
    const d = ingredient.dosification;
    
    dosificationForm.energy = d?.energy ?? null;
    dosificationForm.water = d?.water ?? null;
    dosificationForm.protein = d?.protein ?? null;
    dosificationForm.lipid = d?.lipid ?? null;
    dosificationForm.carbohydrate = d?.carbohydrate ?? null;
    dosificationForm.fiber = d?.fiber ?? null;
    dosificationForm.ash = d?.ash ?? null;
    dosificationForm.calcium = d?.calcium ?? null;
    dosificationForm.phosphorus = d?.phosphorus ?? null;
    dosificationForm.iron = d?.iron ?? null;
    dosificationForm.retinol = d?.retinol ?? null;
    dosificationForm.thiamine = d?.thiamine ?? null;
    dosificationForm.riboflavin = d?.riboflavin ?? null;
    dosificationForm.niacin = d?.niacin ?? null;
    dosificationForm.a_asc = d?.a_asc ?? null;
    dosificationForm.sodium = d?.sodium ?? null;
    dosificationForm.potassium = d?.potassium ?? null;
    dosificationForm.magnesium = d?.magnesium ?? null;
    dosificationForm.zinc = d?.zinc ?? null;
    dosificationForm.selenium = d?.selenium ?? null;
    dosificationForm.a_folic = d?.a_folic ?? null;
    dosificationForm.v_b6 = d?.v_b6 ?? null;
    dosificationForm.v_e = d?.v_e ?? null;
    dosificationForm.v_b12 = d?.v_b12 ?? null;
    dosificationForm.v_b9 = d?.v_b9 ?? null;
    dosificationForm.iodine = d?.iodine ?? null;
    dosificationForm.cholesterol = d?.cholesterol ?? null;

    isDosificationModalOpen.value = true;
};

const submitDosification = () => {
    if (!selectedIngredientForDosification.value) return;
    
    dosificationForm.post(route('ingredients.dosification.update', selectedIngredientForDosification.value.id), {
        onSuccess: () => {
            isDosificationModalOpen.value = false;
            Swal.fire({
                title: '¡Guardado!',
                text: 'Los valores nutricionales han sido actualizados.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
};

const filteredIngredients = computed(() => {
    return props.ingredients.filter(ing => 
        ing.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        (ing.ingredient_category?.name.toLowerCase().includes(searchQuery.value.toLowerCase()))
    );
});

const openCreateDialog = () => {
    editingIngredient.value = null;
    form.reset();
    isDialogOpen.value = true;
};

const openEditDialog = (ingredient: Ingredient) => {
    editingIngredient.value = ingredient;
    form.name = ingredient.name;
    form.description = ingredient.description || '';
    form.amount = ingredient.amount;
    form.waste = ingredient.waste;
    form.energy = ingredient.energy;
    form.ingredient_category_id = ingredient.ingredient_category_id;
    isDialogOpen.value = true;
};

const selectedCategoryId = computed({
    get: () => (form.ingredient_category_id ? String(form.ingredient_category_id) : undefined),
    set: (val) => {
        form.ingredient_category_id = val ? Number(val) : null;
    },
});

// Paginación
const currentPage = ref(1);
const itemsPerPage = 20;

const totalPages = computed(() => Math.ceil(filteredIngredients.value.length / itemsPerPage));

const paginatedIngredients = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredIngredients.value.slice(start, end);
});

const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
};

const prevPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--;
    }
};

// Reset page when search changes
watch(searchQuery, () => {
    currentPage.value = 1;
});

const submit = () => {
    if (editingIngredient.value) {
        form.put(route('ingredients.update', editingIngredient.value.id), {
            onSuccess: () => {
                isDialogOpen.value = false;
                Swal.fire({
                    title: '¡Actualizado!',
                    text: 'El ingrediente ha sido actualizado.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    } else {
        form.post(route('ingredients.store'), {
            onSuccess: () => {
                isDialogOpen.value = false;
                Swal.fire({
                    title: '¡Creado!',
                    text: 'El ingrediente ha sido creado.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    }
};

const deleteIngredient = (id: number) => {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "No podrás revertir esto",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('ingredients.destroy', id), {
                onSuccess: () => {
                    Swal.fire({
                        title: 'Eliminado',
                        text: 'El ingrediente ha sido eliminado.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
};

const fileInput = ref<HTMLInputElement | null>(null);
const importForm = useForm({
    excel_file: null as File | null,
});

const triggerImport = () => {
    fileInput.value?.click();
};

const handleFileSelect = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
        importForm.excel_file = input.files[0];
        uploadFile();
    }
};

const uploadFile = () => {
    importForm.post(route('ingredients.import'), {
        onSuccess: () => {
            Swal.fire({
                title: '¡Importado!',
                text: 'Los ingredientes han sido importados correctamente.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
            importForm.reset();
        },
        onError: () => {
            Swal.fire({
                title: 'Error',
                text: 'Hubo un error al intentar importar el archivo.',
                icon: 'error'
            });
        }
    });
};

const energyInput = ref<HTMLInputElement | null>(null);
const energyImportForm = useForm({
    excel_file: null as File | null,
});

const triggerEnergyImport = () => {
    energyInput.value?.click();
};

const handleEnergyFileSelect = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
        energyImportForm.excel_file = input.files[0];
        uploadEnergyFile();
    }
};

const uploadEnergyFile = () => {
    energyImportForm.post(route('ingredients.import-energy'), {
        onSuccess: () => {
            Swal.fire({
                title: '¡Actualizado!',
                text: 'La energía de los ingredientes ha sido actualizada.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
            energyImportForm.reset();
        },
        onError: () => {
            Swal.fire({
                title: 'Error',
                text: 'Hubo un error al intentar importar las energías.',
                icon: 'error'
            });
        }
    });
};
</script>

<template>
    <Head title="Insumos" />
    <AppLayout>
        <div class="space-y-6 p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">Insumos</h1>
                    <p class="text-muted-foreground mt-1">
                        Gestiona los ingredientes y suministros básicos para tus recetas.
                    </p>
                </div>
                <div class="flex flex-col gap-2 md:flex-row md:items-center">
                    <input 
                        type="file" 
                        ref="fileInput" 
                        class="hidden" 
                        accept=".xlsx, .xls, .csv"
                        @change="handleFileSelect"
                    >
                    <Button 
                        variant="outline" 
                        @click="triggerImport" 
                        :disabled="importForm.processing"
                        class="w-full md:w-auto shadow-sm border-zinc-200 hover:bg-zinc-50 font-semibold"
                    >
                        <template v-if="importForm.processing">
                            <Loader2 class="mr-2 h-4 w-4 animate-spin text-primary" />
                            Importando...
                        </template>
                        <template v-else>
                            <Upload class="mr-2 h-4 w-4 text-zinc-500" />
                            Importar
                        </template>
                    </Button>

                    <input 
                        type="file" 
                        ref="energyInput" 
                        class="hidden" 
                        accept=".xlsx, .xls, .csv"
                        @change="handleEnergyFileSelect"
                    >
                    <Button 
                        variant="outline" 
                        @click="triggerEnergyImport" 
                        :disabled="energyImportForm.processing"
                        class="w-full md:w-auto shadow-sm border-zinc-200 hover:bg-orange-50 font-semibold"
                    >
                        <template v-if="energyImportForm.processing">
                            <Loader2 class="mr-2 h-4 w-4 animate-spin text-orange-500" />
                            Actualizando...
                        </template>
                        <template v-else>
                            <Zap class="mr-2 h-4 w-4 text-orange-500" />
                            Importar Energía
                        </template>
                    </Button>

                    <Button @click="openCreateDialog" class="w-full md:w-auto shadow-lg transition-all hover:scale-105">
                        <Plus class="mr-2 h-4 w-4" />
                        Nuevo Ingrediente
                    </Button>
                </div>
            </div>

            <Card class="overflow-hidden border-none shadow-xl bg-card/50 backdrop-blur-sm">
                <CardContent class="p-6">
                    <div class="mb-6 flex items-center gap-4">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input 
                                v-model="searchQuery"
                                placeholder="Buscar por nombre o categoría..." 
                                class="pl-10 h-11 border-zinc-200 focus-visible:ring-primary"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-white overflow-hidden shadow-sm">
                        <Table>
                            <TableHeader class="bg-zinc-50/50">
                                <TableRow>
                                    <TableHead class="w-[30%]">Nombre</TableHead>
                                    <TableHead>Categoría</TableHead>
                                    <TableHead>Cant. Base</TableHead>
                                    <TableHead>Merma (%)</TableHead>
                                    <TableHead>Energía (kcal)</TableHead>
                                    <TableHead class="text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="ingredient in paginatedIngredients" :key="ingredient.id" class="hover:bg-zinc-50/50 transition-colors">
                                    <TableCell class="font-medium">
                                        <div class="flex flex-col">
                                            <span>{{ ingredient.name }}</span>
                                            <span class="text-xs text-muted-foreground font-normal line-clamp-1">{{ ingredient.description }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">
                                            {{ ingredient.ingredient_category?.name || 'Sin categoría' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{{ ingredient.amount || '-' }}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline" class="bg-orange-50 text-orange-700 border-orange-200">
                                            {{ ingredient.waste || 0 }}%
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-1.5 font-medium text-orange-600">
                                            <Flame class="h-3.5 w-3.5" />
                                            {{ ingredient.energy || 0 }}
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <Button variant="ghost" size="icon" @click="openDosificationModal(ingredient)" class="h-8 w-8 text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50" title="Dosificación">
                                                <Calculator class="h-4 w-4" />
                                            </Button>
                                            <Button variant="ghost" size="icon" @click="openEditDialog(ingredient)" class="h-8 w-8 text-blue-600 hover:text-blue-700 hover:bg-blue-50">
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                            <Button variant="ghost" size="icon" @click="deleteIngredient(ingredient.id)" class="h-8 w-8 text-red-600 hover:text-red-700 hover:bg-red-50">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="filteredIngredients.length === 0">
                                    <TableCell colspan="6" class="h-24 text-center">
                                        <div class="flex flex-col items-center justify-center text-muted-foreground">
                                            <Utensils class="h-8 w-8 mb-2 opacity-20" />
                                            <p>No se encontraron ingredientes.</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Controles de Paginación -->
                    <div class="mt-6 flex items-center justify-between border-t border-zinc-100 pt-6" v-if="totalPages > 1">
                        <div class="text-sm text-muted-foreground">
                            Mostrando {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, filteredIngredients.length) }} de {{ filteredIngredients.length }} registros
                        </div>
                        <div class="flex items-center gap-2">
                            <Button variant="outline" size="sm" :disabled="currentPage === 1" @click="prevPage" class="h-9 px-3 rounded-lg border-zinc-200">
                                <ChevronLeft class="h-4 w-4 mr-1" />
                                Anterior
                            </Button>
                            <div class="flex items-center gap-1 mx-2">
                                <template v-for="page in totalPages" :key="page">
                                    <Button
                                        v-if="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)"
                                        size="sm"
                                        :variant="currentPage === page ? 'default' : 'ghost'"
                                        @click="currentPage = page"
                                        class="h-8 w-8 p-0 rounded-md"
                                    >
                                        {{ page }}
                                    </Button>
                                    <span v-else-if="(page === 2 && currentPage > 3) || (page === totalPages - 1 && currentPage < totalPages - 2)" class="px-1 text-muted-foreground">
                                        ...
                                    </span>
                                </template>
                            </div>
                            <Button variant="outline" size="sm" :disabled="currentPage === totalPages" @click="nextPage" class="h-9 px-3 rounded-lg border-zinc-200">
                                Siguiente
                                <ChevronRight class="h-4 w-4 ml-1" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Modal de Formulario -->
        <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
            <DialogContent class="sm:max-w-[550px] overflow-hidden rounded-2xl p-0 border-none shadow-2xl">
                <div class="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-primary/5 pointer-events-none"></div>
                
                <DialogHeader class="p-6 pb-0">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="p-2 bg-primary/10 rounded-lg">
                            <Utensils class="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <DialogTitle class="text-2xl font-bold">{{ editingIngredient ? 'Editar Ingrediente' : 'Nuevo Ingrediente' }}</DialogTitle>
                            <DialogDescription>
                                {{ editingIngredient ? 'Modifica los datos del ingrediente existente.' : 'Registra un nuevo ingrediente en el sistema.' }}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form @submit.prevent="submit" class="p-6 pt-4 space-y-8">
                    <!-- Sección: Información Básica -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="h-4 w-1 bg-primary rounded-full"></div>
                            <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider">Información General</span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2 space-y-2">
                                <Label for="name" class="text-sm font-bold text-zinc-700 flex items-center gap-2">
                                    <Type class="h-4 w-4 text-zinc-400" /> Nombre del Insumo *
                                </Label>
                                <Input id="name" v-model="form.name" placeholder="Ej. Tomate, Pollo, Aceite..." required class="h-11 border-zinc-200 bg-zinc-50/30 focus:bg-white transition-colors" />
                                <div v-if="form.errors.name" class="text-sm text-red-500 font-medium">{{ form.errors.name }}</div>
                            </div>

                            <div class="col-span-2 space-y-2">
                                <Label for="category" class="text-sm font-bold text-zinc-700 flex items-center gap-2">
                                    <LayoutGrid class="h-4 w-4 text-zinc-400" /> Categoría
                                </Label>
                                <Select v-model="selectedCategoryId">
                                    <SelectTrigger id="category" class="h-11 border-zinc-200 bg-zinc-50/30 transition-colors">
                                        <SelectValue placeholder="Seleccionar categoría" />
                                    </SelectTrigger>
                                    <SelectContent class="bg-white border-zinc-200">
                                        <SelectItem v-for="cat in categories" :key="cat.id" :value="String(cat.id)" class="py-2.5">
                                            {{ cat.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <div v-if="form.errors.ingredient_category_id" class="text-sm text-red-500 font-medium">{{ form.errors.ingredient_category_id }}</div>
                            </div>
                        </div>
                    </div>

                    <Separator class="bg-zinc-100" />

                    <!-- Sección: Especificaciones Técnicas (Bento Style) -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="h-4 w-1 bg-primary rounded-full"></div>
                            <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider">Especificaciones y Rendimiento</span>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="amount" class="text-sm font-bold text-zinc-700 uppercase flex items-center gap-1.5">
                                    <Scale class="h-4 w-4 text-zinc-400" /> Cantidad Base
                                </Label>
                                <Input id="amount" type="number" step="0.01" :model-value="form.amount ?? undefined" @update:model-value="val => form.amount = val ? Number(val) : null" placeholder="0.00" class="h-11 border-zinc-200 font-bold text-lg text-primary" />
                            </div>

                            <div class="space-y-2">
                                <Label for="waste" class="text-sm font-bold text-zinc-700 uppercase flex items-center gap-1.5">
                                    <Trash2 class="h-4 w-4 text-zinc-400" /> Merma (%)
                                </Label>
                                <Input id="waste" type="number" step="0.01" :model-value="form.waste ?? undefined" @update:model-value="val => form.waste = val ? Number(val) : null" placeholder="0.00" class="h-11 border-zinc-200" />
                            </div>

                            <div class="col-span-2 space-y-2">
                                <Label for="energy" class="text-sm font-bold text-zinc-700 uppercase flex items-center gap-1.5">
                                    <Flame class="h-4 w-4 text-zinc-400" /> Energía (kcal)
                                </Label>
                                <Input id="energy" type="number" step="0.01" :model-value="form.energy ?? undefined" @update:model-value="val => form.energy = val ? Number(val) : null" placeholder="0.00" class="h-11 border-zinc-200 bg-orange-50/20" />
                            </div>
                        </div>
                    </div>

                    <Separator class="bg-zinc-100" />

                    <!-- Sección: Notas -->
                    <div class="space-y-2">
                        <Label for="description" class="text-sm font-bold text-zinc-700 flex items-center gap-2">
                            <FileText class="h-4 w-4 text-zinc-400" /> Descripción o Notas
                        </Label>
                        <Input id="description" v-model="form.description" placeholder="Información nutricional adicional, origen, etc..." class="h-11 border-zinc-200 bg-zinc-50/30" />
                    </div>

                    <DialogFooter class="pt-4 gap-2">
                        <Button type="button" variant="ghost" @click="isDialogOpen = false" class="h-11 px-6 text-zinc-500 font-bold">
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="form.processing" class="h-11 px-10 rounded-xl shadow-lg shadow-primary/20 transition-all hover:scale-[1.02] active:scale-95 font-bold uppercase tracking-wide text-xs">
                            {{ editingIngredient ? 'Actualizar Registro' : 'Crear Insumo' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
        <!-- Modal de Dosificación -->
        <Dialog :open="isDosificationModalOpen" @update:open="isDosificationModalOpen = $event">
            <DialogContent class="sm:max-w-[850px] max-h-[95vh] flex flex-col overflow-hidden rounded-2xl p-0 border-none shadow-2xl">
                <div class="absolute inset-0 bg-gradient-to-br from-emerald-50/50 via-transparent to-emerald-50/50 pointer-events-none"></div>
                
                <DialogHeader class="p-6 pb-2 border-b border-zinc-100 flex-shrink-0 relative z-10">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="p-2 bg-emerald-100 rounded-lg">
                            <Calculator class="h-5 w-5 text-emerald-600" />
                        </div>
                        <div>
                            <DialogTitle class="text-2xl font-bold">Valores Nutricionales</DialogTitle>
                            <DialogDescription class="flex items-center gap-2">
                                Gestionando dosificación para: <span class="font-bold text-emerald-700">{{ selectedIngredientForDosification?.name }}</span>
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form @submit.prevent="submitDosification" class="flex flex-col flex-1 overflow-hidden relative z-10">
                    <div class="flex-1 overflow-y-auto p-6 pt-4 custom-scrollbar">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <!-- Columna 1: Macronutrientes -->
                            <div class="space-y-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="h-4 w-1 bg-emerald-500 rounded-full"></div>
                                    <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider">Macronutrientes</span>
                                </div>
                                
                                <div class="space-y-3">
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            <Zap class="h-3 w-3" /> Energía (kcal)
                                        </Label>
                                        <Input :model-value="dosificationForm.energy ?? undefined" @update:model-value="val => dosificationForm.energy = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            <Waves class="h-3 w-3" /> Agua (g)
                                        </Label>
                                        <Input :model-value="dosificationForm.water ?? undefined" @update:model-value="val => dosificationForm.water = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            <Beef class="h-3 w-3" /> Proteína (g)
                                        </Label>
                                        <Input :model-value="dosificationForm.protein ?? undefined" @update:model-value="val => dosificationForm.protein = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            <Droplets class="h-3 w-3" /> Lípidos (g)
                                        </Label>
                                        <Input :model-value="dosificationForm.lipid ?? undefined" @update:model-value="val => dosificationForm.lipid = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            <Cookie class="h-3 w-3" /> Carbohidratos (g)
                                        </Label>
                                        <Input :model-value="dosificationForm.carbohydrate ?? undefined" @update:model-value="val => dosificationForm.carbohydrate = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            <Wheat class="h-3 w-3" /> Fibra (g)
                                        </Label>
                                        <Input :model-value="dosificationForm.fiber ?? undefined" @update:model-value="val => dosificationForm.fiber = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                </div>
                            </div>

                            <!-- Columna 2: Minerales -->
                            <div class="space-y-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="h-4 w-1 bg-blue-500 rounded-full"></div>
                                    <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider">Minerales y Cenizas</span>
                                </div>
                                
                                <div class="space-y-3">
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            <Beaker class="h-3 w-3" /> Cenizas (g)
                                        </Label>
                                        <Input :model-value="dosificationForm.ash ?? undefined" @update:model-value="val => dosificationForm.ash = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Calcio (mg)
                                        </Label>
                                        <Input :model-value="dosificationForm.calcium ?? undefined" @update:model-value="val => dosificationForm.calcium = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Fósforo (mg)
                                        </Label>
                                        <Input :model-value="dosificationForm.phosphorus ?? undefined" @update:model-value="val => dosificationForm.phosphorus = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Hierro (mg)
                                        </Label>
                                        <Input :model-value="dosificationForm.iron ?? undefined" @update:model-value="val => dosificationForm.iron = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Sodio (mg)
                                        </Label>
                                        <Input :model-value="dosificationForm.sodium ?? undefined" @update:model-value="val => dosificationForm.sodium = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Potasio (mg)
                                        </Label>
                                        <Input :model-value="dosificationForm.potassium ?? undefined" @update:model-value="val => dosificationForm.potassium = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Magnesio (mg)
                                        </Label>
                                        <Input :model-value="dosificationForm.magnesium ?? undefined" @update:model-value="val => dosificationForm.magnesium = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Zinc (mg)
                                        </Label>
                                        <Input :model-value="dosificationForm.zinc ?? undefined" @update:model-value="val => dosificationForm.zinc = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Selenio (µg)
                                        </Label>
                                        <Input :model-value="dosificationForm.selenium ?? undefined" @update:model-value="val => dosificationForm.selenium = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Yodo (µg)
                                        </Label>
                                        <Input :model-value="dosificationForm.iodine ?? undefined" @update:model-value="val => dosificationForm.iodine = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                </div>
                            </div>

                            <!-- Columna 3: Vitaminas -->
                            <div class="space-y-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="h-4 w-1 bg-amber-500 rounded-full"></div>
                                    <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider">Vitaminas</span>
                                </div>
                                
                                <div class="space-y-3">
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            <FlaskConical class="h-3 w-3" /> Retinol (µg)
                                        </Label>
                                        <Input :model-value="dosificationForm.retinol ?? undefined" @update:model-value="val => dosificationForm.retinol = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Tiamina (mg)
                                        </Label>
                                        <Input :model-value="dosificationForm.thiamine ?? undefined" @update:model-value="val => dosificationForm.thiamine = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Riboflavina (mg)
                                        </Label>
                                        <Input :model-value="dosificationForm.riboflavin ?? undefined" @update:model-value="val => dosificationForm.riboflavin = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Niacina (mg)
                                        </Label>
                                        <Input :model-value="dosificationForm.niacin ?? undefined" @update:model-value="val => dosificationForm.niacin = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Vitamina C (mg)
                                        </Label>
                                        <Input :model-value="dosificationForm.a_asc ?? undefined" @update:model-value="val => dosificationForm.a_asc = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Vitamina E (mg)
                                        </Label>
                                        <Input :model-value="dosificationForm.v_e ?? undefined" @update:model-value="val => dosificationForm.v_e = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Vitamina B6 (mg)
                                        </Label>
                                        <Input :model-value="dosificationForm.v_b6 ?? undefined" @update:model-value="val => dosificationForm.v_b6 = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Vitamina B12 (µg)
                                        </Label>
                                        <Input :model-value="dosificationForm.v_b12 ?? undefined" @update:model-value="val => dosificationForm.v_b12 = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Ácido Fólico (µg)
                                        </Label>
                                        <Input :model-value="dosificationForm.a_folic ?? undefined" @update:model-value="val => dosificationForm.a_folic = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Vitamina B9 (µg)
                                        </Label>
                                        <Input :model-value="dosificationForm.v_b9 ?? undefined" @update:model-value="val => dosificationForm.v_b9 = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                    <div class="space-y-1.5 pt-4">
                                        <div class="h-px bg-zinc-100 w-full mb-4"></div>
                                        <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center gap-2">
                                            Colesterol (mg)
                                        </Label>
                                        <Input :model-value="dosificationForm.cholesterol ?? undefined" @update:model-value="val => dosificationForm.cholesterol = val ? Number(val) : null" type="number" step="0.01" class="h-9 border-zinc-200" placeholder="0.00" />
                                    </div>
                                </div>

                                <div class="pt-6">
                                    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-100">
                                        <div class="flex items-center gap-2 text-emerald-700 mb-1">
                                            <Info class="h-4 w-4" />
                                            <span class="text-xs font-bold uppercase">Nota</span>
                                        </div>
                                        <p class="text-[10px] text-emerald-600 leading-relaxed font-medium">
                                            Los valores deben ingresarse preferiblemente por cada 100g de porción comestible del alimento.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="p-6 border-t border-zinc-100 gap-2 flex-shrink-0 bg-white/50 backdrop-blur-sm relative z-10">
                        <Button type="button" variant="ghost" @click="isDosificationModalOpen = false" class="h-11 px-6 text-zinc-500 font-bold">
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="dosificationForm.processing" class="h-11 px-10 rounded-xl bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-200 transition-all hover:scale-[1.02] active:scale-95 font-bold uppercase tracking-wide text-xs">
                            {{ dosificationForm.processing ? 'Guardando...' : 'Guardar Nutrientes' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
/* Estilos adicionales para un look premium */
:deep(.lucide) {
    stroke-width: 2.5px;
}

.shadow-xl {
    box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
}

.backdrop-blur-sm {
    backdrop-filter: blur(8px);
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
