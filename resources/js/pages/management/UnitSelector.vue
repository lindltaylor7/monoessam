<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Unit } from '@/types';
import { ref, watch } from 'vue';
import CoordEditor from './CoordEditor.vue';

interface UnitWithCoords extends Unit {
    cafes: { id: number; name: string }[];
    latitude: number | null;
    longitude: number | null;
    address: string | null;
}

const props = defineProps<{
    units: UnitWithCoords[];
    selectedUnitId: number | null;
}>();

const emit = defineEmits<{
    (e: 'selectUnit', id: number): void;
}>();

const localUnits = ref<UnitWithCoords[]>(props.units.map(u => ({ ...u })));

watch(() => props.units, (v) => {
    localUnits.value = v.map(u => ({ ...u }));
}, { deep: true });

function onCoordUpdated(unitId: number, lat: number | null, lng: number | null, address: string | null) {
    const u = localUnits.value.find(x => x.id === unitId);
    if (u) { u.latitude = lat; u.longitude = lng; u.address = address; }
}

const selectUnit = (unitId: number) => {
    emit('selectUnit', unitId === props.selectedUnitId ? null : unitId);
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Unidades 🏢</CardTitle>
        </CardHeader>
        <CardContent class="max-h-[80vh] space-y-2 overflow-y-auto">
            <div v-if="localUnits && localUnits.length > 0">
                <div
                    v-for="unit in localUnits"
                    :key="unit.id"
                    @click="selectUnit(unit.id)"
                    class="mb-2 rounded-lg border p-3 transition-colors cursor-pointer"
                    :class="{
                        'bg-yellow-500 text-white shadow-lg': unit.id === selectedUnitId,
                        'border-gray-200 hover:bg-gray-100': unit.id !== selectedUnitId,
                    }"
                >
                    <div class="font-semibold">{{ unit.name }}</div>
                    <CoordEditor
                        :latitude="unit.latitude"
                        :longitude="unit.longitude"
                        :address="unit.address"
                        :patch-url="`/units/${unit.id}`"
                        @updated="(lat, lng, addr) => onCoordUpdated(unit.id, lat, lng, addr)"
                    />
                </div>
            </div>
            <div v-else class="p-4 text-center text-gray-500">No hay unidades asociadas a esta Mina.</div>
        </CardContent>
    </Card>
</template>
