<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Service } from '@/types';
import { ShieldCheck } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import ArchiveToggle from './ArchiveToggle.vue';
import CafeRolesModal from './CafeRolesModal.vue';
import CoordEditor from './CoordEditor.vue';
import SupplyCityEditor from './SupplyCityEditor.vue';

interface CafeItem {
    id: number;
    name: string;
    latitude: number | null;
    longitude: number | null;
    address: string | null;
    supply_city_id?: number | null;
    archived_at?: string | null;
    unit: {
        name: string;
        mine: { name: string };
        services: Service[];
    };
}

const props = defineProps<{
    cafes: CafeItem[];
    services: Service[];
    roles: any[];
    cities: { id: number; name: string }[];
    unitSupplyCityId: number | null;
}>();

// Activos primero; los archivados (solo alimentación) al final.
const sorted = (v: CafeItem[]) => v.map((c) => ({ ...c })).sort((a, b) => Number(!!a.archived_at) - Number(!!b.archived_at));

const localCafes = ref<CafeItem[]>(sorted(props.cafes));

watch(() => props.cafes, (v) => {
    localCafes.value = sorted(v);
}, { deep: true });

function onCoordUpdated(cafeId: number, lat: number | null, lng: number | null, address: string | null) {
    const c = localCafes.value.find(x => x.id === cafeId);
    if (c) { c.latitude = lat; c.longitude = lng; c.address = address; }
}

const isRolesModalOpen = ref(false);
const selectedCafeForRoles = ref<any>(null);

const openRolesModal = (cafe: any) => {
    selectedCafeForRoles.value = cafe;
    isRolesModalOpen.value = true;
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Comedores ☕</CardTitle>
        </CardHeader>
        <CardContent class="max-h-[80vh] space-y-4 overflow-y-auto">
            <div v-if="localCafes && localCafes.length > 0">
                <Table>
                    <TableCaption>Lista de Comedores en la Unidad seleccionada.</TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nombre / Coordenadas</TableHead>
                            <TableHead class="text-right">Opciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="cafe in localCafes" :key="cafe.id" :class="{ 'opacity-60': cafe.archived_at }">
                            <TableCell class="font-medium">
                                <div>
                                    {{ cafe.name }}
                                    <span v-if="cafe.archived_at" class="ml-1 rounded bg-slate-200 px-1 text-[10px] font-normal text-slate-600">archivado</span>
                                </div>
                                <CoordEditor
                                    :latitude="cafe.latitude"
                                    :longitude="cafe.longitude"
                                    :address="cafe.address"
                                    :patch-url="`/cafes/${cafe.id}`"
                                    @updated="(lat, lng, addr) => onCoordUpdated(cafe.id, lat, lng, addr)"
                                />
                                <SupplyCityEditor
                                    :cities="cities"
                                    :city-id="cafe.supply_city_id ?? null"
                                    :inherited-city-id="unitSupplyCityId"
                                    :patch-url="`/cafes/${cafe.id}`"
                                    @updated="(cityId) => (cafe.supply_city_id = cityId)"
                                />
                                <ArchiveToggle :archived="!!cafe.archived_at" :patch-url="`/cafes/${cafe.id}/archive`" :label="cafe.name" />
                            </TableCell>
                            <TableCell class="flex flex-row justify-end gap-2 text-right">
                                <Button size="icon" variant="outline" @click="openRolesModal(cafe)">
                                    <ShieldCheck class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
            <div v-else class="p-4 text-center text-gray-500">Selecciona una Unidad o esta Unidad no tiene Cafeterías.</div>
        </CardContent>
        <CafeRolesModal :cafe="selectedCafeForRoles" :roles="roles" v-model:open="isRolesModalOpen" />
    </Card>
</template>
