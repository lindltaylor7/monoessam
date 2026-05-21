<script setup lang="ts">
import axios from 'axios';
import { Check, ChevronDown, Coffee, Loader2, Mountain, RefreshCw, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface Service {
    id: number;
    name: string;
    code: string;
    type: string;
}

interface CafeService extends Service {
    pivot: { price: number | null };
}

interface Cafe {
    id: number;
    name: string;
    services: CafeService[];
}

interface Unit {
    id: number;
    name: string;
    cafes: Cafe[];
}

interface Mine {
    id: number;
    name: string;
    units: Unit[];
}

interface ServiceState {
    active: boolean;
    price: string;
}

const open = ref(false);
const loading = ref(false);
const mines = ref<Mine[]>([]);
const allServices = ref<Service[]>([]);
const expandedMines = ref<number[]>([]);
const expandedUnits = ref<number[]>([]);
const localState = ref<Record<number, Record<number, ServiceState>>>({});
const savingCafe = ref<number | null>(null);
const savedCafe = ref<number | null>(null);
const errorCafe = ref<number | null>(null);

const typeConfig: Record<string, { dot: string; row: string; badge: string }> = {
    '1': { dot: 'bg-amber-400',   row: 'bg-amber-50/60',   badge: 'bg-amber-100 text-amber-700' },
    '2': { dot: 'bg-blue-400',    row: 'bg-blue-50/60',    badge: 'bg-blue-100 text-blue-700' },
    '3': { dot: 'bg-violet-400',  row: 'bg-violet-50/60',  badge: 'bg-violet-100 text-violet-700' },
    '4': { dot: 'bg-emerald-400', row: 'bg-emerald-50/60', badge: 'bg-emerald-100 text-emerald-700' },
    '5': { dot: 'bg-slate-400',   row: 'bg-slate-50',      badge: 'bg-slate-100 text-slate-600' },
};

const typeLabels: Record<string, string> = {
    '1': 'Desayuno', '2': 'Almuerzo', '3': 'Cena', '4': 'Refrigerio', '5': 'Descartables',
};

const buildState = (minesData: Mine[], services: Service[]) => {
    const state: Record<number, Record<number, ServiceState>> = {};
    minesData.forEach(mine => {
        mine.units.forEach(unit => {
            unit.cafes.forEach(cafe => {
                state[cafe.id] = {};
                services.forEach(svc => {
                    const assigned = cafe.services.find(s => s.id === svc.id);
                    state[cafe.id][svc.id] = {
                        active: !!assigned,
                        price: assigned?.pivot?.price != null ? String(assigned.pivot.price) : '',
                    };
                });
            });
        });
    });
    return state;
};

const fetchData = async () => {
    loading.value = true;
    try {
        const res = await axios.get('/services/cafes-data');
        mines.value = res.data.mines;
        allServices.value = res.data.services;
        expandedMines.value = mines.value.map(m => m.id);
        expandedUnits.value = mines.value.flatMap(m => m.units.map(u => u.id));
        localState.value = buildState(mines.value, allServices.value);
    } finally {
        loading.value = false;
    }
};

const openModal = () => {
    open.value = true;
    fetchData();
};

const close = () => { open.value = false; };

const toggleMine = (id: number) => {
    const idx = expandedMines.value.indexOf(id);
    if (idx === -1) expandedMines.value.push(id);
    else expandedMines.value.splice(idx, 1);
};

const toggleUnit = (id: number) => {
    const idx = expandedUnits.value.indexOf(id);
    if (idx === -1) expandedUnits.value.push(id);
    else expandedUnits.value.splice(idx, 1);
};

const toggleService = (cafeId: number, serviceId: number) => {
    const s = localState.value[cafeId][serviceId];
    s.active = !s.active;
};

const saveCafe = async (cafeId: number) => {
    savingCafe.value = cafeId;
    savedCafe.value = null;
    errorCafe.value = null;
    const state = localState.value[cafeId];
    const services = Object.entries(state)
        .filter(([, v]) => v.active)
        .map(([id, v]) => ({ id: Number(id), price: parseFloat(v.price) || 0 }));
    try {
        await axios.post(`/services/cafes/${cafeId}/sync`, { services });
        savedCafe.value = cafeId;
        setTimeout(() => { if (savedCafe.value === cafeId) savedCafe.value = null; }, 2500);
    } catch {
        errorCafe.value = cafeId;
        setTimeout(() => { if (errorCafe.value === cafeId) errorCafe.value = null; }, 2500);
    } finally {
        savingCafe.value = null;
    }
};

const activeCount = (cafeId: number) =>
    Object.values(localState.value[cafeId] ?? {}).filter(v => v.active).length;
</script>

<template>
    <button
        @click="openModal"
        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 active:scale-95"
    >
        <RefreshCw class="h-4 w-4" />
        Sincronizar Servicios
    </button>

    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 py-8 md:items-center">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="close" />

            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95 translate-y-2"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                appear
            >
                <div class="relative z-10 w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <!-- Header -->
                    <div class="relative overflow-hidden bg-gradient-to-r from-indigo-600 to-indigo-700 px-6 py-5 text-white">
                        <div class="absolute -top-6 -right-6 h-24 w-24 rounded-full bg-white/10 blur-2xl" />
                        <div class="absolute -bottom-4 -left-4 h-16 w-16 rounded-full bg-black/10 blur-xl" />
                        <div class="relative pr-10">
                            <h3 class="text-xl font-bold tracking-tight">Sincronizar Servicios por Cafetería</h3>
                            <p class="mt-0.5 text-sm text-indigo-100/80">
                                Asigna servicios y configura precios para cada cafetería agrupada por Mina y Unidad
                            </p>
                        </div>
                        <button
                            @click="close"
                            class="absolute top-4 right-4 flex h-8 w-8 items-center justify-center rounded-full bg-white/20 transition hover:bg-white/30"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Legend -->
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-b border-slate-100 bg-slate-50 px-6 py-2.5">
                        <span class="text-[10px] font-bold tracking-wider text-slate-400 uppercase">Tipos:</span>
                        <div v-for="(cfg, type) in typeConfig" :key="type" class="flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full" :class="cfg.dot" />
                            <span class="text-[11px] font-medium text-slate-500">{{ typeLabels[type] }}</span>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="max-h-[65vh] overflow-y-auto p-5">
                        <!-- Loading -->
                        <div v-if="loading" class="flex flex-col items-center justify-center py-24 text-slate-400">
                            <Loader2 class="h-10 w-10 animate-spin text-indigo-400" />
                            <p class="mt-3 text-sm font-medium">Cargando estructura...</p>
                        </div>

                        <div v-else class="space-y-3">
                            <div v-if="mines.length === 0" class="py-16 text-center">
                                <Mountain class="mx-auto h-12 w-12 text-slate-200" />
                                <p class="mt-3 text-sm font-medium text-slate-400">No hay minas registradas</p>
                            </div>

                            <!-- Mine -->
                            <div
                                v-for="mine in mines"
                                :key="mine.id"
                                class="overflow-hidden rounded-xl border border-slate-200 shadow-sm"
                            >
                                <!-- Mine header -->
                                <button
                                    @click="toggleMine(mine.id)"
                                    class="flex w-full items-center gap-3 bg-slate-700 px-5 py-3 text-left transition hover:bg-slate-800"
                                >
                                    <Mountain class="h-4 w-4 flex-shrink-0 text-slate-300" />
                                    <span class="flex-1 text-sm font-bold tracking-wider text-white uppercase">{{ mine.name }}</span>
                                    <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-bold text-slate-300">
                                        {{ mine.units.reduce((n, u) => n + u.cafes.length, 0) }} cafeterías
                                    </span>
                                    <ChevronDown
                                        class="h-4 w-4 flex-shrink-0 text-slate-300 transition-transform duration-200"
                                        :class="{ '-rotate-90': !expandedMines.includes(mine.id) }"
                                    />
                                </button>

                                <!-- Units -->
                                <div v-if="expandedMines.includes(mine.id)" class="divide-y divide-slate-100">
                                    <div v-for="unit in mine.units" :key="unit.id">
                                        <!-- Unit header -->
                                        <button
                                            @click="toggleUnit(unit.id)"
                                            class="flex w-full items-center gap-3 bg-slate-100/80 px-5 py-2.5 text-left transition hover:bg-slate-100"
                                        >
                                            <div class="h-2 w-2 flex-shrink-0 rounded-full bg-indigo-400" />
                                            <span class="flex-1 text-xs font-bold tracking-wider text-slate-600 uppercase">{{ unit.name }}</span>
                                            <span class="text-[10px] text-slate-400">{{ unit.cafes.length }} cafeterías</span>
                                            <ChevronDown
                                                class="h-3.5 w-3.5 flex-shrink-0 text-slate-400 transition-transform duration-200"
                                                :class="{ '-rotate-90': !expandedUnits.includes(unit.id) }"
                                            />
                                        </button>

                                        <!-- Cafes -->
                                        <div v-if="expandedUnits.includes(unit.id)" class="space-y-3 bg-white p-4">
                                            <p v-if="unit.cafes.length === 0" class="py-2 text-center text-xs text-slate-400">
                                                Sin cafeterías en esta unidad
                                            </p>

                                            <div
                                                v-for="cafe in unit.cafes"
                                                :key="cafe.id"
                                                class="overflow-hidden rounded-xl border border-slate-200 shadow-sm"
                                            >
                                                <!-- Cafe header -->
                                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-white px-4 py-3">
                                                    <div class="flex items-center gap-2.5">
                                                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-indigo-100">
                                                            <Coffee class="h-4 w-4 text-indigo-600" />
                                                        </div>
                                                        <div>
                                                            <p class="text-sm font-bold text-slate-800">{{ cafe.name }}</p>
                                                            <p class="text-[10px] text-slate-400">
                                                                <span class="font-semibold text-indigo-600">{{ activeCount(cafe.id) }}</span>
                                                                de {{ allServices.length }} activos
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <button
                                                        @click="saveCafe(cafe.id)"
                                                        :disabled="savingCafe === cafe.id"
                                                        class="flex items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-semibold shadow-sm transition active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                                                        :class="errorCafe === cafe.id
                                                            ? 'bg-red-100 text-red-700'
                                                            : savedCafe === cafe.id
                                                                ? 'bg-emerald-100 text-emerald-700'
                                                                : 'bg-indigo-600 text-white hover:bg-indigo-700'"
                                                    >
                                                        <Loader2 v-if="savingCafe === cafe.id" class="h-3.5 w-3.5 animate-spin" />
                                                        <Check v-else-if="savedCafe === cafe.id" class="h-3.5 w-3.5" />
                                                        <X v-else-if="errorCafe === cafe.id" class="h-3.5 w-3.5" />
                                                        <span>{{ errorCafe === cafe.id ? 'Error' : savedCafe === cafe.id ? 'Guardado' : 'Guardar' }}</span>
                                                    </button>
                                                </div>

                                                <!-- Service rows -->
                                                <div class="divide-y divide-slate-100">
                                                    <div
                                                        v-for="svc in allServices"
                                                        :key="svc.id"
                                                        class="flex items-center gap-3 px-4 py-2.5 transition-colors"
                                                        :class="localState[cafe.id]?.[svc.id]?.active
                                                            ? (typeConfig[svc.type]?.row ?? 'bg-indigo-50/50')
                                                            : 'bg-white hover:bg-slate-50'"
                                                    >
                                                        <!-- Type dot -->
                                                        <span
                                                            class="h-2.5 w-2.5 flex-shrink-0 rounded-full"
                                                            :class="typeConfig[svc.type]?.dot ?? 'bg-slate-400'"
                                                        />

                                                        <!-- Service name (full, no truncation) -->
                                                        <span
                                                            class="flex-1 text-sm leading-snug"
                                                            :class="localState[cafe.id]?.[svc.id]?.active
                                                                ? 'font-semibold text-slate-800'
                                                                : 'font-medium text-slate-500'"
                                                        >
                                                            {{ svc.name }}
                                                        </span>

                                                        <!-- Type badge -->
                                                        <span
                                                            class="flex-shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold"
                                                            :class="typeConfig[svc.type]?.badge ?? 'bg-slate-100 text-slate-500'"
                                                        >
                                                            {{ typeLabels[svc.type] ?? svc.type }}
                                                        </span>

                                                        <!-- Price input (active only) -->
                                                        <div v-if="localState[cafe.id]?.[svc.id]?.active" class="flex flex-shrink-0 items-center gap-1">
                                                            <span class="text-[11px] font-medium text-slate-400">S/</span>
                                                            <input
                                                                type="number"
                                                                v-model="localState[cafe.id][svc.id].price"
                                                                class="w-16 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs font-bold text-slate-700 outline-none focus:border-indigo-300 focus:ring-1 focus:ring-indigo-100"
                                                                placeholder="0.00"
                                                                min="0"
                                                                step="0.01"
                                                                @click.stop
                                                            />
                                                        </div>
                                                        <div v-else class="w-[88px] flex-shrink-0" />

                                                        <!-- Toggle switch -->
                                                        <button
                                                            @click="toggleService(cafe.id, svc.id)"
                                                            class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-150 focus:outline-none"
                                                            :class="localState[cafe.id]?.[svc.id]?.active ? 'bg-indigo-500' : 'bg-slate-200'"
                                                            :title="localState[cafe.id]?.[svc.id]?.active ? 'Desactivar' : 'Activar'"
                                                        >
                                                            <span
                                                                class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition-transform duration-150"
                                                                :class="localState[cafe.id]?.[svc.id]?.active ? 'translate-x-4' : 'translate-x-0'"
                                                            />
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50/80 px-6 py-3">
                        <p class="text-xs text-slate-400">
                            Haz clic en un servicio para activarlo/desactivarlo · Ingresa el precio · Guarda por cafetería
                        </p>
                        <button
                            @click="close"
                            class="rounded-lg border border-slate-200 bg-white px-5 py-2 text-sm font-medium text-slate-600 shadow-sm transition hover:bg-slate-50 active:scale-95"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>
