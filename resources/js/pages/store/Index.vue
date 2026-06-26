<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Building2,
    CheckCircle2,
    Clock,
    Coffee,
    HardHat,
    Laptop,
    PackageCheck,
    PackageSearch,
    SendHorizonal,
    ShieldCheck,
    Truck,
    UtensilsCrossed,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

// ── Types ──────────────────────────────────────────────────────────────────
interface CafeUnit { id: number; name: string; mine: { id: number; name: string } | null }
interface Cafe     { id: number; name: string; unit_id: number; unit: CafeUnit | null }
interface HQ       { id: number; name: string; business: { id: number; name: string } | null }

interface Dispatch {
    id: number;
    dispatch_number: string;
    guide_number: string | null;
    status: string;
    equipable_type: 'computer' | 'kitchen';
    equipable_id: number;
    quantity: number;
    equipment_name: string;
    equipment_brand: string | null;
    equipment_model: string | null;
    equipment_code: string | null;
    origin_name: string;
    destination_id: number;
    dispatched_by: string;
    dispatched_at: string;
    received_at: string | null;
    received_by: string | null;
    reception_notes: string | null;
}

const props = defineProps<{
    dispatches:   Dispatch[];
    cafes:        Cafe[];
    allCafes:     Cafe[];
    headquarters: HQ[];
}>();

// ── State ──────────────────────────────────────────────────────────────────
type TabKey = 'all' | 'computer' | 'kitchen' | 'epp' | 'supplies';

const selectedCafeId = ref<number | null>(props.cafes[0]?.id ?? null);
const activeTab      = ref<TabKey>('all');
const confirmId      = ref<number | null>(null);
const receptionNote  = ref('');
const processing     = ref(false);

// ── Send modal ─────────────────────────────────────────────────────────────
const sendOpen = ref(false);
const sendForm = ref({
    destination_type: 'cafe' as 'cafe' | 'headquarter',
    destination_id:   '' as string,
    description:      '',
    items:            [] as { equipable_type: 'computer' | 'kitchen'; equipable_id: number; equipment_name: string; quantity: number; max: number }[],
});
const sendProcessing = ref(false);

function openSendModal() {
    // Build the equipment list from received dispatches at this cafe
    const received = cafeDispatches.value.filter(d => d.received_at);
    const byEquip: Record<string, typeof sendForm.value['items'][0]> = {};
    for (const d of received) {
        const key = `${d.equipable_type}-${d.equipable_id}`;
        if (!byEquip[key]) {
            byEquip[key] = {
                equipable_type: d.equipable_type,
                equipable_id:   d.equipable_id,
                equipment_name: d.equipment_name,
                quantity:       0,
                max:            0,
            };
        }
        byEquip[key].max += d.quantity;
    }
    sendForm.value = {
        destination_type: 'cafe',
        destination_id:   '',
        description:      '',
        items: Object.values(byEquip).map(e => ({ ...e, quantity: 1 })),
    };
    sendOpen.value = true;
}

function closeSendModal() {
    sendOpen.value = false;
}

const sendableItems = computed(() =>
    sendForm.value.items.filter(i => i.quantity > 0)
);

const destOptions = computed(() => {
    if (sendForm.value.destination_type === 'cafe') {
        return props.allCafes
            .filter(c => c.id !== selectedCafeId.value)
            .map(c => ({ id: String(c.id), label: `${c.name} — ${c.unit?.name ?? ''}` }));
    }
    return props.headquarters.map(h => ({
        id: String(h.id),
        label: `${h.name}${h.business ? ' · ' + h.business.name : ''}`,
    }));
});

