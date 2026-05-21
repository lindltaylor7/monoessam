<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import axios from 'axios';
import { Calculator, DollarSign, Flame, MapPin, Scale, Trash2, Truck } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const isOpen = ref(false);

const props = defineProps<{
    ingredient: any; // Using any to avoid TS errors with dynamic properties like gross_weight
    totalMateriaPrima: number;
    totalWasteWeight: number;
    totalCalories: number;
    totalCost: number;
    totalfinalProduct: number;
}>();

const emits = defineEmits(['calcMassiveProperties']);

const ingredientSelected = ref(props.ingredient);
const providerSelected = ref<string | number>(0);
const priceSelected = ref(0.0);
const priceUeGr = ref(0.0);
const baseCost = ref(0.0);
const baseCostPercentage = ref(0.0);
const finalProduct = ref(0.0);
const finalProductPercentage = ref(0.0);
const substitutes = ref<any[]>([]);
const loadingSubstitutes = ref(false);

const recalculateAndEmit = () => {
    const val = parseFloat(ingredientSelected.value.input_quantity) || 0;

    // Weight conversion logic consistent with Quebrados.vue
    const weightInGrams = ingredientSelected.value.selected_unit === 'Kg' ? val * 1000 : val;
    ingredientSelected.value.gross_weight = weightInGrams;

    const origWaste = parseFloat(ingredientSelected.value.originalValues?.waste) || 0;
    const origCalories = parseFloat(ingredientSelected.value.originalValues?.calories) || 0;

    ingredientSelected.value.solid_waste = (ingredientSelected.value.gross_weight * origWaste) / 100;
    finalProduct.value = ingredientSelected.value.gross_weight - ingredientSelected.value.solid_waste;
    ingredientSelected.value.final_product = finalProduct.value;

    ingredientSelected.value.calories = (finalProduct.value * origCalories) / 100;

    // Base cost calculation
    baseCost.value = priceUeGr.value * weightInGrams;

    emits('calcMassiveProperties', ingredientSelected.value.id, [
        ingredientSelected.value.gross_weight,
        ingredientSelected.value.solid_waste,
        ingredientSelected.value.calories,
        baseCost.value,
        finalProduct.value,
        priceSelected.value,
    ]);
};

const updateCalcValues = (e: any) => {
    ingredientSelected.value.input_quantity = parseFloat(e.target.value) || 0;
    recalculateAndEmit();
};

// Handle assignment selection change
watch(providerSelected, (newId) => {
    if (!newId || newId === 'none' || newId === '0') {
        priceSelected.value = 0;
        priceUeGr.value = 0;
    } else {
        let assignment = ingredientSelected.value.assignments?.find((a: any) => a.id === Number(newId));
        if (!assignment) {
            assignment = substitutes.value.find((a: any) => a.id === Number(newId));
        }
        if (assignment) {
            priceSelected.value = parseFloat(assignment.cost_price) || 0;
            priceUeGr.value = priceSelected.value / 1000;
        }
    }
    recalculateAndEmit();
});

watch(props, (newValue) => {
    ingredientSelected.value.gross_weight_volume = ((ingredientSelected.value.gross_weight / (newValue.totalMateriaPrima || 1)) * 100).toFixed(2);
    ingredientSelected.value.waste_volume = ((ingredientSelected.value.solid_waste / (newValue.totalWasteWeight || 1)) * 100).toFixed(2);
    ingredientSelected.value.calories_volume = ((ingredientSelected.value.calories / (newValue.totalCalories || 1)) * 100).toFixed(2);
    baseCostPercentage.value = (baseCost.value / (props.totalCost || 1)) * 100;
    finalProductPercentage.value = (finalProduct.value / (props.totalfinalProduct || 1)) * 100;
});

const loadSubstitutes = async (isOpen: boolean) => {
    if (isOpen && substitutes.value.length === 0 && ingredientSelected.value?.id) {
        loadingSubstitutes.value = true;
        try {
            const response = await axios.get(route('ingredients.substitutes', ingredientSelected.value.id));
            substitutes.value = response.data;
        } catch (error) {
            console.error('Error loading substitutes:', error);
        } finally {
            loadingSubstitutes.value = false;
        }
    }
};
</script>

