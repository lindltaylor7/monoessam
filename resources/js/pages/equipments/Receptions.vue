<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    CheckCircle2,
    Coffee,
    Layers,
    MapPin,
    Mountain,
    PackageCheck,
    Search,
    Truck,
    Warehouse,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DispatchReceptionTable from './_DispatchReceptionTable.vue';

// ── Types ──────────────────────────────────────────────────────────────────
interface Cafe   { id: number; name: string }
interface Unit   { id: number; name: string; cafes: Cafe[] }
interface Mine   { id: number; name: string; units: Unit[] }
interface HQ     { id: number; name: string; business: { id: number; name: string } | null }

export interface DispatchRow {
    id: number;
    dispatch_number: string;
    status: 'active' | 'returned';
    equipable_type: 'computer' | 'kitchen';
    quantity: number;
    equipment_name: string;
    equipment_brand: string | null;
    equipment_model: string | null;
    origin_name: string;
    destination_type: string;
    destination_id: number;
    dispatched_by: string;
    dispatched_at: string;
    received_at: string | null;
    received_by: string | null;
}

const props = defineProps<{
    dispatches:   DispatchRow[];
    mines:        Mine[];
    headquarters: HQ[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Equipos', href: route('equipments.index') },
    { title: 'Despachos', href: route('equipment-dispatches.index') },
    { title: 'Recepción por Destino', href: route('equipment-dispatches.receptions') },
];

// ── State ──────────────────────────────────────────────────────────────────
const search     = ref('');
const confirmId  = ref<number | null>(null);
const processing = ref(false);

// ── Helpers ────────────────────────────────────────────────────────────────
function dispatchesFor(type: string, id: number): DispatchRow[] {
    const q = search.value.trim().toLowerCase();
    return props.dispatches.filter(d => {
        if (d.destination_type !== type || d.destination_id !== id) return false;
        if (!q) return true;
        return (
            d.dispatch_number.toLowerCase().includes(q) ||
            d.equipment_name.toLowerCase().includes(q)
        );
    });
}

// ── Stats ──────────────────────────────────────────────────────────────────
const stats = computed(() => {
    const total      = props.dispatches.length;
    const received   = props.dispatches.filter(d => d.received_at).length;
    const inTransit  = total - received;
    const totalUnits = props.dispatches.reduce((s, d) => s + d.quantity, 0);
    return { total, received, inTransit, totalUnits };
});

// ── Groups ─────────────────────────────────────────────────────────────────
const mineGroups = computed(() =>
    props.mines
        .map(mine => {
            const unitGroups = mine.units.map(unit => {
                const cafeGroups = unit.cafes
                    .map(cafe => ({ cafe, dispatches: dispatchesFor('cafe', cafe.id) }))
                    .filter(g => g.dispatches.length > 0);
                const unitDispatches = dispatchesFor('unit', unit.id);
                return { unit, cafeGroups, unitDispatches };
            }).filter(g => g.cafeGroups.length > 0 || g.unitDispatches.length > 0);

            const mineDispatches = dispatchesFor('mine', mine.id);
            return { mine, unitGroups, mineDispatches };
        })
        .filter(g => g.unitGroups.length > 0 || g.mineDispatches.length > 0)
);

const hqGroups = computed(() =>
    props.headquarters
        .map(hq => ({ hq, dispatches: dispatchesFor('headquarter', hq.id) }))
        .filter(g => g.dispatches.length > 0)
);

const hasResults = computed(() => mineGroups.value.length > 0 || hqGroups.value.length > 0);

// ── Actions ────────────────────────────────────────────────────────────────
function doReceive(id: number) {
    processing.value = true;
    router.put(route('equipment-dispatches.receive', id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            confirmId.value  = null;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Recepción de Equipos" />

        <div class="space-y-6 p-6">

            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Recepción de Equipos</h1>
                    <p class="text-muted-foreground mt-0.5 text-sm">
                        Control de recepciones por unidad minera y sede
                    </p>
                </div>
                <a :href="route('equipment-dispatches.index')">
                    <Button variant="outline" size="sm" class="gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Volver a Despachos
                    </Button>
                </a>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <Card>
                    <CardContent class="flex items-center gap-3 pt-5">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100">
                            <Truck class="h-5 w-5 text-blue-600" />
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">En Tránsito</p>
                            <p class="text-2xl font-bold text-blue-600">{{ stats.inTransit }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 pt-5">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100">
                            <CheckCircle2 class="h-5 w-5 text-emerald-600" />
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Confirmados</p>
                            <p class="text-2xl font-bold text-emerald-600">{{ stats.received }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 pt-5">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-amber-100">
                            <Layers class="h-5 w-5 text-amber-600" />
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Despachos Activos</p>
                            <p class="text-2xl font-bold text-amber-600">{{ stats.total }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 pt-5">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                            <PackageCheck class="h-5 w-5 text-purple-600" />
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Unidades Enviadas</p>
                            <p class="text-2xl font-bold text-purple-600">{{ stats.totalUnits }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Search -->
            <div class="relative max-w-sm">
                <Search class="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                <Input v-model="search" placeholder="Buscar por N° despacho o equipo…" class="pl-9" />
            </div>

            <!-- Empty: no active dispatches at all -->
            <div v-if="dispatches.length === 0" class="py-20 text-center">
                <CheckCircle2 class="mx-auto mb-3 h-12 w-12 text-emerald-400" />
                <p class="text-lg font-semibold">No hay despachos activos</p>
                <p class="text-muted-foreground text-sm">Todos los equipos están en almacén</p>
            </div>

            <!-- Empty: search no results -->
            <div v-else-if="!hasResults && search" class="py-20 text-center">
                <Search class="text-muted-foreground mx-auto mb-3 h-12 w-12" />
                <p class="text-lg font-semibold">Sin resultados</p>
                <p class="text-muted-foreground text-sm">No hay despachos para "{{ search }}"</p>
            </div>

            <!-- ── Mine Groups ── -->
            <template v-if="mineGroups.length > 0">
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-slate-400">
                    <Mountain class="h-3.5 w-3.5" />
                    Unidades Mineras
                </div>

                <Card
                    v-for="{ mine, unitGroups, mineDispatches } in mineGroups"
                    :key="mine.id"
                    class="border-slate-200"
                >
                    <CardHeader class="border-b bg-slate-50 py-3 dark:bg-slate-800/40">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Mountain class="h-4 w-4 text-slate-400" />
                            {{ mine.name }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6 p-4">

                        <!-- Dispatched directly to mine -->
                        <template v-if="mineDispatches.length">
                            <p class="text-muted-foreground text-xs font-semibold uppercase">Directo a Mina</p>
                            <DispatchReceptionTable
                                :dispatches="mineDispatches"
                                :confirm-id="confirmId"
                                :processing="processing"
                                @confirm="id => confirmId = id"
                                @cancel="confirmId = null"
                                @receive="doReceive"
                            />
                        </template>

                        <!-- Units -->
                        <div v-for="{ unit, cafeGroups, unitDispatches } in unitGroups" :key="unit.id" class="space-y-4">
                            <div class="flex items-center gap-1.5 text-sm font-semibold text-slate-500">
                                <MapPin class="h-3.5 w-3.5" />
                                {{ unit.name }}
                            </div>

                            <!-- Dispatched directly to unit -->
                            <template v-if="unitDispatches.length">
                                <DispatchReceptionTable
                                    :dispatches="unitDispatches"
                                    :confirm-id="confirmId"
                                    :processing="processing"
                                    @confirm="id => confirmId = id"
                                    @cancel="confirmId = null"
                                    @receive="doReceive"
                                />
                            </template>

                            <!-- Cafes -->
                            <div
                                v-for="{ cafe, dispatches: cafeDis } in cafeGroups"
                                :key="cafe.id"
                                class="ml-4 rounded-lg border border-dashed border-amber-200 bg-amber-50/30 p-4 dark:bg-amber-900/10"
                            >
                                <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-amber-700">
                                    <Coffee class="h-4 w-4" />
                                    {{ cafe.name }}
                                    <span class="text-muted-foreground font-normal text-xs">
                                        ({{ cafeDis.length }} despacho{{ cafeDis.length !== 1 ? 's' : '' }})
                                    </span>
                                </div>
                                <DispatchReceptionTable
                                    :dispatches="cafeDis"
                                    :confirm-id="confirmId"
                                    :processing="processing"
                                    @confirm="id => confirmId = id"
                                    @cancel="confirmId = null"
                                    @receive="doReceive"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </template>

            <!-- ── HQ Groups ── -->
            <template v-if="hqGroups.length > 0">
                <div class="mt-2 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-slate-400">
                    <Building2 class="h-3.5 w-3.5" />
                    Sedes / Almacenes
                </div>

                <Card
                    v-for="{ hq, dispatches: hqDis } in hqGroups"
                    :key="hq.id"
                    class="border-slate-200"
                >
                    <CardHeader class="border-b bg-slate-50 py-3 dark:bg-slate-800/40">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Warehouse class="h-4 w-4 text-slate-400" />
                            {{ hq.name }}
                            <span v-if="hq.business" class="text-muted-foreground text-sm font-normal">
                                · {{ hq.business.name }}
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-4">
                        <DispatchReceptionTable
                            :dispatches="hqDis"
                            :confirm-id="confirmId"
                            :processing="processing"
                            @confirm="id => confirmId = id"
                            @cancel="confirmId = null"
                            @receive="doReceive"
                        />
                    </CardContent>
                </Card>
            </template>

        </div>
    </AppLayout>
</template>
