<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ref, watch } from 'vue';
import CoordEditor from './CoordEditor.vue';

interface Mine {
    id: number;
    name: string;
    latitude: number | null;
    longitude: number | null;
    address: string | null;
}

const props = defineProps<{
    mines: Mine[];
    selectedMineId: number | null;
}>();

const emit = defineEmits<{
    (e: 'selectMine', id: number): void;
}>();

const localMines = ref<Mine[]>(props.mines.map(m => ({ ...m })));

watch(() => props.mines, (v) => {
    localMines.value = v.map(m => ({ ...m }));
}, { deep: true });

function onCoordUpdated(mineId: number, lat: number | null, lng: number | null, address: string | null) {
    const m = localMines.value.find(x => x.id === mineId);
    if (m) { m.latitude = lat; m.longitude = lng; m.address = address; }
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Minas ⛏️</CardTitle>
        </CardHeader>
        <CardContent class="max-h-[80vh] space-y-2 overflow-y-auto">
            <div
                v-for="mine in localMines"
                :key="mine.id"
                @click="emit('selectMine', mine.id)"
                :class="{
                    'cursor-pointer rounded-lg p-3 transition-colors': true,
                    'bg-blue-500 text-white shadow-lg': mine.id === selectedMineId,
                    'hover:bg-gray-100': mine.id !== selectedMineId,
                }"
            >
                <div class="font-medium">{{ mine.name }}</div>
                <CoordEditor
                    :latitude="mine.latitude"
                    :longitude="mine.longitude"
                    :address="mine.address"
                    :patch-url="`/mines/${mine.id}`"
                    @updated="(lat, lng, addr) => onCoordUpdated(mine.id, lat, lng, addr)"
                />
            </div>
        </CardContent>
    </Card>
</template>
