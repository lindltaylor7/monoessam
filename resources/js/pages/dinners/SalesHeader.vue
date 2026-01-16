<script setup lang="ts">
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Cafe, Service, Unit } from '@/types';
import { ref, watch } from 'vue';
import DatePicker from './DatePicker.vue';
import DinnersTable from './DinnersTable.vue';
import ExcelDialog from './ExcelDialog.vue';
import NewDinnerDialog from './NewDinnerDialog.vue';
import OtherUnitDialog from './OtherUnitDialog.vue';
import ReportDialog from './ReportDialog.vue';
import Icon from '@/components/Icon.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Settings } from 'lucide-vue-next';

interface Props {
    cafes: Cafe[];
    services: any[];
    receipt_types: any[];
    sale_types: any[];
    subdealerships: any[];
    units: Unit[]
}

const emits = defineEmits(['showServicesFromCafeSelected', 'updateDate', 'updateFormData', 'addServiceSelected']);

const props = defineProps<Props>();
const doublePrice = ref(false);

const cafeSelected = ref<number>(0);
const saletypeSelected = ref<number>(0);
const servicesSelected = ref<any[]>([]);
const salesSelected = ref<any[]>([]);
const receiptType = ref<number>(0);
const showOtherUnitDialog = ref(false);

const emitFormData = () => {
    emits('updateFormData', {
        receipt_type_id: receiptType.value,
        sale_type_id: saletypeSelected.value,
        cafe_id: cafeSelected.value,
    });
};

const doublePriceSave = () => {
    doublePrice.value = true;
    showOtherUnitDialog.value = false;
};

watch(cafeSelected, (newVal) => {
    const found = props.cafes.find((cafe) => cafe.id === newVal) as any;
    if (found) {
        servicesSelected.value = found.services || [];
        salesSelected.value = found.sales || [];
        emits('showServicesFromCafeSelected', servicesSelected.value, salesSelected.value);
    } else {
        servicesSelected.value = [];
        emits('showServicesFromCafeSelected', servicesSelected.value);
    }
});

watch([receiptType, saletypeSelected, cafeSelected], () => {
    emitFormData();
});

const updateDate = (date: string) => {
    emits('updateDate', date);
};

const addServiceSelected = (service: Service) => {
    emits('addServiceSelected', service);
};
</script>

<template>
    <div class="space-y-6">
        <!-- Configuration Card -->
        <Card class="border-none shadow-sm bg-white overflow-hidden py-0">
            <div class="bg-red-600 px-6 py-4 flex items-center gap-3">
                <Settings class="text-white"/>
                <h3 class="text-white font-bold tracking-tight text-sm uppercase">Configuración de Venta</h3>
            </div>
            
            <CardContent class="p-6 space-y-6">
                <!-- Selectores de Tipo -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Tipo de Documento -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-[11px] font-bold text-slate-400 uppercase tracking-widest">
                            <Icon name="file-text" size="12" class="text-blue-500" />
                            Documento
                        </label>
                        <ToggleGroup v-model="receiptType" type="single" class="flex flex-wrap gap-1 bg-slate-50 p-1 rounded-xl">
                            <ToggleGroupItem
                                v-for="type in receipt_types"
                                :value="type.id"
                                :key="type.id"
                                class="flex-1 h-9 rounded-lg text-xs font-bold transition-all data-[state=on]:bg-blue-600 data-[state=on]:text-white data-[state=on]:shadow-md"
                            >
                                {{ type.name }}
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>

                    <!-- Tipo de Venta -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-[11px] font-bold text-slate-400 uppercase tracking-widest">
                            <Icon name="tag" size="12" class="text-purple-500" />
                            Categoría
                        </label>
                        <ToggleGroup v-model="saletypeSelected" type="single" class="flex flex-wrap gap-1 bg-slate-50 p-1 rounded-xl">
                            <ToggleGroupItem
                                v-for="type in sale_types"
                                :value="type.id"
                                :key="type.id"
                                class="flex-1 h-9 rounded-lg text-xs font-bold transition-all data-[state=on]:bg-purple-600 data-[state=on]:text-white data-[state=on]:shadow-md"
                            >
                                {{ type.name }}
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>
                </div>

                <!-- Selector de Cafetería -->
                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-[11px] font-bold text-slate-400 uppercase tracking-widest">
                        <Icon name="map-pin" size="12" class="text-amber-500" />
                        Punto de Servicio (Unidad / Cafetería)
                    </label>
                    
                    <div class="space-y-4 max-h-[350px] overflow-y-auto pr-2 custom-scrollbar">
                        <div v-for="unit in units" :key="unit.id" class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-black uppercase text-slate-900 bg-slate-100 px-2 py-0.5 rounded">
                                    {{ unit.name }}
                                </span>
                                <div class="h-[1px] flex-1 bg-slate-100"></div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="cafe in unit.cafes"
                                    :key="cafe.id"
                                    @click="cafeSelected = cafe.id"
                                    class="text-left px-3 py-2.5 rounded-xl text-xs font-bold border transition-all flex items-center justify-between group"
                                    :class="cafeSelected === cafe.id 
                                        ? 'bg-amber-500 border-amber-500 text-white shadow-lg shadow-amber-200' 
                                        : 'bg-white border-slate-200 text-slate-600 hover:border-amber-300 hover:bg-amber-50'"
                                >
                                    <span class="truncate">{{ cafe.name }}</span>
                                    <Icon v-if="cafeSelected === cafe.id" name="check-circle" size="14" />
                                    <Icon v-else name="coffee" size="14" class="text-slate-300 group-hover:text-amber-500 transition-colors" />
                                </button>
                            </div>
                            <p v-if="unit.cafes.length === 0" class="text-[10px] italic text-slate-400 pl-2">Sin cafeterías asignadas</p>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 grid grid-cols-2 gap-3">
                    <DatePicker @updateDate="updateDate" class="w-full h-11" />
                    <NewDinnerDialog :cafes="cafes" :subdealerships="subdealerships" />
                </div>
            </CardContent>
        </Card>

        <!-- Services Selection Card -->
        <Card class="border-none shadow-sm bg-white overflow-hidden py-0">
            <div class="bg-emerald-600 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3 text-white">
                    <Icon name="utensils" class="h-5 w-5" />
                    <h3 class="font-bold tracking-tight text-sm uppercase">Servicios Disponibles</h3>
                </div>
                <Badge variant="outline" class="bg-white/20 text-white border-white/20 font-bold border-none rounded p-1">{{ servicesSelected.length }}</Badge>
            </div>
            <CardContent class="p-0">
                <DinnersTable :services="servicesSelected" @addServiceSelected="addServiceSelected" />
            </CardContent>
        </Card>

        <!-- Quick Actions -->
        <div class="grid grid-cols-2 gap-4">
            <ExcelDialog class="w-full" />
            <ReportDialog class="w-full" />
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
</style>
