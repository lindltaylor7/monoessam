<script setup lang="ts">
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Cafe, Service, Unit } from '@/types';
import { Coffee, File, Tag } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import DatePicker from './DatePicker.vue';
import DinnersTable from './DinnersTable.vue';
import ExcelDialog from './ExcelDialog.vue';
import NewDinnerDialog from './NewDinnerDialog.vue';
import OtherUnitDialog from './OtherUnitDialog.vue';
import ReportDialog from './ReportDialog.vue';

interface Props {
    cafes: Cafe[];
    services: any[];
    receipt_types: any[];
    sale_types: any[];
    subdealerships: any[];
    units: Unit[]
}

interface SaleFormData {
    receipt_type_id: number;
    sale_type_id: number;
    cafe_id: number;
    date: string;
}

const emits = defineEmits(['showServicesFromCafeSelected', 'updateDate', 'updateFormData', 'addServiceSelected']);

const emitFormData = () => {
    const formData: SaleFormData = {
        receipt_type_id: receiptType.value,
        sale_type_id: saletypeSelected.value,
        cafe_id: cafeSelected.value,
    };
    emits('updateFormData', formData);
};

const props = defineProps<Props>();
const doublePrice = ref(false);

const cafeSelected = ref(0);
const saletypeSelected = ref(0);
const servicesSelected = ref([]);
const salesSelected = ref([]);
const receiptType = ref(0);
const showOtherUnitDialog = ref(false);

const doublePriceSave = () => {
    doublePrice.value = true;
    showOtherUnitDialog.value = false;
};

watch(cafeSelected, (newVal) => {
    const cafeSelected = props.cafes.find((cafe) => cafe.id === newVal);
    console.log(cafeSelected);
    if (cafeSelected) {
        servicesSelected.value = cafeSelected.services;
        salesSelected.value = cafeSelected.sales;
        emits('showServicesFromCafeSelected', servicesSelected.value, salesSelected.value);
    } else {
        servicesSelected.value = [];
        emits('showServicesFromCafeSelected', servicesSelected.value);
    }
});

watch([receiptType, saletypeSelected, cafeSelected], () => {
    emitFormData();
});

const updateDate = (date) => {
    emits('updateDate', date);
};

const addServiceSelected = (service: Service) => {
    emits('addServiceSelected', service);
};
</script>

