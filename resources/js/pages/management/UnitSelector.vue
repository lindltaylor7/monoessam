<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Unit } from '@/types';
import { ref, watch } from 'vue';
import ArchiveToggle from './ArchiveToggle.vue';
import CoordEditor from './CoordEditor.vue';
import SupplyCityEditor from './SupplyCityEditor.vue';

interface UnitWithCoords extends Unit {
    cafes: { id: number; name: string }[];
    latitude: number | null;
    longitude: number | null;
    address: string | null;
    supply_city_id?: number | null;
    archived_at?: string | null;
}

const props = defineProps<{
    units: UnitWithCoords[];
    selectedUnitId: number | null;
    cities: { id: number; name: string }[];
}>();

const emit = defineEmits<{
    (e: 'selectUnit', id: number): void;
}>();

// Activas primero; las archivadas (solo alimentación) al final.
const sorted = (v: UnitWithCoords[]) => v.map((u) => ({ ...u })).sort((a, b) => Number(!!a.archived_at) - Number(!!b.archived_at));

const localUnits = ref<UnitWithCoords[]>(sorted(props.units));

watch(() => props.units, (v) => {
    localUnits.value = sorted(v);
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
                        'opacity-60': unit.archived_at && unit.id !== selectedUnitId,
                    }"
                >
                    <div class="font-semibold">
                        {{ unit.name }}
                        <span v-if="unit.archived_at" class="ml-1 rounded bg-slate-200 px-1 text-[10px] font-normal text-slate-600">archivada</span>
                    </div>
                    <CoordEditor
                        :latitude="unit.latitude"
                        :longitude="unit.longitude"
                        :address="unit.address"
                        :patch-url="`/units/${unit.id}`"
                        @updated="(lat, lng, addr) => onCoordUpdated(unit.id, lat, lng, addr)"
                    />
                    <SupplyCityEditor
                        :cities="cities"
                        :city-id="unit.supply_city_id ?? null"
                        :patch-url="`/units/${unit.id}`"
                        @updated="(cityId) => (unit.supply_city_id = cityId)"
                    />
                    <ArchiveToggle :archived="!!unit.archived_at" :patch-url="`/units/${unit.id}/archive`" :label="unit.name" />
                </div>
            </div>
            <div v-else class="p-4 text-center text-gray-500">No hay unidades asociadas a esta Mina.</div>
        </CardContent>
    </Card>
</template>
