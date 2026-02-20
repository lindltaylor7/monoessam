<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { Ingredient, Provider } from '@/types';
import { ref, watch, computed } from 'vue';
import { 
    Settings2, 
    Truck, 
    DollarSign, 
    Scale, 
    Flame, 
    Droplets, 
    Trash2, 
    CheckCircle2, 
    Info,
    TrendingUp,
    ChevronRight,
    Calculator
} from 'lucide-vue-next';

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

const updateCalcValues = (e: any) => {
    const val = parseFloat(e.target.value) || 0;
    baseCost.value = priceSelected.value * val;

    ingredientSelected.value.gross_weight = val * 1000;
    
    const amount = parseFloat(ingredientSelected.value.amount) || 1;
    const origWaste = parseFloat(ingredientSelected.value.originalValues?.waste) || 0;
    const origCalories = parseFloat(ingredientSelected.value.originalValues?.calories) || 0;

    ingredientSelected.value.solid_waste = (
        (ingredientSelected.value.gross_weight * origWaste) / 100
    );
    
    finalProduct.value = ingredientSelected.value.gross_weight - ingredientSelected.value.solid_waste;

    ingredientSelected.value.calories = (
        (finalProduct.value * origCalories) / 100
    );

    emits('calcMassiveProperties', ingredientSelected.value.id, [
        ingredientSelected.value.gross_weight,
        ingredientSelected.value.solid_waste,
        ingredientSelected.value.calories,
        baseCost.value,
        finalProduct.value,
        priceSelected.value,
    ]);
};

const selectPrice = (provider: Provider) => {
    priceSelected.value = parseFloat((provider as any).pivot?.cost_price) || 0;
    priceUeGr.value = priceSelected.value / 1000;
};

// Handle provider selection change
watch(providerSelected, (newId) => {
    const provider = ingredientSelected.value.providers?.find((p: any) => p.id === Number(newId));
    if (provider) {
        selectPrice(provider);
    }
});

