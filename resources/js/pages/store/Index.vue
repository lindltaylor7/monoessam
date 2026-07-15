<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Building2,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
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
interface CafeUnit {
    id: number;
    name: string;
    mine: { id: number; name: string } | null;
}
interface Cafe {
    id: number;
    name: string;
    unit_id: number;
    unit: CafeUnit | null;
}
interface HQ {
    id: number;
    name: string;
    business: { id: number; name: string } | null;
}
interface UnitDest {
    id: number;
    name: string;
    mine: { id: number; name: string } | null;
}

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
    destination_type: string;
    destination_id: number;
    dispatched_by: string;
    dispatched_at: string;
    received_at: string | null;
    received_by: string | null;
    reception_notes: string | null;
}

const props = defineProps<{
    dispatches: Dispatch[];
    cafes: Cafe[];
    units: UnitDest[];
    allCafes: Cafe[];
    headquarters: HQ[];
    cafeStocks: Record<string, Record<string, number>>;
    unitStocks: Record<string, Record<string, number>>;
}>();

// ── State ──────────────────────────────────────────────────────────────────
type TabKey = 'all' | 'computer' | 'kitchen' | 'epp' | 'supplies';
type TargetType = 'cafe' | 'unit';

const selectedType = ref<TargetType>('cafe');
const selectedId = ref<number | null>(props.cafes[0]?.id ?? null);
const activeTab = ref<TabKey>('all');
const confirmId = ref<number | null>(null);
const receptionNote = ref('');
const processing = ref(false);
const showHistory = ref(false);

// ── Send modal ─────────────────────────────────────────────────────────────
const sendOpen = ref(false);
const sendForm = ref({
    destination_type: 'cafe' as 'cafe' | 'headquarter',
    destination_id: '' as string,
    description: '',
    items: [] as { equipable_type: 'computer' | 'kitchen'; equipable_id: number; equipment_name: string; quantity: number; max: number }[],
});
const sendProcessing = ref(false);

function openSendModal() {
    // "Disponible" se lee directo del ledger de stock del café (equipment_stocks), no del
    // historial de guías — el nombre/marca del equipo sí se toma de cualquier guía que lo
    // mencione, solo para mostrarlo en la lista.
    const stockForCafe = props.cafeStocks[String(selectedId.value)] ?? {};
    const byEquip: Record<string, (typeof sendForm.value)['items'][0]> = {};
    for (const [key, qty] of Object.entries(stockForCafe)) {
        if (qty <= 0) continue;
        const [equipableType, equipableIdStr] = key.split('-') as ['computer' | 'kitchen', string];
        const equipableId = Number(equipableIdStr);
        const match = props.dispatches.find((d) => d.equipable_type === equipableType && d.equipable_id === equipableId);
        byEquip[key] = {
            equipable_type: equipableType,
            equipable_id: equipableId,
            equipment_name: match?.equipment_name ?? '—',
            quantity: 0,
            max: qty,
        };
    }
    sendForm.value = {
        destination_type: 'cafe',
        destination_id: '',
        description: '',
        items: Object.values(byEquip).map((e) => ({ ...e, quantity: 0 })),
    };
    sendOpen.value = true;
}

function closeSendModal() {
    sendOpen.value = false;
}

// Stock actual del café/unidad seleccionado — una fila por equipo, sin repetirse aunque el
// mismo equipo aparezca en varias guías del historial. Independiente de la tabla de Despachos.
const currentLocationStock = computed(() => {
    const stocks = selectedType.value === 'cafe' ? props.cafeStocks : props.unitStocks;
    const stockMap = stocks[String(selectedId.value)] ?? {};
    return Object.entries(stockMap)
        .filter(([, qty]) => qty > 0)
        .map(([key, qty]) => {
            const [equipableType, equipableIdStr] = key.split('-') as ['computer' | 'kitchen', string];
            const equipableId = Number(equipableIdStr);
            const match = props.dispatches.find((d) => d.equipable_type === equipableType && d.equipable_id === equipableId);
            return {
                key,
                equipable_type: equipableType,
                equipment_name: match?.equipment_name ?? '—',
                equipment_brand: match?.equipment_brand ?? null,
                equipment_model: match?.equipment_model ?? null,
                quantity: qty,
            };
        })
        .sort((a, b) => a.equipment_name.localeCompare(b.equipment_name));
});

const sendableItems = computed(() => sendForm.value.items.filter((i) => i.quantity > 0));

