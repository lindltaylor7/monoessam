<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { computed, ref, watch } from 'vue';
import ArchiveToggle from './ArchiveToggle.vue';
import CoordEditor from './CoordEditor.vue';

interface Mine {
    id: number;
    name: string;
    latitude: number | null;
    longitude: number | null;
    address: string | null;
    archived_at: string | null;
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

// Archivadas (p. ej. las z(Hist) del sistema antiguo): solo se usan en alimentación; van aparte.
const activeMines = computed(() => localMines.value.filter((m) => !m.archived_at));
const archivedMines = computed(() => localMines.value.filter((m) => m.archived_at));
const showArchived = ref(false);

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
            <template v-for="(mine, i) in [...activeMines, ...(showArchived ? archivedMines : [])]" :key="mine.id">
                <div v-if="i === activeMines.length" class="pt-2 text-[11px] font-semibold tracking-wide text-slate-400 uppercase">
                    Archivadas · solo alimentación
                </div>
                <div
                    @click="emit('selectMine', mine.id)"
                    :class="{
                        'cursor-pointer rounded-lg p-3 transition-colors': true,
                        'bg-blue-500 text-white shadow-lg': mine.id === selectedMineId,
                        'hover:bg-gray-100': mine.id !== selectedMineId,
                        'opacity-60': mine.archived_at && mine.id !== selectedMineId,
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
                    <ArchiveToggle :archived="!!mine.archived_at" :patch-url="`/mines/${mine.id}/archive`" :label="mine.name" />
                </div>
            </template>
            <button
                v-if="archivedMines.length"
                type="button"
                class="w-full rounded-lg border border-dashed p-2 text-xs text-slate-500 hover:bg-gray-50"
                @click="showArchived = !showArchived"
            >
                {{ showArchived ? 'Ocultar archivadas' : `Ver archivadas (${archivedMines.length})` }}
            </button>
        </CardContent>
    </Card>
</template>