<template>
    <Popover v-model:open="isOpen" @update:open="loadSubstitutes">
        <PopoverTrigger as-child>
            <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800">
                <Calculator class="hover:text-primary h-4 w-4 text-zinc-500 transition-colors" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-[420px] overflow-hidden rounded-xl border-none bg-white p-0 shadow-2xl" side="right" :side-offset="12">
            <!-- Header Section -->
            <div class="border-b border-zinc-100 bg-gradient-to-r from-zinc-50 to-white p-5">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-blue-50 p-2">
                        <Calculator class="h-5 w-5 text-blue-600" />
                    </div>
                    <div>
                        <h4 class="font-bold text-zinc-900">Ajustes de Ingrediente</h4>
                        <p class="line-clamp-1 text-[12px] font-medium text-zinc-500">{{ ingredientSelected.name }}</p>
                    </div>
                </div>
            </div>

            <div class="space-y-6 p-5">
                <!-- Proveedor y Costos Section -->
                <div class="space-y-4">
                    <div class="mb-1 flex items-center gap-2">
                        <Truck class="h-4 w-4 text-zinc-400" />
                        <span class="text-sm text-[11px] font-bold tracking-wider text-zinc-700 uppercase">Proveedor y Precios</span>
                    </div>

                    <Select :model-value="String(providerSelected)" @update:model-value="(val) => (providerSelected = val as string)">
                        <SelectTrigger class="h-11 border-zinc-200 bg-zinc-50/30">
                            <SelectValue placeholder="Seleccionar un proveedor" />
                        </SelectTrigger>
                        <SelectContent class="border-zinc-200 bg-white">
                            <SelectGroup>
                                <SelectItem value="none" class="py-2.5 text-zinc-400 italic"> Ninguno / Precio Manual </SelectItem>
                                <Separator v-if="ingredientSelected.assignments?.length" class="my-1" />
                                <SelectItem
                                    v-for="assignment in ingredientSelected.assignments"
                                    :key="assignment.id"
                                    :value="String(assignment.id)"
                                    class="py-2.5"
                                >
                                    <div class="flex flex-col gap-0.5" v-if="assignment.provider">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-medium">{{ assignment.provider.name }}</span>
                                            <Badge
                                                v-if="assignment.cost_price"
                                                variant="outline"
                                                class="border-green-100 bg-green-50 px-1 py-0 text-[9px] text-green-700"
                                            >
                                                S/. {{ assignment.cost_price }}
                                            </Badge>
                                        </div>
                                        <div class="flex items-center gap-1 text-[10px] text-zinc-400" v-if="assignment.city">
                                            <MapPin class="h-3 w-3" />
                                            <span>{{ assignment.city.name }}</span>
                                        </div>
                                    </div>
                                </SelectItem>

                                <template v-if="loadingSubstitutes">
                                    <div class="py-3 text-center text-xs text-zinc-400">Buscando sugerencias...</div>
                                </template>

                                <template v-if="!loadingSubstitutes && substitutes.length > 0">
                                    <SelectLabel
                                        class="mt-2 border-y border-zinc-100 bg-zinc-50/50 px-2 py-1.5 text-[10px] font-bold tracking-wider text-zinc-500 uppercase"
                                        >Sugerencias de sustitutos</SelectLabel
                                    >
                                    <SelectItem
                                        v-for="assignment in substitutes"
                                        :key="'sub_' + assignment.id"
                                        :value="String(assignment.id)"
                                        class="py-2.5"
                                    >
                                        <div class="flex flex-col gap-0.5" v-if="assignment.provider">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-medium text-zinc-700">{{ assignment.provider.name }}</span>
                                                <Badge
                                                    v-if="assignment.cost_price"
                                                    variant="outline"
                                                    class="border-blue-100 bg-blue-50 px-1 py-0 text-[9px] text-blue-700"
                                                >
                                                    S/. {{ assignment.cost_price }}
                                                </Badge>
                                            </div>
                                            <div class="mt-0.5 flex items-center justify-between text-[10px] text-zinc-400">
                                                <div class="flex items-center gap-1" v-if="assignment.city">
                                                    <MapPin class="h-3 w-3" />
                                                    <span>{{ assignment.city.name }}</span>
                                                </div>
                                                <span class="max-w-[120px] truncate text-zinc-400 italic" :title="assignment.ingredient_name"
                                                    >De: {{ assignment.ingredient_name }}</span
                                                >
                                            </div>
                                        </div>
                                    </SelectItem>
                                </template>
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label class="flex items-center gap-1.5 text-[11px] font-bold text-zinc-500 uppercase">
                                <DollarSign class="h-3 w-3" /> Precio Unit.
                            </Label>
                            <div class="relative">
                                <Input
                                    :model-value="`S/. ${Number(priceSelected).toFixed(2)}`"
                                    readonly
                                    class="h-10 border-zinc-200 bg-zinc-100/50 font-bold"
                                />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label class="flex items-center gap-1.5 text-[11px] font-bold text-zinc-500 uppercase"> Pr. x Gr. </Label>
                            <Input
                                :model-value="`S/. ${Number(priceUeGr).toFixed(4)}`"
                                readonly
                                class="h-10 border-zinc-200 bg-zinc-100/50 font-medium text-zinc-600"
                            />
                        </div>
                    </div>
                </div>

                <Separator class="bg-zinc-100" />

                <!-- Dosificación y Rendimiento Section -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Scale class="h-4 w-4 text-zinc-400" />
                            <span class="text-sm text-[11px] font-bold tracking-wider text-zinc-700 uppercase">Cálculo de Insumo</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-bold text-zinc-500 uppercase">Cantidad</Label>
                            <Input
                                v-model="ingredientSelected.input_quantity"
                                @input="updateCalcValues"
                                type="number"
                                step="0.01"
                                class="h-11 border-zinc-200 text-lg font-bold focus:ring-2 focus:ring-blue-500"
                                placeholder="0.00"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="flex items-center justify-between text-[11px] font-bold text-zinc-500 uppercase">
                                Costo Base
                                <span class="text-[10px] font-normal text-zinc-400 lowercase">({{ Number(baseCostPercentage).toFixed(1) }}%)</span>
                            </Label>
                            <div
                                class="flex h-11 items-center rounded-lg border border-emerald-100 bg-emerald-50 px-3 text-lg font-bold text-emerald-700"
                            >
                                S/. {{ Number(baseCost).toFixed(2) }}
                            </div>
                        </div>
                    </div>

                    <!-- Grilla de Materia Prima y Rendimiento -->
                    <div class="grid grid-cols-2 gap-3 rounded-xl border border-zinc-100 bg-zinc-50 p-3">
                        <!-- Materia Prima -->
                        <div class="space-y-1 border-r border-zinc-200 pr-3">
                            <div class="flex items-center justify-between text-[10px]">
                                <span class="font-bold tracking-tighter text-zinc-500 uppercase">Mat. Prima</span>
                                <span class="font-medium text-zinc-400">{{ ingredientSelected.gross_weight_volume }}%</span>
                            </div>
                            <div class="font-bold text-zinc-800">{{ ingredientSelected.gross_weight }}g</div>
                        </div>

                        <!-- Prod Final -->
                        <div class="space-y-1 pl-1">
                            <div class="flex items-center justify-between text-[10px]">
                                <span class="font-bold tracking-tighter text-blue-600 uppercase">Prod. Final</span>
                                <span class="font-medium text-blue-400">{{ Number(finalProductPercentage).toFixed(1) }}%</span>
                            </div>
                            <div class="font-extrabold text-blue-700">{{ Number(finalProduct).toFixed(2) }}g</div>
                        </div>
                    </div>
                </div>

                <!-- Mermas y Nutrición Section -->
                <div class="grid grid-cols-2 gap-3">
                    <!-- Merma -->
                    <div class="space-y-1 rounded-lg border border-orange-100 bg-orange-50/50 p-2.5">
                        <div class="flex items-center gap-1.5 text-orange-600">
                            <Trash2 class="h-3 w-3" />
                            <span class="text-[9px] font-bold uppercase">Merma Tot.</span>
                        </div>
                        <div class="text-[13px] font-bold text-orange-700">
                            {{ Number(ingredientSelected.solid_waste).toFixed(2) }}g ({{ ingredientSelected.originalValues?.waste }}%)
                        </div>
                    </div>

                    <!-- Calorías -->
                    <div class="space-y-1 rounded-lg border border-rose-100 bg-rose-50/50 p-2.5">
                        <div class="flex items-center gap-1.5 text-rose-600">
                            <Flame class="h-3 w-3" />
                            <span class="text-[9px] font-bold uppercase">Calories</span>
                        </div>
                        <div class="text-[13px] font-bold text-rose-700">
                            {{ parseFloat(ingredientSelected.calories).toFixed(0) }} <span class="text-[8px] font-normal">kcal</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Section -->
            <div class="flex items-center justify-between border-t border-zinc-100 bg-zinc-50 px-5 py-4">
                <Button
                    variant="ghost"
                    size="sm"
                    class="px-0 text-[11px] font-bold tracking-wide text-zinc-500 uppercase hover:bg-transparent hover:text-zinc-800"
                >
                    Restablecer
                </Button>
                <div class="flex gap-2">
                    <Button variant="ghost" size="sm" class="h-8 rounded-lg font-medium text-zinc-500" @click="isOpen = false">Cerrar</Button>
                    <Button
                        size="sm"
                        class="h-8 rounded-lg bg-zinc-900 px-5 text-[11px] font-bold tracking-wider uppercase shadow-sm hover:bg-zinc-800"
                        @click="isOpen = false"
                    >
                        Aplicar
                    </Button>
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>

<style scoped>
/* Transición suave para el popover focus */
:deep(input:focus) {
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

.text-zinc-900 {
    color: #18181b;
}

.tracking-wider {
    letter-spacing: 0.05em;
}
</style>