const destOptions = computed(() => {
    if (sendForm.value.destination_type === 'cafe') {
        return props.allCafes.filter((c) => c.id !== selectedId.value).map((c) => ({ id: String(c.id), label: `${c.name} — ${c.unit?.name ?? ''}` }));
    }
    return props.headquarters.map((h) => ({
        id: String(h.id),
        label: `${h.name}${h.business ? ' · ' + h.business.name : ''}`,
    }));
});

function submitSend() {
    if (selectedType.value !== 'cafe' || !selectedId.value || !sendForm.value.destination_id || sendableItems.value.length === 0) return;
    sendProcessing.value = true;
    router.post(
        route('store.dispatch'),
        {
            origin_cafe_id: selectedId.value,
            destination_type: sendForm.value.destination_type,
            destination_id: Number(sendForm.value.destination_id),
            description: sendForm.value.description || null,
            items: sendableItems.value.map((i) => ({
                equipable_type: i.equipable_type,
                equipable_id: i.equipable_id,
                quantity: i.quantity,
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                sendOpen.value = false;
            },
            onFinish: () => {
                sendProcessing.value = false;
            },
        },
    );
}

// ── Computed ───────────────────────────────────────────────────────────────
const selectedCafe = computed(() => (selectedType.value === 'cafe' ? (props.cafes.find((c) => c.id === selectedId.value) ?? null) : null));

const selectedUnit = computed(() => (selectedType.value === 'unit' ? (props.units.find((u) => u.id === selectedId.value) ?? null) : null));

const targetDispatches = computed(() =>
    props.dispatches.filter((d) => d.destination_type === selectedType.value && d.destination_id === selectedId.value),
);

const filteredDispatches = computed(() => {
    if (activeTab.value === 'all') return targetDispatches.value;
    if (activeTab.value === 'computer') return targetDispatches.value.filter((d) => d.equipable_type === 'computer');
    if (activeTab.value === 'kitchen') return targetDispatches.value.filter((d) => d.equipable_type === 'kitchen');
    return [];
});

const stats = computed(() => {
    const all = targetDispatches.value;
    return {
        pending: all.filter((d) => !d.received_at).length,
        received: all.filter((d) => d.received_at).length,
        computers: all.filter((d) => d.equipable_type === 'computer').length,
        kitchen: all.filter((d) => d.equipable_type === 'kitchen').length,
    };
});

function pendingForCafe(cafeId: number) {
    return props.dispatches.filter((d) => d.destination_type === 'cafe' && d.destination_id === cafeId && !d.received_at).length;
}

function pendingForUnit(unitId: number) {
    return props.dispatches.filter((d) => d.destination_type === 'unit' && d.destination_id === unitId && !d.received_at).length;
}

function tabPending(key: TabKey) {
    if (key === 'all') return targetDispatches.value.filter((d) => !d.received_at).length;
    if (key === 'computer') return targetDispatches.value.filter((d) => d.equipable_type === 'computer' && !d.received_at).length;
    if (key === 'kitchen') return targetDispatches.value.filter((d) => d.equipable_type === 'kitchen' && !d.received_at).length;
    return 0;
}

// ── Reception ──────────────────────────────────────────────────────────────
function doReceive(id: number) {
    processing.value = true;
    router.put(
        route('equipment-dispatches.receive', id),
        { reception_notes: receptionNote.value || null },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                confirmId.value = null;
                receptionNote.value = '';
            },
        },
    );
}

function startConfirm(id: number) {
    confirmId.value = id;
    receptionNote.value = '';
}