function submitSend() {
    if (!selectedCafeId.value || !sendForm.value.destination_id || sendableItems.value.length === 0) return;
    sendProcessing.value = true;
    router.post(route('store.dispatch'), {
        origin_cafe_id:   selectedCafeId.value,
        destination_type: sendForm.value.destination_type,
        destination_id:   Number(sendForm.value.destination_id),
        description:      sendForm.value.description || null,
        items:            sendableItems.value.map(i => ({
            equipable_type: i.equipable_type,
            equipable_id:   i.equipable_id,
            quantity:       i.quantity,
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => { sendOpen.value = false; },
        onFinish:  () => { sendProcessing.value = false; },
    });
}

// ── Computed ───────────────────────────────────────────────────────────────
const selectedCafe = computed(() =>
    props.cafes.find(c => c.id === selectedCafeId.value) ?? null
);

const cafeDispatches = computed(() =>
    props.dispatches.filter(d => d.destination_id === selectedCafeId.value)
);

const filteredDispatches = computed(() => {
    if (activeTab.value === 'all')      return cafeDispatches.value;
    if (activeTab.value === 'computer') return cafeDispatches.value.filter(d => d.equipable_type === 'computer');
    if (activeTab.value === 'kitchen')  return cafeDispatches.value.filter(d => d.equipable_type === 'kitchen');
    return [];
});

const stats = computed(() => {
    const all = cafeDispatches.value;
    return {
        pending:   all.filter(d => !d.received_at).length,
        received:  all.filter(d =>  d.received_at).length,
        computers: all.filter(d => d.equipable_type === 'computer').length,
        kitchen:   all.filter(d => d.equipable_type === 'kitchen').length,
    };
});

function pendingForCafe(cafeId: number) {
    return props.dispatches.filter(d => d.destination_id === cafeId && !d.received_at).length;
}

function tabPending(key: TabKey) {
    if (key === 'all')      return cafeDispatches.value.filter(d => !d.received_at).length;
    if (key === 'computer') return cafeDispatches.value.filter(d => d.equipable_type === 'computer' && !d.received_at).length;
    if (key === 'kitchen')  return cafeDispatches.value.filter(d => d.equipable_type === 'kitchen'  && !d.received_at).length;
    return 0;
}

// ── Reception ──────────────────────────────────────────────────────────────
function doReceive(id: number) {
    processing.value = true;
    router.put(route('equipment-dispatches.receive', id), { reception_notes: receptionNote.value || null }, {
        preserveScroll: true,
        onFinish: () => { processing.value = false; confirmId.value = null; receptionNote.value = ''; },
    });
}

function startConfirm(id: number) {
    confirmId.value     = id;
    receptionNote.value = '';
}

const tabs: { key: TabKey; label: string; icon: any }[] = [
    { key: 'all',      label: 'Todos',       icon: PackageSearch },
    { key: 'computer', label: 'Tecnológico',  icon: Laptop },
    { key: 'kitchen',  label: 'Menaje',       icon: UtensilsCrossed },
    { key: 'epp',      label: 'EPP',          icon: ShieldCheck },
    { key: 'supplies', label: 'Insumos',      icon: HardHat },
];
</script>

<template>
    <Head title="Almacén · Recepciones por Café" />
    <AppLayout>
        <div class="flex h-full flex-col">

            <!-- ── Page header ── -->
            <div class="shrink-0 border-b bg-white px-6 py-4 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100">
                        <PackageCheck class="h-5 w-5 text-amber-600" />
                    </div>
                    <div>
                        <h1 class="text-lg font-bold leading-tight">Almacén — Recepciones por Café</h1>
                        <p class="text-xs text-slate-500">Equipos, menaje, EPP e insumos enviados a cada comedor</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-1 overflow-hidden">

                <!-- ── Sidebar: lista de cafés ── -->
                <aside class="w-60 shrink-0 overflow-y-auto border-r bg-slate-50 dark:bg-gray-800/40">
                    <p class="px-4 pt-4 pb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                        Comedores / Cafés
                    </p>
                    <ul class="space-y-0.5 px-2 pb-4">
                        <li v-for="cafe in cafes" :key="cafe.id">
                            <button
                                @click="selectedCafeId = cafe.id; activeTab = 'all'; confirmId = null"
                                class="group flex w-full items-start gap-2 rounded-lg px-3 py-2.5 text-left transition-colors"
                                :class="selectedCafeId === cafe.id
                                    ? 'bg-amber-500 text-white shadow-sm'
                                    : 'hover:bg-white dark:hover:bg-gray-700 text-slate-700 dark:text-slate-200'"
                            >
                                <Coffee
                                    class="mt-0.5 h-4 w-4 shrink-0"
                                    :class="selectedCafeId === cafe.id ? 'text-white' : 'text-amber-500'"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">{{ cafe.name }}</p>
                                    <p
                                        class="truncate text-[11px] leading-tight"
                                        :class="selectedCafeId === cafe.id ? 'text-amber-100' : 'text-slate-400'"
                                    >
                                        {{ cafe.unit?.name ?? '—' }}
                                        <span v-if="cafe.unit?.mine"> · {{ cafe.unit.mine.name }}</span>
                                    </p>
                                </div>
                                <span
                                    v-if="pendingForCafe(cafe.id) > 0"
                                    class="mt-0.5 shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                                    :class="selectedCafeId === cafe.id ? 'bg-white text-amber-600' : 'bg-amber-100 text-amber-700'"
                                >
                                    {{ pendingForCafe(cafe.id) }}
                                </span>
                            </button>
                        </li>
                    </ul>
                </aside>

                <!-- ── Main content ── -->
                <main class="flex-1 overflow-y-auto p-5">

                    <div v-if="!selectedCafe" class="flex h-60 flex-col items-center justify-center text-slate-400">
                        <Coffee class="mb-3 h-10 w-10 opacity-40" />
                        <p class="text-sm">Selecciona un comedor para ver sus envíos</p>
                    </div>

                    <template v-else>

                        <!-- Cafe title -->
                        <div class="mb-5 flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100">
                                <Coffee class="h-5 w-5 text-amber-600" />
                            </div>
                            <div class="flex-1">
                                <h2 class="text-xl font-bold">{{ selectedCafe.name }}</h2>
                                <p class="text-sm text-slate-500">
                                    {{ selectedCafe.unit?.name ?? '—' }}
                                    <span v-if="selectedCafe.unit?.mine"> · {{ selectedCafe.unit.mine.name }}</span>
                                </p>
                            </div>
                            <button
                                @click="openSendModal"
                                class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors"
                            >
                                <SendHorizonal class="h-4 w-4" />
                                Nueva Guía de Remisión
                            </button>
                        </div>

                        <!-- Stats -->
                        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-xl border bg-white p-4 shadow-sm dark:bg-gray-800">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50">
                                        <Truck class="h-4 w-4 text-blue-500" />
                                    </div>
                                    <div>
                                        <p class="text-[11px] text-slate-400">En tránsito</p>
                                        <p class="text-xl font-bold text-blue-600">{{ stats.pending }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-xl border bg-white p-4 shadow-sm dark:bg-gray-800">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50">
                                        <CheckCircle2 class="h-4 w-4 text-emerald-500" />
                                    </div>
                                    <div>
                                        <p class="text-[11px] text-slate-400">Recepcionados</p>
                                        <p class="text-xl font-bold text-emerald-600">{{ stats.received }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-xl border bg-white p-4 shadow-sm dark:bg-gray-800">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50">
                                        <Laptop class="h-4 w-4 text-blue-500" />
                                    </div>
                                    <div>
                                        <p class="text-[11px] text-slate-400">Tecnológico</p>
                                        <p class="text-xl font-bold">{{ stats.computers }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-xl border bg-white p-4 shadow-sm dark:bg-gray-800">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50">
                                        <UtensilsCrossed class="h-4 w-4 text-orange-500" />
                                    </div>
                                    <div>
                                        <p class="text-[11px] text-slate-400">Menaje</p>
                                        <p class="text-xl font-bold">{{ stats.kitchen }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs -->
                        <div class="mb-4 flex gap-1 rounded-xl border bg-slate-100 p-1 dark:bg-gray-800">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                @click="activeTab = tab.key; confirmId = null"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs font-semibold transition-all"
                                :class="activeTab === tab.key
                                    ? 'bg-white shadow text-slate-800 dark:bg-gray-700 dark:text-white'
                                    : 'text-slate-500 hover:text-slate-700'"
                            >
                                <component :is="tab.icon" class="h-3.5 w-3.5 shrink-0" />
                                <span class="hidden sm:inline">{{ tab.label }}</span>
                                <span
                                    v-if="tabPending(tab.key) > 0 && ['all','computer','kitchen'].includes(tab.key)"
                                    class="rounded-full bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold text-white"
                                >
                                    {{ tabPending(tab.key) }}
                                </span>
                            </button>
                        </div>

                        <!-- ── Equipment dispatches table ── -->
                        <template v-if="activeTab !== 'epp' && activeTab !== 'supplies'">

                            <div v-if="filteredDispatches.length === 0"
                                class="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-slate-400">
                                <PackageSearch class="mb-3 h-10 w-10 text-slate-300" />
                                <p class="font-medium">Sin envíos registrados</p>
                                <p class="mt-1 text-xs">No hay despachos de equipos para este comedor</p>
                            </div>

                            <div v-else class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-gray-800">
                                <table class="w-full text-sm">
                                    <thead class="border-b bg-slate-50 dark:bg-gray-700/50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">N° Despacho</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">Equipo</th>
                                            <th class="px-4 py-3 text-center text-xs font-bold text-slate-500">Cant.</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">Origen</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">Despachado</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">Estado</th>
                                            <th class="px-4 py-3 text-center text-xs font-bold text-slate-500">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <tr
                                            v-for="d in filteredDispatches"
                                            :key="d.id"
                                            class="transition-colors"
                                            :class="d.received_at
                                                ? 'bg-emerald-50/40 dark:bg-emerald-900/10'
                                                : 'hover:bg-slate-50 dark:hover:bg-gray-700/30'"
                                        >
                                            <!-- N° Despacho -->
                                            <td class="px-4 py-3">
                                                <p class="font-mono text-xs font-semibold text-slate-700 dark:text-slate-200">
                                                    {{ d.dispatch_number }}
                                                </p>
                                                <p v-if="d.guide_number" class="font-mono text-[10px] text-slate-400">
                                                    {{ d.guide_number }}
                                                </p>
                                            </td>

                                            <!-- Equipo -->
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <div
                                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                                                        :class="d.equipable_type === 'computer' ? 'bg-blue-100' : 'bg-orange-100'"
                                                    >
                                                        <Laptop v-if="d.equipable_type === 'computer'" class="h-3.5 w-3.5 text-blue-600" />
                                                        <UtensilsCrossed v-else class="h-3.5 w-3.5 text-orange-600" />
                                                    </div>
                                                    <div>
                                                        <p class="font-semibold leading-tight text-slate-800 dark:text-slate-100">
                                                            {{ d.equipment_name }}
                                                        </p>
                                                        <p class="text-[11px] text-slate-400">
                                                            {{ [d.equipment_brand, d.equipment_model].filter(Boolean).join(' · ') || '—' }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Cantidad -->
                                            <td class="px-4 py-3 text-center">
                                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 font-mono text-xs font-bold text-amber-700">
                                                    {{ d.quantity }}
                                                </span>
                                            </td>

                                            <!-- Origen -->
                                            <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                                                {{ d.origin_name }}
                                            </td>

                                            <!-- Despachado -->
                                            <td class="px-4 py-3">
                                                <p class="text-xs text-slate-700 dark:text-slate-200">{{ d.dispatched_at }}</p>
                                                <p class="text-[10px] text-slate-400">por {{ d.dispatched_by }}</p>
                                            </td>

                                            <!-- Estado -->
                                            <td class="px-4 py-3">
                                                <span
                                                    v-if="d.received_at"
                                                    class="inline-flex items-center gap-1 rounded-full border border-emerald-300 bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700"
                                                >
                                                    <CheckCircle2 class="h-3 w-3" />
                                                    Recepcionado
                                                </span>
                                                <span
                                                    v-else
                                                    class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-[11px] font-semibold text-blue-600"
                                                >
                                                    <Clock class="h-3 w-3" />
                                                    En tránsito
                                                </span>
                                            </td>

                                            <!-- Acción -->
                                            <td class="px-4 py-3">
                                                <!-- Ya recepcionado -->
                                                <template v-if="d.received_at">
                                                    <p class="text-[11px] text-slate-500">{{ d.received_by ?? '—' }}</p>
                                                    <p v-if="d.reception_notes" class="mt-1 max-w-[180px] rounded bg-slate-100 px-2 py-1 text-[10px] italic text-slate-500">
                                                        {{ d.reception_notes }}
                                                    </p>
                                                </template>

                                                <!-- Formulario de confirmación -->
                                                <template v-else-if="confirmId === d.id">
                                                    <div class="space-y-1.5">
                                                        <textarea
                                                            v-model="receptionNote"
                                                            placeholder="Observación (opcional)…"
                                                            rows="2"
                                                            class="w-full min-w-[180px] rounded-lg border border-slate-200 px-2 py-1.5 text-[11px] outline-none focus:border-emerald-400 focus:ring-1 focus:ring-emerald-200 resize-none"
                                                        />
                                                        <div class="flex gap-1">
                                                            <button
                                                                :disabled="processing"
                                                                class="flex-1 rounded-lg bg-emerald-600 py-1.5 text-[11px] font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                                                                @click="doReceive(d.id)"
                                                            >
                                                                Confirmar
                                                            </button>
                                                            <button
                                                                class="rounded-lg border px-2.5 py-1.5 text-[11px] font-semibold hover:bg-slate-50"
                                                                @click="confirmId = null"
                                                            >
                                                                Cancelar
                                                            </button>
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- Botón inicial -->
                                                <button
                                                    v-else
                                                    class="rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-[11px] font-bold text-emerald-700 shadow-sm hover:bg-emerald-50 dark:bg-transparent dark:hover:bg-emerald-900/20"
                                                    @click="startConfirm(d.id)"
                                                >
                                                    Recepcionar
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <!-- ── EPP placeholder ── -->
                        <div
                            v-else-if="activeTab === 'epp'"
                            class="flex flex-col items-center justify-center rounded-xl border border-dashed py-20 text-slate-400"
                        >
                            <ShieldCheck class="mb-3 h-10 w-10 text-slate-300" />
                            <p class="font-medium">EPP — Sin envíos registrados</p>
                            <p class="mt-1 text-xs">Los despachos de EPP aparecerán aquí cuando estén disponibles</p>
                        </div>

                        <!-- ── Insumos placeholder ── -->
                        <div
                            v-else-if="activeTab === 'supplies'"
                            class="flex flex-col items-center justify-center rounded-xl border border-dashed py-20 text-slate-400"
                        >
                            <HardHat class="mb-3 h-10 w-10 text-slate-300" />
                            <p class="font-medium">Insumos — Sin envíos registrados</p>
                            <p class="mt-1 text-xs">Los despachos de insumos aparecerán aquí cuando estén disponibles</p>
                        </div>

                    </template>
                </main>
            </div>
        </div>
    <!-- ── Send / Guía de Remisión Modal ── -->
    <Teleport to="body">
        <div
            v-if="sendOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="closeSendModal"
        >
            <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-800">

                <!-- Header -->
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-100">
                            <SendHorizonal class="h-4 w-4 text-indigo-600" />
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 dark:text-white">Nueva Guía de Remisión</h3>
                            <p class="text-xs text-slate-500">Desde: {{ selectedCafe?.name }}</p>
                        </div>
                    </div>
                    <button @click="closeSendModal" class="rounded-lg p-1 hover:bg-slate-100 dark:hover:bg-gray-700">
                        <X class="h-5 w-5 text-slate-500" />
                    </button>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-5">

                    <!-- Destination type -->
                    <div>
                        <p class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-400">Destino</p>
                        <div class="flex gap-2">
                            <button
                                @click="sendForm.destination_type = 'cafe'; sendForm.destination_id = ''"
                                class="flex flex-1 items-center justify-center gap-2 rounded-xl border-2 px-4 py-3 text-sm font-semibold transition-all"
                                :class="sendForm.destination_type === 'cafe'
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                    : 'border-slate-200 text-slate-500 hover:border-slate-300'"
                            >
                                <Coffee class="h-4 w-4" />
                                Otro Café / Comedor
                            </button>
                            <button
                                @click="sendForm.destination_type = 'headquarter'; sendForm.destination_id = ''"
                                class="flex flex-1 items-center justify-center gap-2 rounded-xl border-2 px-4 py-3 text-sm font-semibold transition-all"
                                :class="sendForm.destination_type === 'headquarter'
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                    : 'border-slate-200 text-slate-500 hover:border-slate-300'"
                            >
                                <Building2 class="h-4 w-4" />
                                Sede / Almacén
                            </button>
                        </div>

                        <select
                            v-model="sendForm.destination_id"
                            class="mt-3 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">— Seleccionar destino —</option>
                            <option v-for="opt in destOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                        </select>
                    </div>

                    <!-- Description -->
                    <div>
                        <p class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-400">Descripción (opcional)</p>
                        <textarea
                            v-model="sendForm.description"
                            rows="2"
                            placeholder="Motivo del traslado…"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 resize-none dark:bg-gray-700 dark:text-white"
                        />
                    </div>

                    <!-- Equipment list -->
                    <div>
                        <p class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-400">Equipos a enviar</p>
                        <div v-if="sendForm.items.length === 0" class="rounded-xl border border-dashed py-8 text-center text-sm text-slate-400">
                            No hay equipos recepcionados en este café
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="item in sendForm.items"
                                :key="`${item.equipable_type}-${item.equipable_id}`"
                                class="flex items-center gap-3 rounded-xl border bg-slate-50 px-4 py-3 dark:bg-gray-700/40"
                            >
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                    :class="item.equipable_type === 'computer' ? 'bg-blue-100' : 'bg-orange-100'"
                                >
                                    <Laptop v-if="item.equipable_type === 'computer'" class="h-4 w-4 text-blue-600" />
                                    <UtensilsCrossed v-else class="h-4 w-4 text-orange-600" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ item.equipment_name }}</p>
                                    <p class="text-[11px] text-slate-400">Disponible: {{ item.max }}</p>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button
                                        class="flex h-6 w-6 items-center justify-center rounded-full border text-slate-500 hover:bg-slate-200 disabled:opacity-30"
                                        :disabled="item.quantity <= 0"
                                        @click="item.quantity = Math.max(0, item.quantity - 1)"
                                    >−</button>
                                    <input
                                        type="number"
                                        v-model.number="item.quantity"
                                        :min="0"
                                        :max="item.max"
                                        class="w-14 rounded-lg border border-slate-200 px-2 py-1 text-center text-sm font-bold focus:outline-none focus:border-indigo-400 dark:bg-gray-700 dark:text-white"
                                    />
                                    <button
                                        class="flex h-6 w-6 items-center justify-center rounded-full border text-slate-500 hover:bg-slate-200 disabled:opacity-30"
                                        :disabled="item.quantity >= item.max"
                                        @click="item.quantity = Math.min(item.max, item.quantity + 1)"
                                    >+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between border-t px-6 py-4">
                    <p class="text-xs text-slate-400">
                        {{ sendableItems.length }} ítem(s) · Se generará una Guía de Remisión automáticamente
                    </p>
                    <div class="flex gap-2">
                        <button
                            @click="closeSendModal"
                            class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="submitSend"
                            :disabled="sendProcessing || !sendForm.destination_id || sendableItems.length === 0"
                            class="flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-bold text-white shadow hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                        >
                            <SendHorizonal class="h-4 w-4" />
                            {{ sendProcessing ? 'Enviando…' : 'Generar Guía' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    </AppLayout>
</template>