watch(props, (newValue) => {
    ingredientSelected.value.gross_weight_volume = ((ingredientSelected.value.gross_weight / (newValue.totalMateriaPrima || 1)) * 100).toFixed(2);
    ingredientSelected.value.waste_volume = ((ingredientSelected.value.solid_waste / (newValue.totalWasteWeight || 1)) * 100).toFixed(2);
    ingredientSelected.value.calories_volume = ((ingredientSelected.value.calories / (newValue.totalCalories || 1)) * 100).toFixed(2);
    baseCostPercentage.value = (baseCost.value / (props.totalCost || 1)) * 100;
    finalProductPercentage.value = (finalProduct.value / (props.totalfinalProduct || 1)) * 100;
});
</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
            <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                <Calculator class="h-4 w-4 text-zinc-500 hover:text-primary transition-colors" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-[420px] p-0 overflow-hidden rounded-xl border-none shadow-2xl bg-white" side="right" :side-offset="12">
            <!-- Header Section -->
            <div class="p-5 bg-gradient-to-r from-zinc-50 to-white border-b border-zinc-100">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-50 rounded-lg">
                        <Calculator class="h-5 w-5 text-blue-600" />
                    </div>
                    <div>
                        <h4 class="font-bold text-zinc-900">Ajustes de Ingrediente</h4>
                        <p class="text-[12px] text-zinc-500 font-medium line-clamp-1">{{ ingredientSelected.name }}</p>
                    </div>
                </div>
            </div>

            <div class="p-5 space-y-6">
                <!-- Proveedor y Costos Section -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2 mb-1">
                        <Truck class="h-4 w-4 text-zinc-400" />
                        <span class="text-sm font-bold text-zinc-700 uppercase tracking-wider text-[11px]">Proveedor y Precios</span>
                    </div>
                    
                    <Select :model-value="String(providerSelected)" @update:model-value="val => providerSelected = val as string">
                        <SelectTrigger class="h-11 border-zinc-200 bg-zinc-50/30">
                            <SelectValue placeholder="Seleccionar un proveedor" />
                        </SelectTrigger>
                        <SelectContent class="bg-white border-zinc-200">
                            <SelectGroup>
                                <SelectItem
                                    v-for="provider in ingredientSelected.providers"
                                    :key="provider.id"
                                    :value="String(provider.id)"
                                    class="py-2.5"
                                >
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-medium">{{ provider.name }}</span>
                                        <span class="text-[10px] text-zinc-400">{{ provider.cities?.[0]?.name || 'N/A' }}</span>
                                    </div>
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-bold text-zinc-500 flex items-center gap-1.5 uppercase">
                                <DollarSign class="h-3 w-3" /> Precio Unit.
                            </Label>
                            <div class="relative">
                                <Input :model-value="`S/. ${Number(priceSelected).toFixed(2)}`" readonly class="h-10 bg-zinc-100/50 border-zinc-200 font-bold" />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-bold text-zinc-500 flex items-center gap-1.5 uppercase">
                                Pr. x Gr.
                            </Label>
                            <Input :model-value="`S/. ${Number(priceUeGr).toFixed(4)}`" readonly class="h-10 bg-zinc-100/50 border-zinc-200 font-medium text-zinc-600" />
                        </div>
                    </div>
                </div>

                <Separator class="bg-zinc-100" />

                <!-- Dosificación y Rendimiento Section -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Scale class="h-4 w-4 text-zinc-400" />
                            <span class="text-sm font-bold text-zinc-700 uppercase tracking-wider text-[11px]">Cálculo de Insumo</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-bold text-zinc-500 uppercase">Cantidad</Label>
                            <Input 
                                v-model="ingredientSelected.quantity" 
                                @input="updateCalcValues" 
                                type="number" 
                                step="0.01" 
                                class="h-11 border-zinc-200 focus:ring-2 focus:ring-blue-500 text-lg font-bold"
                                placeholder="0.00"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-bold text-zinc-500 uppercase flex items-center justify-between">
                                Costo Base
                                <span class="text-[10px] lowercase text-zinc-400 font-normal">({{ Number(baseCostPercentage).toFixed(1) }}%)</span>
                            </Label>
                            <div class="h-11 flex items-center px-3 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-700 font-bold text-lg">
                                S/. {{ Number(baseCost).toFixed(2) }}
                            </div>
                        </div>
                    </div>

                    <!-- Grilla de Materia Prima y Rendimiento -->
                    <div class="grid grid-cols-2 gap-3 p-3 rounded-xl bg-zinc-50 border border-zinc-100">
                        <!-- Materia Prima -->
                        <div class="space-y-1 border-r border-zinc-200 pr-3">
                            <div class="flex items-center justify-between text-[10px]">
                                <span class="text-zinc-500 font-bold uppercase tracking-tighter">Mat. Prima</span>
                                <span class="text-zinc-400 font-medium">{{ ingredientSelected.gross_weight_volume }}%</span>
                            </div>
                            <div class="font-bold text-zinc-800">{{ ingredientSelected.gross_weight }}g</div>
                        </div>
                        
                        <!-- Prod Final -->
                        <div class="space-y-1 pl-1">
                            <div class="flex items-center justify-between text-[10px]">
                                <span class="text-blue-600 font-bold uppercase tracking-tighter">Prod. Final</span>
                                <span class="text-blue-400 font-medium">{{ Number(finalProductPercentage).toFixed(1) }}%</span>
                            </div>
                            <div class="font-extrabold text-blue-700">{{ Number(finalProduct).toFixed(2) }}g</div>
                        </div>
                    </div>
                </div>

                <!-- Mermas y Nutrición Section -->
                <div class="grid grid-cols-2 gap-3">
                    <!-- Merma -->
                    <div class="p-2.5 rounded-lg border border-orange-100 bg-orange-50/50 space-y-1">
                        <div class="flex items-center gap-1.5 text-orange-600">
                            <Trash2 class="h-3 w-3" />
                            <span class="text-[9px] font-bold uppercase">Merma Tot.</span>
                        </div>
                        <div class="text-[13px] font-bold text-orange-700">{{ Number(ingredientSelected.solid_waste).toFixed(2) }}g ({{ ingredientSelected.originalValues?.waste }}%)</div>
                    </div>

                    <!-- Calorías -->
                    <div class="p-2.5 rounded-lg border border-rose-100 bg-rose-50/50 space-y-1">
                        <div class="flex items-center gap-1.5 text-rose-600">
                            <Flame class="h-3 w-3" />
                            <span class="text-[9px] font-bold uppercase">Calories</span>
                        </div>
                        <div class="text-[13px] font-bold text-rose-700">{{ parseFloat(ingredientSelected.calories).toFixed(0) }} <span class="text-[8px] font-normal">kcal</span></div>
                    </div>
                </div>
            </div>

            <!-- Footer Section -->
            <div class="px-5 py-4 bg-zinc-50 border-t border-zinc-100 flex items-center justify-between">
                <Button variant="ghost" size="sm" class="text-zinc-500 font-bold text-[11px] uppercase tracking-wide px-0 hover:bg-transparent hover:text-zinc-800">
                    Restablecer
                </Button>
                <div class="flex gap-2">
                    <Button variant="ghost" size="sm" class="h-8 rounded-lg text-zinc-500 font-medium">Cerrar</Button>
                    <Button size="sm" class="h-8 px-5 rounded-lg shadow-sm font-bold bg-zinc-900 hover:bg-zinc-800 text-[11px] uppercase tracking-wider">
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