const tabs: { key: TabKey; label: string; icon: any }[] = [
    { key: 'all', label: 'Todos', icon: PackageSearch },
    { key: 'computer', label: 'Tecnológico', icon: Laptop },
    { key: 'kitchen', label: 'Menaje', icon: UtensilsCrossed },
    { key: 'epp', label: 'EPP', icon: ShieldCheck },
    { key: 'supplies', label: 'Insumos', icon: HardHat },
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
                        <h1 class="text-lg leading-tight font-bold">Almacén — Recepciones por Café</h1>
                        <p class="text-xs text-slate-500">Equipos, menaje, EPPs e insumos enviados a cada comedor</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-1 overflow-hidden">
                <!-- ── Sidebar: unidades (general) + lista de cafés ── -->
                <aside class="w-60 shrink-0 overflow-y-auto border-r bg-slate-50 dark:bg-gray-800/40">
                    <p class="px-4 pt-4 pb-2 text-[10px] font-bold tracking-widest text-slate-400 uppercase">Unidades (envío general)</p>
                    <ul class="space-y-0.5 px-2 pb-2">
                        <li v-for="unit in units" :key="`unit-${unit.id}`">
                            <button
                                @click="
                                    selectedType = 'unit';
                                    selectedId = unit.id;
                                    activeTab = 'all';
                                    confirmId = null;
                                "
                                class="group flex w-full items-start gap-2 rounded-lg px-3 py-2.5 text-left transition-colors"
                                :class="
                                    selectedType === 'unit' && selectedId === unit.id
                                        ? 'bg-indigo-600 text-white shadow-sm'
                                        : 'text-slate-700 hover:bg-white dark:text-slate-200 dark:hover:bg-gray-700'
                                "
                            >
                                <Building2
                                    class="mt-0.5 h-4 w-4 shrink-0"
                                    :class="selectedType === 'unit' && selectedId === unit.id ? 'text-white' : 'text-indigo-500'"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">{{ unit.name }}</p>
                                    <p
                                        class="truncate text-[11px] leading-tight"
                                        :class="selectedType === 'unit' && selectedId === unit.id ? 'text-indigo-100' : 'text-slate-400'"
                                    >
                                        Envíos sin café específico
                                    </p>
                                </div>
                                <span
                                    v-if="pendingForUnit(unit.id) > 0"
                                    class="mt-0.5 shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                                    :class="
                                        selectedType === 'unit' && selectedId === unit.id
                                            ? 'bg-white text-indigo-600'
                                            : 'bg-indigo-100 text-indigo-700'
                                    "
                                >
                                    {{ pendingForUnit(unit.id) }}
                                </span>
                            </button>
                        </li>
                    </ul>

                    <p class="px-4 pt-3 pb-2 text-[10px] font-bold tracking-widest text-slate-400 uppercase">Comedores / Cafés</p>
                    <ul class="space-y-0.5 px-2 pb-4">
                        <li v-for="cafe in cafes" :key="cafe.id">
                            <button
                                @click="
                                    selectedType = 'cafe';
                                    selectedId = cafe.id;
                                    activeTab = 'all';
                                    confirmId = null;
                                "
                                class="group flex w-full items-start gap-2 rounded-lg px-3 py-2.5 text-left transition-colors"
                                :class="
                                    selectedType === 'cafe' && selectedId === cafe.id
                                        ? 'bg-amber-500 text-white shadow-sm'
                                        : 'text-slate-700 hover:bg-white dark:text-slate-200 dark:hover:bg-gray-700'
                                "
                            >
                                <Coffee
                                    class="mt-0.5 h-4 w-4 shrink-0"
                                    :class="selectedType === 'cafe' && selectedId === cafe.id ? 'text-white' : 'text-amber-500'"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">{{ cafe.name }}</p>
                                    <p
                                        class="truncate text-[11px] leading-tight"
                                        :class="selectedType === 'cafe' && selectedId === cafe.id ? 'text-amber-100' : 'text-slate-400'"
                                    >
                                        {{ cafe.unit?.name ?? '—' }}
                                        <span v-if="cafe.unit?.mine"> · {{ cafe.unit.mine.name }}</span>
                                    </p>
                                </div>
                                <span
                                    v-if="pendingForCafe(cafe.id) > 0"
                                    class="mt-0.5 shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                                    :class="
                                        selectedType === 'cafe' && selectedId === cafe.id ? 'bg-white text-amber-600' : 'bg-amber-100 text-amber-700'
                                    "
                                >
                                    {{ pendingForCafe(cafe.id) }}
                                </span>
                            </button>
                        </li>
                    </ul>
                </aside>

                <!-- ── Main content ── -->
                <main class="flex-1 overflow-y-auto p-5">
                    <div v-if="!selectedCafe && !selectedUnit" class="flex h-60 flex-col items-center justify-center text-slate-400">
                        <Coffee class="mb-3 h-10 w-10 opacity-40" />
                        <p class="text-sm">Selecciona un comedor o una unidad para ver sus envíos</p>
                    </div>

                    <template v-else>
                        <!-- Título: café o unidad -->
                        <div class="mb-5 flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl"
                                :class="selectedCafe ? 'bg-amber-100' : 'bg-indigo-100'"
                            >
                                <Coffee v-if="selectedCafe" class="h-5 w-5 text-amber-600" />
                                <Building2 v-else class="h-5 w-5 text-indigo-600" />
                            </div>
                            <div class="flex-1">
                                <h2 class="text-xl font-bold">{{ selectedCafe?.name ?? selectedUnit?.name }}</h2>
                                <p v-if="selectedCafe" class="text-sm text-slate-500">
                                    {{ selectedCafe.unit?.name ?? '—' }}
                                    <span v-if="selectedCafe.unit?.mine"> · {{ selectedCafe.unit.mine.name }}</span>
                                </p>
                                <p v-else class="text-sm text-slate-500">Envíos dirigidos a la unidad en general, sin café específico</p>
                            </div>
                            <button
                                v-if="selectedCafe"
                                @click="openSendModal"
                                class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700"
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

                        <!-- Stock actual: una fila por equipo, sin repetirse aunque el historial tenga varias guías -->
                        <div class="mb-5 overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-gray-800">
                            <p class="border-b bg-slate-50 px-4 py-2.5 text-xs font-bold tracking-widest text-slate-500 uppercase dark:bg-gray-700/50">
                                Stock actual en {{ selectedCafe?.name ?? selectedUnit?.name }}
                            </p>
                            <div v-if="currentLocationStock.length === 0" class="px-4 py-6 text-center text-sm text-slate-400">
                                Sin equipos disponibles aquí por ahora
                            </div>
                            <ul v-else class="divide-y">
                                <li
                                    v-for="item in currentLocationStock"
                                    :key="item.key"
                                    class="flex items-center justify-between gap-3 px-4 py-2.5"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <div
                                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                                            :class="item.equipable_type === 'computer' ? 'bg-blue-100' : 'bg-orange-100'"
                                        >
                                            <Laptop v-if="item.equipable_type === 'computer'" class="h-3.5 w-3.5 text-blue-600" />
                                            <UtensilsCrossed v-else class="h-3.5 w-3.5 text-orange-600" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ item.equipment_name }}</p>
                                            <p class="text-[11px] text-slate-400">
                                                {{ [item.equipment_brand, item.equipment_model].filter(Boolean).join(' · ') || '—' }}
                                            </p>
                                        </div>
                                    </div>
                                    <span
                                        class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 font-mono text-xs font-bold text-amber-700"
                                    >
                                        {{ item.quantity }}
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <!-- Historial de despachos: colapsado por defecto para no competir con "Stock actual" -->
                        <button
                            @click="showHistory = !showHistory"
                            class="mb-4 flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                        >
                            <component :is="showHistory ? ChevronUp : ChevronDown" class="h-4 w-4" />
                            {{ showHistory ? 'Ocultar' : 'Ver' }} historial de despachos
                        </button>

                        <template v-if="showHistory">
                        <!-- Tabs -->
                        <div class="mb-4 flex gap-1 rounded-xl border bg-slate-100 p-1 dark:bg-gray-800">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                @click="
                                    activeTab = tab.key;
                                    confirmId = null;
                                "
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs font-semibold transition-all"
                                :class="
                                    activeTab === tab.key
                                        ? 'bg-white text-slate-800 shadow dark:bg-gray-700 dark:text-white'
                                        : 'text-slate-500 hover:text-slate-700'
                                "
                            >
                                <component :is="tab.icon" class="h-3.5 w-3.5 shrink-0" />
                                <span class="hidden sm:inline">{{ tab.label }}</span>
                                <span
                                    v-if="tabPending(tab.key) > 0 && ['all', 'computer', 'kitchen'].includes(tab.key)"
                                    class="rounded-full bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold text-white"
                                >
                                    {{ tabPending(tab.key) }}
                                </span>
                            </button>
                        </div>

                        <!-- ── Equipment dispatches table ── -->
                        <template v-if="activeTab !== 'epp' && activeTab !== 'supplies'">
                            <div
                                v-if="filteredDispatches.length === 0"
                                class="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-slate-400"
                            >
                                <PackageSearch class="mb-3 h-10 w-10 text-slate-300" />
                                <p class="font-medium">Sin envíos registrados</p>
                                <p class="mt-1 text-xs">No hay despachos de equipos para este comedor</p>
                            </div>

                            <div v-else class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-gray-800">
                                <table class="w-full text-sm">
                                    <thead class="border-b bg-slate-50 dark:bg-gray-700/50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">N° Despacho</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">Guía</th>
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
                                            :class="
                                                d.received_at
                                                    ? 'bg-emerald-50/40 dark:bg-emerald-900/10'
                                                    : 'hover:bg-slate-50 dark:hover:bg-gray-700/30'
                                            "
                                        >
                                            <!-- N° Despacho -->
                                            <td class="px-4 py-3">
                                                <p class="font-mono text-xs font-semibold text-slate-700 dark:text-slate-200">
                                                    {{ d.dispatch_number }}
                                                </p>
                                            </td>

                                            <!-- Guía -->
                                            <td class="px-4 py-3">
                                                <p v-if="d.guide_number" class="font-mono text-xs text-slate-600 dark:text-slate-300">
                                                    {{ d.guide_number }}
                                                </p>
                                                <span v-else class="text-xs text-slate-300">—</span>
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
                                                        <p class="leading-tight font-semibold text-slate-800 dark:text-slate-100">
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
                                                <span
                                                    class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 font-mono text-xs font-bold text-amber-700"
                                                >
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
                                                    <p
                                                        v-if="d.reception_notes"
                                                        class="mt-1 max-w-[180px] rounded bg-slate-100 px-2 py-1 text-[10px] text-slate-500 italic"
                                                    >
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
                                                            class="w-full min-w-[180px] resize-none rounded-lg border border-slate-200 px-2 py-1.5 text-[11px] outline-none focus:border-emerald-400 focus:ring-1 focus:ring-emerald-200"
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
                    </template>
                </main>
            </div>
        </div>
        <!-- ── Send / Guía de Remisión Modal ── -->
        <Teleport to="body">
            <div v-if="sendOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeSendModal">
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
                    <div class="flex-1 space-y-5 overflow-y-auto px-6 py-4">
                        <!-- Destination type -->
                        <div>
                            <p class="mb-2 text-xs font-bold tracking-widest text-slate-400 uppercase">Destino</p>
                            <div class="flex gap-2">
                                <button
                                    @click="
                                        sendForm.destination_type = 'cafe';
                                        sendForm.destination_id = '';
                                    "
                                    class="flex flex-1 items-center justify-center gap-2 rounded-xl border-2 px-4 py-3 text-sm font-semibold transition-all"
                                    :class="
                                        sendForm.destination_type === 'cafe'
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                            : 'border-slate-200 text-slate-500 hover:border-slate-300'
                                    "
                                >
                                    <Coffee class="h-4 w-4" />
                                    Otro Café / Comedor
                                </button>
                                <button
                                    @click="
                                        sendForm.destination_type = 'headquarter';
                                        sendForm.destination_id = '';
                                    "
                                    class="flex flex-1 items-center justify-center gap-2 rounded-xl border-2 px-4 py-3 text-sm font-semibold transition-all"
                                    :class="
                                        sendForm.destination_type === 'headquarter'
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                            : 'border-slate-200 text-slate-500 hover:border-slate-300'
                                    "
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
                            <p class="mb-2 text-xs font-bold tracking-widest text-slate-400 uppercase">Descripción (opcional)</p>
                            <textarea
                                v-model="sendForm.description"
                                rows="2"
                                placeholder="Motivo del traslado…"
                                class="w-full resize-none rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 dark:bg-gray-700 dark:text-white"
                            />
                        </div>

                        <!-- Equipment list -->
                        <div>
                            <p class="mb-2 text-xs font-bold tracking-widest text-slate-400 uppercase">Equipos a enviar</p>
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
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ item.equipment_name }}</p>
                                        <p class="text-[11px] text-slate-400">Disponible: {{ item.max }}</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <button
                                            class="flex h-6 w-6 items-center justify-center rounded-full border text-slate-500 hover:bg-slate-200 disabled:opacity-30"
                                            :disabled="item.quantity <= 0"
                                            @click="item.quantity = Math.max(0, item.quantity - 1)"
                                        >
                                            −
                                        </button>
                                        <input
                                            type="number"
                                            v-model.number="item.quantity"
                                            :min="0"
                                            :max="item.max"
                                            class="w-14 rounded-lg border border-slate-200 px-2 py-1 text-center text-sm font-bold focus:border-indigo-400 focus:outline-none dark:bg-gray-700 dark:text-white"
                                        />
                                        <button
                                            class="flex h-6 w-6 items-center justify-center rounded-full border text-slate-500 hover:bg-slate-200 disabled:opacity-30"
                                            :disabled="item.quantity >= item.max"
                                            @click="item.quantity = Math.min(item.max, item.quantity + 1)"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-between border-t px-6 py-4">
                        <p class="text-xs text-slate-400">{{ sendableItems.length }} ítem(s) · Se generará una Guía de Remisión automáticamente</p>
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
                                class="flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-bold text-white shadow transition-colors hover:bg-indigo-700 disabled:opacity-50"
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
