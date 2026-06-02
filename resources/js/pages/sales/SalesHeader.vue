<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Cafe, Service, Unit } from '@/types';
import axios from 'axios';
import { CalendarDays, Coffee, Settings, ShoppingBag, Tag } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import DatePicker from './DatePicker.vue';
import DinnersTable from './DinnersTable.vue';

interface Props {
    cafes: Cafe[];
    services: any[];
    receipt_types: any[];
    sale_types: any[];
    subdealerships: any[];
    units: Unit[];
}

const emits = defineEmits(['showServicesFromCafeSelected', 'updateDate', 'updateFormData', 'addServiceSelected']);

defineProps<Props>();
const doublePrice = ref(false);

const cafeSelected = ref<number>(0);
const saletypeSelected = ref<number>(0);
const servicesSelected = ref<any[]>([]);
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
    if (!newVal) {
        servicesSelected.value = [];
        emits('showServicesFromCafeSelected', [], undefined);
        return;
    }
    axios
        .get(`/cafes/${newVal}/services`)
        .then((res) => {
            servicesSelected.value = res.data ?? [];
            emits('showServicesFromCafeSelected', servicesSelected.value, newVal);
        })
        .catch(() => {
            servicesSelected.value = [];
            emits('showServicesFromCafeSelected', [], newVal);
        });
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
    <div class="space-y-4">
        <!-- Configuration Card -->
        <Card class="overflow-hidden border-none bg-white py-0 shadow-md">
            <!-- Card Header -->
            <div class="flex items-center gap-3 bg-gradient-to-r from-red-600 to-red-500 px-5 py-3.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/20 backdrop-blur-sm">
                    <Settings class="h-4 w-4 text-white" />
                </div>
                <h3 class="text-sm font-bold tracking-widest text-white uppercase">Configuración de Venta</h3>
            </div>

            <CardContent class="space-y-5 p-5">

                <!-- Row: Documento + Categoría -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                    <!-- Tipo de Documento -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-1.5 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                            <ShoppingBag class="h-3 w-3 text-blue-500" />
                            Documento
                        </label>
                        <!-- min-w-[60px] evita que los items se compriman y el texto se solape -->
                        <ToggleGroup
                            v-model="receiptType"
                            type="single"
                            class="flex w-full flex-wrap gap-1.5 rounded-xl bg-slate-100 p-1.5"
                        >
                            <ToggleGroupItem
                                v-for="type in receipt_types"
                                :value="type.id"
                                :key="type.id"
                                class="h-8 min-w-[60px] flex-1 overflow-hidden truncate rounded-lg px-2 text-xs font-semibold whitespace-nowrap transition-all
                                       data-[state=on]:bg-blue-600 data-[state=on]:text-white data-[state=on]:shadow-sm"
                            >
                                {{ type.name }}
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>

                    <!-- Tipo de Venta (Categoría) -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-1.5 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                            <Tag class="h-3 w-3 text-purple-500" />
                            Categoría
                        </label>
                        <ToggleGroup
                            v-model="saletypeSelected"
                            type="single"
                            class="flex w-full flex-wrap gap-1.5 rounded-xl bg-slate-100 p-1.5"
                        >
                            <ToggleGroupItem
                                v-for="type in sale_types"
                                :value="type.id"
                                :key="type.id"
                                class="h-8 min-w-[60px] flex-1 overflow-hidden truncate rounded-lg px-2 text-xs font-semibold whitespace-nowrap transition-all
                                       data-[state=on]:bg-purple-600 data-[state=on]:text-white data-[state=on]:shadow-sm"
                            >
                                {{ type.name }}
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>
                </div>

                <!-- Divider -->
                <div class="flex items-center gap-3">
                    <div class="h-px flex-1 bg-slate-100"></div>
                    <span class="text-[9px] font-bold tracking-widest text-slate-300 uppercase">Punto de servicio</span>
                    <div class="h-px flex-1 bg-slate-100"></div>
                </div>

                <!-- Selector de Cafetería -->
                <div class="space-y-3">
                    <label class="flex items-center gap-1.5 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                        <Icon name="map-pin" size="12" class="text-amber-500" />
                        Unidad / Cafetería
                    </label>

                    <div class="custom-scrollbar max-h-[320px] space-y-4 overflow-y-auto pr-1">
                        <div v-for="unit in units" :key="unit.id" class="space-y-2">
                            <!-- Unit label -->
                            <div class="flex items-center gap-2">
                                <span class="rounded-md bg-slate-800 px-2 py-0.5 text-[9px] font-black tracking-widest text-white uppercase">
                                    {{ unit.name }}
                                </span>
                                <div class="h-px flex-1 bg-slate-100"></div>
                            </div>

                            <!-- Cafe buttons -->
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="cafe in unit.cafes"
                                    :key="cafe.id"
                                    @click="cafeSelected = cafeSelected === cafe.id ? 0 : cafe.id"
                                    class="group flex items-center justify-between rounded-xl border px-3 py-2 text-left text-xs font-semibold transition-all"
                                    :class="
                                        cafeSelected === cafe.id
                                            ? 'border-amber-400 bg-amber-500 text-white shadow-md shadow-amber-200/60'
                                            : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-amber-300 hover:bg-amber-50 hover:text-amber-700'
                                    "
                                >
                                    <span class="min-w-0 truncate">{{ cafe.name }}</span>
                                    <Icon
                                        v-if="cafeSelected === cafe.id"
                                        name="check-circle"
                                        size="13"
                                        class="ml-1.5 shrink-0 text-white"
                                    />
                                    <Coffee
                                        v-else
                                        class="ml-1.5 h-3.5 w-3.5 shrink-0 text-slate-300 transition-colors group-hover:text-amber-400"
                                    />
                                </button>
                            </div>

                            <p v-if="unit.cafes.length === 0" class="pl-2 text-[10px] text-slate-400 italic">
                                Sin cafeterías asignadas
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="h-px w-full bg-slate-100"></div>

                <!-- Date Picker -->
                <div class="space-y-2">
                    <label class="flex items-center gap-1.5 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                        <CalendarDays class="h-3 w-3 text-sky-500" />
                        Fecha de Venta
                    </label>
                    <DatePicker @updateDate="updateDate" class="h-10 w-full" />
                </div>

            </CardContent>
        </Card>

        <!-- Services Selection Card -->
        <Card class="overflow-hidden border-none bg-white py-0 shadow-md">
            <div class="flex items-center justify-between bg-gradient-to-r from-emerald-600 to-emerald-500 px-5 py-3.5">
                <div class="flex items-center gap-3 text-white">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/20">
                        <Icon name="utensils" class="h-4 w-4" />
                    </div>
                    <h3 class="text-sm font-bold tracking-widest uppercase">Servicios Disponibles</h3>
                </div>
                <Badge class="rounded-full border-none bg-white/25 px-2.5 py-1 text-xs font-bold text-white backdrop-blur-sm">
                    {{ servicesSelected.length }}
                </Badge>
            </div>
            <CardContent class="max-h-[320px] p-0">
                <DinnersTable :services="servicesSelected" @addServiceSelected="addServiceSelected" />
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 3px;
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
