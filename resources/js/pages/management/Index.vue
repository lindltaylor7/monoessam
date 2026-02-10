<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Business, Cafe, Mine, Service, Unit } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue'; // Necesitas importar `ref` y `computed`
import CafeTable from './CafeTable.vue'; // Nuevo componente (similar a TableCafes, pero solo para la Unidad seleccionada)
import ManagementModal from './ManagementModal.vue';
import MineSelector from './MineSelector.vue'; // Nuevo componente
import UnitSelector from './UnitSelector.vue'; // Nuevo componente

interface Props {
    // Es CRUCIAL que las relaciones estén precargadas (eager loaded)
    mines: (Mine & { units: Unit[] })[];
    units: Unit[]; 
    cafes: Cafe[]; 
    services: Service[];
    businesses: Business[];
}

const props = defineProps<Props>();

// --- Estado de Navegación ---
const selectedMineId = ref<number | null>(null);
const selectedUnitId = ref<number | null>(null);

// --- Computed Properties ---
const selectedMine = computed(() => props.mines.find((m) => m.id === selectedMineId.value));

const selectedUnit = computed(() => {
    const mine = selectedMine.value;
    if (mine && mine.units) {
        // Asumiendo que `Unit` ahora tiene una propiedad `cafes`
        return mine.units.find((u: Unit & { cafes: Cafe[] }) => u.id === selectedUnitId.value);
    }
    return null;
});

// --- Handlers ---
const handleSelectMine = (mineId: number) => {
    selectedMineId.value = mineId;
    selectedUnitId.value = null; // Reiniciar unidad al cambiar de mina
};

const handleSelectUnit = (unitId: number) => {
    selectedUnitId.value = unitId;
};

// NOTA: Para que esto funcione, la estructura de datos que pasas desde el backend
// a la prop `mines` debe incluir sus relaciones:
// `mines: Mine::with(['units.cafes'])->get()`
</script>
<template>
    <Head title="Lugares" />
    <AppLayout>
        <div class="grid h-full w-full gap-4 p-4 lg:grid-cols-3">
            <MineSelector :mines="mines" @selectMine="handleSelectMine" :selectedMineId="selectedMineId" />

            <UnitSelector v-if="selectedMine" :units="selectedMine.units" @selectUnit="handleSelectUnit" :selectedUnitId="selectedUnitId" />
            <div v-else class="flex items-center justify-center rounded-xl border p-4 text-center text-gray-500">
                Selecciona una Mina para ver sus Unidades.
            </div>

            <CafeTable v-if="selectedUnit" :cafes="selectedUnit.cafes" :services="services" />
            <div v-else class="flex items-center justify-center rounded-xl border p-4 text-center text-gray-500">
                Selecciona una Unidad para ver sus Cafeterías.
            </div>
        </div>
        <ManagementModal :businesses="businesses" />
    </AppLayout>
</template>