<template>
    <header class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-md">
        <!-- Fila superior: Tres columnas simétricas -->
        <div class="flex flex-col gap-3 md:flex-row">
            <!-- Columna izquierda: Selectores apilados -->
            <div class="min-w-0 flex-1 space-y-3">
                <!-- Selector de Tipo de Documento -->
                <div class="space-y-1">
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <File class="h-4 w-4 text-blue-500" />
                        <span>Tipo de Documento</span>
                    </label>
                    <ToggleGroup v-model="receiptType" type="single" class="flex flex-wrap gap-1 rounded-lg bg-gray-50 p-1">
                        <ToggleGroupItem
                            v-for="receipt_type in receipt_types"
                            :value="receipt_type.id"
                            :key="receipt_type.id"
                            class="min-w-[100px] flex-1 rounded-md px-3 py-2 text-center text-sm transition-all hover:bg-blue-50 hover:text-blue-600 data-[state=on]:bg-blue-500 data-[state=on]:text-white"
                        >
                            {{ receipt_type.name }}
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>

                <!-- Selector de Tipo de Venta -->
                <div class="space-y-1">
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <Tag class="h-4 w-4 text-purple-500" />
                        <span>Tipo de Venta</span>
                    </label>
                    <ToggleGroup v-model="saletypeSelected" type="single" class="flex flex-wrap gap-1 rounded-lg bg-gray-50 p-1">
                        <ToggleGroupItem
                            v-for="sale_type in sale_types"
                            :value="sale_type.id"
                            :key="sale_type.id"
                            class="min-w-[100px] flex-1 rounded-md px-3 py-2 text-center text-sm transition-all hover:bg-purple-50 hover:text-purple-600 data-[state=on]:bg-purple-500 data-[state=on]:text-white"
                        >
                            {{ sale_type.name }}
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>

                <!-- Selector de Cafetería -->
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <Coffee class="h-4 w-4 text-amber-500" />
                        <span>Cafetería por Unidad</span>
                    </label>
                    <div class="space-y-4 max-h-[300px] overflow-y-auto pr-1">
                        <div v-for="unit in units" :key="unit.id" class="space-y-1.5">
                            <span class="text-[10px] font-bold uppercase text-gray-400 tracking-widest flex items-center gap-2 px-1">
                                {{ unit.name }}
                                <div class="h-[1px] flex-1 bg-gray-100"></div>
                            </span>
                            <ToggleGroup v-model="cafeSelected" type="single" class="flex flex-wrap gap-1 rounded-lg bg-gray-50/50 p-1">
                                <ToggleGroupItem
                                    v-for="cafe in unit.cafes"
                                    :value="cafe.id"
                                    :key="cafe.id"
                                    class="min-w-[120px] flex-1 rounded-md px-2 py-2 text-center text-sm border border-transparent transition-all hover:bg-amber-50 hover:text-amber-600 data-[state=on]:border-amber-200 data-[state=on]:bg-amber-500 data-[state=on]:text-white data-[state=on]:shadow-sm"
                                >
                                    <span class="truncate">{{ cafe.name }}</span>
                                </ToggleGroupItem>
                            </ToggleGroup>
                            <p v-if="unit.cafes.length === 0" class="text-[10px] italic text-gray-400 px-2 leading-none">Sin cafeterías asignadas</p>
                        </div>
                    </div>
                </div>
                <DatePicker @updateDate="updateDate" class="w-full" />
            </div>

            <!-- Columna derecha: Tabla de comensales -->
            <div class="flex min-w-0 flex-1 flex-col gap-3">
                <DinnersTable :services="servicesSelected" @addServiceSelected="addServiceSelected" />
            </div>
            <!-- Columna central: Fecha y acciones principales -->
            <div class="flex min-w-0 flex-1 flex-col gap-3">
                <!-- Fecha -->
                <div class="flex w-full flex-col items-center space-y-1">
                    <ExcelDialog class="w-full" />

                    <ReportDialog class="w-full" />

                    <NewDinnerDialog :cafes="cafes" :subdealerships="subdealerships" />

                    <!-- <PricesDialog :services="servicesSelected" class="w-full" /> -->

                    <OtherUnitDialog :showOtherUnitDialog="showOtherUnitDialog" @doublePriceSave="doublePriceSave" class="w-full" />
                </div>

                <!-- Botones en columna -->
                <div class="flex w-full flex-col gap-2">
                    <!-- <Button class="flex w-full items-center justify-center gap-1 bg-blue-600 hover:bg-blue-700">
                        <UserRoundPlus class="h-4 w-4" />
                        <span>Agregar</span>
                    </Button> -->
                </div>
            </div>
        </div>

        <!-- Estado actual (selecciones) -->
        <div class="flex flex-wrap items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm">
            <span v-if="receiptType" class="flex items-center gap-1 rounded-full bg-blue-100 px-2 py-1 text-blue-800">
                <File class="h-3 w-3" />
                {{ receipt_types.find((t) => t.id === receiptType)?.name }}
            </span>
            <span v-if="saletypeSelected" class="flex items-center gap-1 rounded-full bg-purple-100 px-2 py-1 text-purple-800">
                <Tag class="h-3 w-3" />
                {{ sale_types.find((t) => t.id === saletypeSelected)?.name }}
            </span>
            <span v-if="cafeSelected" class="flex items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-amber-800">
                <Coffee class="h-3 w-3" />
                {{ cafes.find((c) => c.id === cafeSelected)?.name }}
            </span>
            <span v-if="servicesSelected.length" class="ml-auto text-gray-500"> {{ servicesSelected.length }} servicios disponibles </span>
        </div>
    </header>
</template>
