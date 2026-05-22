<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Card, CardContent } from '@/components/ui/card';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Cafe, Service, Unit } from '@/types';
import { Settings } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import DatePicker from './DatePicker.vue';
import DinnersTable from './DinnersTable.vue';
import ReportDialog from './ReportDialog.vue';

interface Props {
    cafes: Cafe[];
    services: any[];
    receipt_types: any[];
    sale_types: any[];
    subdealerships: any[];
    units: Unit[];
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
        emits('showServicesFromCafeSelected', servicesSelected.value, newVal);
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
        <Card class="overflow-hidden border-none bg-white py-0 shadow-sm">
            <div class="flex items-center gap-3 bg-red-600 px-6 py-4">
                <Settings class="text-white" />
                <h3 class="text-sm font-bold tracking-tight text-white uppercase">Configuración de Venta</h3>
            </div>

            <CardContent class="space-y-6 p-6">
                <!-- Selectores de Tipo -->
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Tipo de Documento -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-[11px] font-bold tracking-widest text-slate-400 uppercase">
                            <Icon name="file-text" size="12" class="text-blue-500" />
                            Documento
                        </label>
                        <ToggleGroup v-model="receiptType" type="single" class="flex flex-wrap gap-1 rounded-xl bg-slate-50 p-1">
                            <ToggleGroupItem
                                v-for="type in receipt_types"
                                :value="type.id"
                                :key="type.id"
                                class="h-9 flex-1 rounded-lg text-xs font-bold transition-all data-[state=on]:bg-blue-600 data-[state=on]:text-white data-[state=on]:shadow-md"
                            >
                                {{ type.name }}
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>

                    <!-- Tipo de Venta -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-[11px] font-bold tracking-widest text-slate-400 uppercase">
                            <Icon name="tag" size="12" class="text-purple-500" />
                            Categoría
                        </label>
                        <ToggleGroup v-model="saletypeSelected" type="single" class="flex flex-wrap gap-1 rounded-xl bg-slate-50 p-1">
                            <ToggleGroupItem
                                v-for="type in sale_types"
                                :value="type.id"
                                :key="type.id"
                                class="h-9 flex-1 rounded-lg text-xs font-bold transition-all data-[state=on]:bg-purple-600 data-[state=on]:text-white data-[state=on]:shadow-md"
                            >
                                {{ type.name }}
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>
                </div>

                <!-- Selector de Cafetería -->
                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-[11px] font-bold tracking-widest text-slate-400 uppercase">
                        <Icon name="map-pin" size="12" class="text-amber-500" />
                        Punto de Servicio (Unidad / Cafetería)
                    </label>

                    <div class="custom-scrollbar max-h-[350px] space-y-4 overflow-y-auto pr-2">
                        <div v-for="unit in units" :key="unit.id" class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-black text-slate-900 uppercase">
                                    {{ unit.name }}
                                </span>
                                <div class="h-[1px] flex-1 bg-slate-100"></div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="cafe in unit.cafes"
                                    :key="cafe.id"
                                    @click="cafeSelected = cafe.id"
                                    class="group flex items-center justify-between rounded-xl border px-3 py-2.5 text-left text-xs font-bold transition-all"
                                    :class="
                                        cafeSelected === cafe.id
                                            ? 'border-amber-500 bg-amber-500 text-white shadow-lg shadow-amber-200'
                                            : 'border-slate-200 bg-white text-slate-600 hover:border-amber-300 hover:bg-amber-50'
                                    "
                                >
                                    <span class="truncate">{{ cafe.name }}</span>
                                    <Icon v-if="cafeSelected === cafe.id" name="check-circle" size="14" />
                                    <Icon v-else name="coffee" size="14" class="text-slate-300 transition-colors group-hover:text-amber-500" />
                                </button>
                            </div>
                            <p v-if="unit.cafes.length === 0" class="pl-2 text-[10px] text-slate-400 italic">Sin cafeterías asignadas</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 border-t border-slate-100 pt-4">
                    <DatePicker @updateDate="updateDate" class="h-11 w-full" />
                    <!-- <NewDinnerDialog :cafes="cafes" :subdealerships="subdealerships" /> -->
                </div>
            </CardContent>
        </Card>

        <!-- Services Selection Card -->
        <Card class="overflow-hidden border-none bg-white py-0 shadow-sm">
            <div class="flex items-center justify-between bg-emerald-600 px-6 py-4">
                <div class="flex items-center gap-3 text-white">
                    <Icon name="utensils" class="h-5 w-5" />
                    <h3 class="text-sm font-bold tracking-tight uppercase">Servicios Disponibles</h3>
                </div>
                <Badge variant="outline" class="rounded border-none border-white/20 bg-white/20 p-1 font-bold text-white">{{
                    servicesSelected.length
                }}</Badge>
            </div>
            <CardContent class="p-0">
                <DinnersTable :services="servicesSelected" @addServiceSelected="addServiceSelected" />
            </CardContent>
        </Card>

        <!-- Quick Actions -->
        <div class="grid grid-cols-2 gap-4">
            <!-- <ExcelDialog class="w-full" /> -->
            <ReportDialog class="w-full" :units="units" :sale_types="sale_types" />
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
