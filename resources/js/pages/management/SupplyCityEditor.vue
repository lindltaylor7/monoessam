<script setup lang="ts">
import axios from 'axios';
import { Truck } from 'lucide-vue-next';
import { ref, watch } from 'vue';

// Ciudad desde la que se envían los insumos a la Unidad o al Comedor. Define con qué proveedores
// y precios se costean sus ciclos de menú y órdenes de compra. En un Comedor, vacío = hereda la
// de su Unidad (inheritedCityId).
const props = defineProps<{
    cities: { id: number; name: string }[];
    cityId: number | null;
    patchUrl: string;
    inheritedCityId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'updated', cityId: number | null): void;
}>();

const value = ref<string>(props.cityId ? String(props.cityId) : '');
const saving = ref(false);
const error = ref('');

watch(
    () => props.cityId,
    (v) => {
        value.value = v ? String(v) : '';
    },
);

const cityName = (id: number | null | undefined) => props.cities.find((c) => c.id === id)?.name;

async function save() {
    const cityId = value.value ? Number(value.value) : null;
    saving.value = true;
    error.value = '';
    try {
        await axios.patch(props.patchUrl, { supply_city_id: cityId });
        emit('updated', cityId);
    } catch {
        error.value = 'Error al guardar';
        value.value = props.cityId ? String(props.cityId) : '';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <!-- @click.stop: elegir la ciudad no debe seleccionar/deseleccionar la tarjeta contenedora. -->
    <label class="mt-1 flex items-center gap-1.5 text-xs text-slate-400" title="Ciudad desde la que se le envían los insumos" @click.stop>
        <Truck class="h-3 w-3 shrink-0" />
        <span class="shrink-0">Abastece:</span>
        <select
            v-model="value"
            :disabled="saving"
            class="h-6 min-w-0 rounded border border-slate-200 bg-white py-0 pr-6 pl-1.5 text-[11px] text-slate-700 focus:ring-1 focus:ring-blue-400 disabled:opacity-50"
            @change="save"
        >
            <option value="">
                {{ inheritedCityId !== undefined ? `Igual que la unidad (${cityName(inheritedCityId) ?? 'sin ciudad'})` : 'Sin ciudad' }}
            </option>
            <option v-for="city in cities" :key="city.id" :value="String(city.id)">{{ city.name }}</option>
        </select>
        <span v-if="error" class="text-[10px] text-red-500">{{ error }}</span>
    </label>
</template>
