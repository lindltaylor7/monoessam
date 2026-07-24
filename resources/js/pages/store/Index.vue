<script setup lang="ts">
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Building2,
    CheckCircle2,
    Clock,
    Coffee,
    FileCheck,
    FileSpreadsheet,
    FileText,
    FileX,
    HardHat,
    Laptop,
    PackageCheck,
    PackageSearch,
    Paperclip,
    SendHorizonal,
    ShieldCheck,
    Truck,
    Upload,
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
    equipable_type: 'computer' | 'kitchen' | 'epp';
    equipable_id: number;
    quantity: number;
    size?: string | null;
    color_name?: string | null;
    equipment_name: string;
    equipment_brand: string | null;
    equipment_model: string | null;
    equipment_code: string | null;
    equipment_series: string | null;
    equipment_status: number | null;
    origin_cafe_id: number | null;
    origin_name: string;
    destination_type: string;
    destination_id: number;
    destination_name: string;
    dispatched_by: string;
    dispatched_at: string;
    received_at: string | null;
    received_by: string | null;
    reception_notes: string | null;
}

interface StockItem {
    id: number;
    equipable_id: number;
    equipable_type: 'computer' | 'kitchen' | 'epp';
    equipment_name: string;
    equipment_brand: string | null;
    equipment_model: string | null;
    equipment_code: string | null;
    equipment_series: string | null;
    equipment_status: number | null;
    size?: string | null;
    color_name?: string | null;
    responsible_id: number | null;
    responsible_name: string | null;
    cargo_path?: string | null;
    quantity: number;
}

interface StaffOption {
    id: number;
    name: string;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLinks[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
}

interface Stats {
    pending: number;
    received: number;
    computers: number;
    kitchen: number;
    epp: number;
    pending_computer: number;
    pending_kitchen: number;
    pending_epp: number;
}

interface StockLedgerEntry {
    quantity: number;
    name: string;
    brand: string | null;
    model: string | null;
}

interface EppStockLedgerEntry {
    epp_id: number;
    name: string;
    quantity: number;
    size: string | null;
    color_id: number | null;
    color_name: string | null;
}

const props = defineProps<{
    dispatches: Paginated<Dispatch>;
    stock: Paginated<StockItem>;
    cafes: Cafe[];
    units: UnitDest[];
    allCafes: Cafe[];
    headquarters: HQ[];
    cafeStocks: Record<string, Record<string, StockLedgerEntry>>;
    unitStocks: Record<string, Record<string, StockLedgerEntry>>;
    cafeEppStocks: Record<string, EppStockLedgerEntry[]>;
    staffOptions: StaffOption[];
    stats: Stats;
    pendingByCafe: Record<number, number>;
    pendingByUnit: Record<number, number>;
    filters: {
        location_type: 'cafe' | 'unit';
        location_id: number;
        type: 'all' | 'computer' | 'kitchen' | 'epp' | 'supplies';
    };
}>();

// ── State ──────────────────────────────────────────────────────────────────
type TabKey = 'all' | 'computer' | 'kitchen' | 'epp' | 'supplies';
type TargetType = 'cafe' | 'unit';
type ViewSection = 'guides' | 'stock';

const selectedType = ref<TargetType>(props.filters.location_type);
const selectedId = ref<number | null>(props.filters.location_id || (props.cafes[0]?.id ?? null));
const activeTab = ref<TabKey>(props.filters.type);
const viewSection = ref<ViewSection>('stock');
const confirmId = ref<number | null>(null);
const receptionNote = ref('');
const processing = ref(false);

// ── Send modal ─────────────────────────────────────────────────────────────
const sendOpen = ref(false);
type SendItem = {
    equipable_type: 'computer' | 'kitchen' | 'epp';
    equipable_id: number;
    equipment_name: string;
    quantity: number;
    max: number;
    size?: string | null;
    color_id?: number | null;
    color_name?: string | null;
};
const sendForm = ref({
    destination_type: 'cafe' as 'cafe' | 'headquarter',
    destination_id: '' as string,
    description: '',
    items: [] as SendItem[],
});
const sendProcessing = ref(false);

function openSendModal() {
    // "Disponible" y el nombre del ítem se leen directo del ledger de stock del café
    // (equipment_stocks / InventoryStock para EPP) — no dependen de qué página de guías/stock
    // esté cargada.
    const stockForCafe = props.cafeStocks[String(selectedId.value)] ?? {};
    const byEquip: Record<string, SendItem> = {};
    for (const [key, entry] of Object.entries(stockForCafe)) {
        if (entry.quantity <= 0) continue;
        const [equipableType, equipableIdStr] = key.split('-') as ['computer' | 'kitchen', string];
        const equipableId = Number(equipableIdStr);
        byEquip[key] = {
            equipable_type: equipableType,
            equipable_id: equipableId,
            equipment_name: entry.name,
            quantity: 0,
            max: entry.quantity,
        };
    }

    const eppStockForCafe = props.cafeEppStocks[String(selectedId.value)] ?? [];
    for (const entry of eppStockForCafe) {
        if (entry.quantity <= 0) continue;
        const key = `epp-${entry.epp_id}-${entry.size ?? ''}-${entry.color_id ?? ''}`;
        byEquip[key] = {
            equipable_type: 'epp',
            equipable_id: entry.epp_id,
            equipment_name: entry.name,
            quantity: 0,
            max: entry.quantity,
            size: entry.size,
            color_id: entry.color_id,
            color_name: entry.color_name,
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
                size: i.size ?? null,
                color_id: i.color_id ?? null,
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

// Enlace directo de descarga (GET, sin axios) — abre/descarga el Excel con todo el stock
// (Tecnológico, Menaje, EPP) del café/unidad seleccionado actualmente.
const exportHref = computed(() => {
    if (!selectedId.value) return '#';
    return route('store.export', { location_type: selectedType.value, location_id: selectedId.value });
});

// Agrupa los despachos por guía de remisión (ya vienen filtrados y paginados desde el backend
// — 10 guías por página) — igual al patrón de "Desde Café" en Inventario: una tarjeta por guía,
// con sus equipos listados debajo. `is_outgoing` indica si el café/unidad seleccionado es el
// ORIGEN de la guía (salió de aquí) en vez del destino (llegó aquí) — cambia qué se muestra en
// la cabecera y si corresponde ofrecer la acción de recepcionar.
interface DispatchGroup {
    key: string;
    guide_number: string | null;
    items: Dispatch[];
    origin_name: string;
    destination_name: string;
    is_outgoing: boolean;
    dispatched_at: string;
    dispatched_by: string;
}

const groupedDispatches = computed((): DispatchGroup[] => {
    const map = new Map<string, DispatchGroup>();
    for (const d of props.dispatches.data) {
        const key = d.guide_number ?? `solo-${d.id}`;
        if (map.has(key)) {
            map.get(key)!.items.push(d);
        } else {
            map.set(key, {
                key,
                guide_number: d.guide_number,
                items: [d],
                origin_name: d.origin_name,
                destination_name: d.destination_name,
                is_outgoing: selectedType.value === 'cafe' && d.origin_cafe_id === selectedId.value,
                dispatched_at: d.dispatched_at,
                dispatched_by: d.dispatched_by,
            });
        }
    }
    return [...map.values()];
});

function pendingForCafe(cafeId: number) {
    return props.pendingByCafe[cafeId] ?? 0;
}

function pendingForUnit(unitId: number) {
    return props.pendingByUnit[unitId] ?? 0;
}

// ── Selección de café/unidad y filtro por tipo: paginación y filtrado ocurren en el backend,
// así que cambiar cualquiera de estos dispara una nueva visita (reseteando ambas páginas a 1).
function reload(overrides: Partial<{ location_type: TargetType; location_id: number; type: TabKey }> = {}) {
    router.get(
        route('store'),
        {
            location_type: overrides.location_type ?? selectedType.value,
            location_id: overrides.location_id ?? selectedId.value,
            type: overrides.type ?? activeTab.value,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function selectCafe(cafeId: number) {
    selectedType.value = 'cafe';
    selectedId.value = cafeId;
    activeTab.value = 'all';
    confirmId.value = null;
    reload({ location_type: 'cafe', location_id: cafeId, type: 'all' });
}

function selectUnit(unitId: number) {
    selectedType.value = 'unit';
    selectedId.value = unitId;
    activeTab.value = 'all';
    confirmId.value = null;
    reload({ location_type: 'unit', location_id: unitId, type: 'all' });
}

function selectTypeTab(key: TabKey) {
    activeTab.value = key;
    confirmId.value = null;
    reload({ type: key });
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

// ── Asignación de responsable y Cargo (Stock) ─────────────────────────────────
const assigningStock = ref<number | null>(null);
const assignModalOpen = ref(false);
const targetAssignItem = ref<StockItem | null>(null);
const targetStaffId = ref<string>('');
const cargoFile = ref<File | null>(null);
const assignNotes = ref<string>('');
const assignProcessing = ref(false);

function onStaffSelectChange(item: StockItem, staffId: string) {
    if (!staffId) {
        assigningStock.value = item.id;
        router.post(
            route('equipments.history.store', { type: item.equipable_type, id: item.equipable_id }),
            { action: 'Asignación', staff_id: null },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    assigningStock.value = null;
                },
            },
        );
        return;
    }
    openAssignModal(item, staffId);
}

function openAssignModal(item: StockItem, staffId: string) {
    targetAssignItem.value = item;
    targetStaffId.value = staffId;
    cargoFile.value = null;
    assignNotes.value = '';
    assignModalOpen.value = true;
}

function closeAssignModal() {
    assignModalOpen.value = false;
    targetAssignItem.value = null;
    targetStaffId.value = '';
    cargoFile.value = null;
    assignNotes.value = '';
}

const targetStaffName = computed(() => {
    if (!targetStaffId.value) return '';
    const found = props.staffOptions.find((s) => String(s.id) === String(targetStaffId.value));
    return found ? found.name : '';
});

function handleCargoFileUpload(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        cargoFile.value = input.files[0];
    }
}

function submitAssignment(withCargo: boolean) {
    if (!targetAssignItem.value || !targetStaffId.value) return;

    assignProcessing.value = true;
    assigningStock.value = targetAssignItem.value.id;

    const payload: Record<string, any> = {
        action: 'Asignación',
        staff_id: targetStaffId.value,
        notes: assignNotes.value || null,
    };

    if (withCargo && cargoFile.value) {
        payload.cargo_file = cargoFile.value;
    }

    router.post(
        route('equipments.history.store', {
            type: targetAssignItem.value.equipable_type,
            id: targetAssignItem.value.equipable_id,
        }),
        payload,
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeAssignModal();
            },
            onFinish: () => {
                assignProcessing.value = false;
                assigningStock.value = null;
            },
        },
    );
}

const tabs: { key: TabKey; label: string; icon: any }[] = [
    { key: 'all', label: 'Todos', icon: PackageSearch },
    { key: 'computer', label: 'Tecnológico', icon: Laptop },
    { key: 'kitchen', label: 'Menaje', icon: UtensilsCrossed },
    { key: 'epp', label: 'EPP', icon: ShieldCheck },
    { key: 'supplies', label: 'Insumos', icon: HardHat },
];

const EQUIPMENT_STATUSES = [
    { value: 0, label: 'Nuevo', cls: 'bg-blue-100 text-blue-700 border-blue-200' },
    { value: 1, label: 'Bueno', cls: 'bg-green-100 text-green-700 border-green-200' },
    { value: 2, label: 'Regular', cls: 'bg-yellow-100 text-yellow-700 border-yellow-200' },
    { value: 3, label: 'Dañado', cls: 'bg-red-100 text-red-700 border-red-200' },
    { value: 4, label: 'Baja', cls: 'bg-gray-100 text-gray-600 border-gray-200' },
];

function equipmentStatusInfo(val: number | null) {
    return EQUIPMENT_STATUSES.find((s) => s.value === val) ?? EQUIPMENT_STATUSES[0];
}
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
                                @click="selectUnit(unit.id)"
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
                                    title="Guías entrantes pendientes de recepcionar en esta unidad"
                                    class="mt-0.5 shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                                    :class="
                                        selectedType === 'unit' && selectedId === unit.id
                                            ? 'bg-white text-rose-600'
                                            : 'bg-rose-100 text-rose-700'
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
                                @click="selectCafe(cafe.id)"
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
                                    title="Guías entrantes pendientes de recepcionar en este café"
                                    class="mt-0.5 shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                                    :class="
                                        selectedType === 'cafe' && selectedId === cafe.id ? 'bg-white text-rose-600' : 'bg-rose-100 text-rose-700'
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
                            <a
                                :href="exportHref"
                                target="_blank"
                                class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 shadow-sm transition-colors hover:bg-emerald-100"
                            >
                                <FileSpreadsheet class="h-4 w-4" />
                                Exportar Excel
                            </a>
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

                        <!-- Sección: Guías vs Stock -->
                        <Tabs :model-value="viewSection" @update:model-value="(v) => (viewSection = v as ViewSection)" class="mb-4">
                            <TabsList class="bg-slate-100 p-1 dark:bg-gray-800">
                                <TabsTrigger
                                    value="stock"
                                    class="gap-2 rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm dark:data-[state=active]:bg-gray-700"
                                >
                                    <PackageCheck class="h-4 w-4" />
                                    Stock
                                </TabsTrigger>
                                <TabsTrigger
                                    value="guides"
                                    class="relative gap-2 rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm dark:data-[state=active]:bg-gray-700"
                                >
                                    <FileText class="h-4 w-4" />
                                    Guías
                                    <span
                                        v-if="stats.pending > 0"
                                        title="Total en tránsito: guías entrantes + salientes sin recepcionar"
                                        class="absolute -top-1.5 -right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[9px] font-black text-white"
                                    >
                                        {{ stats.pending }}
                                    </span>
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>

                        <!-- Filtro por tipo: solo aplica a Stock — una Guía puede traer equipos, menaje
                        y EPP a la vez, así que filtrarla por tipo dejaría cada guía mostrada a medias. -->
                        <Tabs v-if="viewSection === 'stock'" :model-value="activeTab" @update:model-value="(v) => selectTypeTab(v as TabKey)" class="mb-4">
                            <TabsList class="w-full bg-slate-100 p-1 dark:bg-gray-800">
                                <TabsTrigger
                                    v-for="tab in tabs"
                                    :key="tab.key"
                                    :value="tab.key"
                                    class="relative flex-1 gap-2 rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm dark:data-[state=active]:bg-gray-700"
                                >
                                    <component :is="tab.icon" class="h-3.5 w-3.5 shrink-0" />
                                    <span class="hidden sm:inline">{{ tab.label }}</span>
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>

                        <!-- ═══ Guías: una tarjeta por guía de remisión, igual al patrón de "Desde Café" ═══ -->
                        <!-- No se filtra por tipo: una misma guía puede traer equipos, menaje y EPP juntos. -->
                        <template v-if="viewSection === 'guides'">
                            <div
                                v-if="groupedDispatches.length === 0"
                                    class="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-slate-400"
                                >
                                    <PackageSearch class="mb-3 h-10 w-10 text-slate-300" />
                                    <p class="font-medium">Sin envíos registrados</p>
                                    <p class="mt-1 text-xs">No hay despachos de equipos para este comedor</p>
                                </div>

                                <div v-else class="space-y-4 pb-2">
                                    <div
                                        v-for="group in groupedDispatches"
                                        :key="group.key"
                                        class="bg-card overflow-hidden rounded-2xl border shadow-sm transition-shadow hover:shadow-md dark:bg-gray-800"
                                        :class="[
                                            group.items.every((i) => i.received_at) ? 'border-l-[3px] border-l-emerald-400' : 'border-l-[3px]',
                                            !group.items.every((i) => i.received_at) && group.is_outgoing ? 'border-l-amber-400' : '',
                                            !group.items.every((i) => i.received_at) && !group.is_outgoing ? 'border-l-indigo-400' : '',
                                        ]"
                                    >
                                        <!-- Cabecera de la guía -->
                                        <div
                                            class="bg-muted/30 flex flex-col gap-3 border-b px-5 py-3.5 lg:flex-row lg:items-center lg:justify-between dark:bg-gray-700/30"
                                        >
                                            <div class="flex items-center gap-2.5">
                                                <div
                                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                                                    :class="group.is_outgoing ? 'bg-amber-100' : 'bg-indigo-100'"
                                                >
                                                    <SendHorizonal v-if="group.is_outgoing" class="h-4 w-4 text-amber-600" />
                                                    <FileText v-else class="h-4 w-4 text-indigo-600" />
                                                </div>
                                                <div class="leading-tight">
                                                    <div class="flex items-center gap-1.5">
                                                        <p class="font-mono text-sm font-bold text-slate-800 dark:text-slate-100">
                                                            {{ group.guide_number ?? group.items[0].dispatch_number }}
                                                        </p>
                                                        <span
                                                            class="rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase"
                                                            :class="
                                                                group.is_outgoing ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700'
                                                            "
                                                        >
                                                            {{ group.is_outgoing ? 'Saliente' : 'Entrante' }}
                                                        </span>
                                                    </div>
                                                    <p class="text-[11px] text-slate-400">
                                                        <template v-if="group.is_outgoing">Hacia {{ group.destination_name }}</template>
                                                        <template v-else>Desde {{ group.origin_name }}</template>
                                                        · {{ group.dispatched_at }} · {{ group.dispatched_by }}
                                                    </p>
                                                </div>
                                            </div>
                                            <span
                                                :class="[
                                                    'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold',
                                                    group.items.every((i) => i.received_at)
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : 'bg-indigo-100 text-indigo-700',
                                                ]"
                                            >
                                                <CheckCircle2 v-if="group.items.every((i) => i.received_at)" class="h-3 w-3" />
                                                <Clock v-else class="h-3 w-3" />
                                                {{ group.items.filter((i) => i.received_at).length }}/{{ group.items.length }}
                                                recepcionados
                                            </span>
                                        </div>

                                        <!-- Equipos de la guía -->
                                        <div class="divide-y">
                                            <div
                                                v-for="d in group.items"
                                                :key="d.id"
                                                class="flex flex-col gap-3 px-5 py-3 transition-colors sm:flex-row sm:items-center"
                                                :class="d.received_at ? 'bg-emerald-50/30 dark:bg-emerald-900/10' : 'hover:bg-muted/20'"
                                            >
                                                <!-- Equipo -->
                                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                                    <div
                                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                                        :class="{
                                                            'bg-blue-100': d.equipable_type === 'computer',
                                                            'bg-orange-100': d.equipable_type === 'kitchen',
                                                            'bg-indigo-100': d.equipable_type === 'epp',
                                                        }"
                                                    >
                                                        <Laptop v-if="d.equipable_type === 'computer'" class="h-4 w-4 text-blue-600" />
                                                        <ShieldCheck v-else-if="d.equipable_type === 'epp'" class="h-4 w-4 text-indigo-600" />
                                                        <UtensilsCrossed v-else class="h-4 w-4 text-orange-600" />
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm leading-tight font-semibold text-slate-800 dark:text-slate-100">
                                                            {{ d.equipment_name }}
                                                        </p>
                                                        <p class="truncate text-[11px] text-slate-400">
                                                            <span class="font-mono">{{ d.dispatch_number }}</span>
                                                            <span v-if="d.equipable_type === 'epp' && (d.size || d.color_name)">
                                                                · {{ [d.size ? `Talla ${d.size}` : null, d.color_name].filter(Boolean).join(' · ') }}
                                                            </span>
                                                            <span v-else-if="d.equipment_brand || d.equipment_model">
                                                                · {{ [d.equipment_brand, d.equipment_model].filter(Boolean).join(' ') }}
                                                            </span>
                                                        </p>
                                                    </div>
                                                </div>

                                                <!-- Cantidad -->
                                                <div class="flex items-center gap-1.5 sm:w-16 sm:justify-center">
                                                    <span class="text-[10px] font-semibold text-slate-400 uppercase sm:hidden">Cant.</span>
                                                    <span
                                                        class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-600"
                                                    >
                                                        ×{{ d.quantity }}
                                                    </span>
                                                </div>

                                                <!-- Estado -->
                                                <div class="sm:w-36">
                                                    <span
                                                        v-if="d.received_at"
                                                        class="inline-flex items-center gap-1 rounded-full border border-emerald-300 bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700"
                                                    >
                                                        <CheckCircle2 class="h-3 w-3" /> Recepcionado
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="inline-flex items-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-600"
                                                    >
                                                        <Clock class="h-3 w-3" /> En tránsito
                                                    </span>
                                                </div>

                                                <!-- Acción -->
                                                <div class="sm:w-56 sm:shrink-0 sm:text-right">
                                                    <template v-if="d.received_at">
                                                        <p class="text-[11px] font-medium text-slate-500">
                                                            {{ group.is_outgoing ? 'Recibido por' : '' }} {{ d.received_by ?? '—' }}
                                                        </p>
                                                        <p
                                                            v-if="d.reception_notes"
                                                            class="mt-1 rounded bg-slate-100 px-2 py-1 text-left text-[10px] text-slate-500 italic"
                                                        >
                                                            {{ d.reception_notes }}
                                                        </p>
                                                    </template>

                                                    <!-- Guía saliente: se recepciona del otro lado, no desde acá -->
                                                    <template v-else-if="group.is_outgoing">
                                                        <span class="text-[11px] text-slate-400 italic">Pendiente en destino</span>
                                                    </template>

                                                    <template v-else-if="confirmId === d.id">
                                                        <div class="space-y-1.5 text-left">
                                                            <textarea
                                                                v-model="receptionNote"
                                                                placeholder="Observación (opcional)…"
                                                                rows="2"
                                                                class="w-full resize-none rounded-lg border border-slate-200 px-2 py-1.5 text-[11px] outline-none focus:border-emerald-400 focus:ring-1 focus:ring-emerald-200"
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

                                                    <button
                                                        v-else
                                                        class="rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-[11px] font-bold text-emerald-700 shadow-sm hover:bg-emerald-50"
                                                        @click="startConfirm(d.id)"
                                                    >
                                                        Recepcionar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Paginación: 10 guías por página -->
                                <div
                                    v-if="dispatches.total > dispatches.per_page"
                                    class="mt-3 flex items-center justify-between rounded-xl border bg-white px-4 py-3 shadow-sm dark:bg-gray-800"
                                >
                                    <div class="text-xs text-slate-400">
                                        Mostrando <span class="font-medium">{{ dispatches.from }}</span> a
                                        <span class="font-medium">{{ dispatches.to }}</span> de
                                        <span class="font-medium">{{ dispatches.total }}</span> guías
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <template v-for="(link, k) in dispatches.links" :key="k">
                                            <div
                                                v-if="link.url === null"
                                                class="flex h-7 min-w-[28px] items-center justify-center rounded-md px-2 text-xs text-slate-300"
                                                v-html="link.label"
                                            />
                                            <Link
                                                v-else
                                                :href="link.url"
                                                class="flex h-7 min-w-[28px] items-center justify-center rounded-md border px-2 text-xs transition-all"
                                                :class="
                                                    link.active
                                                        ? 'border-indigo-600 bg-indigo-600 font-bold text-white shadow-sm'
                                                        : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'
                                                "
                                                v-html="link.label"
                                                preserve-scroll
                                                preserve-state
                                            />
                                        </template>
                                    </div>
                                </div>
                        </template>

                        <!-- ═══ Stock: una fila por equipo, sin repetirse aunque el historial tenga varias guías ═══ -->
                        <template v-else>
                            <template v-if="activeTab !== 'supplies'">
                                <div
                                    v-if="stock.data.length === 0"
                                    class="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-slate-400"
                                >
                                    <PackageCheck class="mb-3 h-10 w-10 text-slate-300" />
                                    <p class="font-medium">Sin equipos disponibles aquí por ahora</p>
                                </div>

                                <div v-else class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-gray-800">
                                    <table class="w-full text-sm">
                                        <thead class="border-b bg-slate-50 dark:bg-gray-700/50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">Código</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">Equipo</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">Marca / Modelo</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">N° Serie</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">Tipo</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">Estado</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">Responsable</th>
                                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500">Cant.</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            <tr v-for="item in stock.data" :key="item.id" class="hover:bg-slate-50 dark:hover:bg-gray-700/30">
                                                <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ item.equipment_code || '—' }}</td>
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-2">
                                                        <div
                                                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                                                            :class="{
                                                                'bg-blue-100': item.equipable_type === 'computer',
                                                                'bg-orange-100': item.equipable_type === 'kitchen',
                                                                'bg-indigo-100': item.equipable_type === 'epp',
                                                            }"
                                                        >
                                                            <Laptop v-if="item.equipable_type === 'computer'" class="h-3.5 w-3.5 text-blue-600" />
                                                            <ShieldCheck v-else-if="item.equipable_type === 'epp'" class="h-3.5 w-3.5 text-indigo-600" />
                                                            <UtensilsCrossed v-else class="h-3.5 w-3.5 text-orange-600" />
                                                        </div>
                                                        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ item.equipment_name }}</p>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                                    <template v-if="item.equipable_type === 'epp'">
                                                        {{ [item.size ? `Talla ${item.size}` : null, item.color_name].filter(Boolean).join(' · ') || '—' }}
                                                    </template>
                                                    <template v-else>
                                                        {{ [item.equipment_brand, item.equipment_model].filter(Boolean).join(' · ') || '—' }}
                                                    </template>
                                                </td>
                                                <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ item.equipment_series || '—' }}</td>
                                                <td class="px-4 py-3 text-xs text-slate-500">
                                                    {{ item.equipable_type === 'computer' ? 'Tecnológico' : item.equipable_type === 'epp' ? 'EPP' : 'Menaje' }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span
                                                        v-if="item.equipment_status !== null"
                                                        :class="[
                                                            'inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                                                            equipmentStatusInfo(item.equipment_status).cls,
                                                        ]"
                                                    >
                                                        {{ equipmentStatusInfo(item.equipment_status).label }}
                                                    </span>
                                                    <span v-else class="text-xs text-slate-300">—</span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <!-- Asignación de responsable solo existe para equipos (computer/kitchen);
                                                    EPP no pasa por ese flujo todavía. -->
                                                    <span v-if="item.equipable_type === 'epp'" class="text-xs text-slate-300">—</span>
                                                    <div v-else class="flex items-center gap-2">
                                                        <select
                                                            :value="item.responsible_id ?? ''"
                                                            :disabled="assigningStock === item.id"
                                                            @change="onStaffSelectChange(item, ($event.target as HTMLSelectElement).value)"
                                                            class="w-full max-w-[150px] rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs outline-none focus:border-indigo-400 disabled:opacity-50 dark:bg-gray-700 dark:text-white"
                                                        >
                                                            <option value="">{{ item.responsible_name ?? 'Sin asignar' }}</option>
                                                            <option v-for="s in staffOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
                                                        </select>

                                                        <template v-if="item.responsible_id">
                                                            <!-- Con cargo subido: icono de documento de color verde con un check -->
                                                            <a
                                                                v-if="item.cargo_path"
                                                                :href="`/storage/${item.cargo_path}`"
                                                                target="_blank"
                                                                title="Cargo registrado con documento (Clic para ver/descargar)"
                                                                class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 hover:bg-emerald-200 transition-colors dark:bg-emerald-950 dark:text-emerald-400 dark:hover:bg-emerald-900"
                                                            >
                                                                <FileCheck class="h-4 w-4" />
                                                            </a>
                                                            <!-- Sin cargo subido: icono de documento de color rojo -->
                                                            <button
                                                                v-else
                                                                type="button"
                                                                @click="openAssignModal(item, String(item.responsible_id))"
                                                                title="Asignación sin cargo registrado (Clic para subir el cargo)"
                                                                class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-700 hover:bg-rose-200 transition-colors dark:bg-rose-950 dark:text-rose-400 dark:hover:bg-rose-900"
                                                            >
                                                                <FileX class="h-4 w-4" />
                                                            </button>
                                                        </template>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <span
                                                        class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 font-mono text-xs font-bold text-amber-700"
                                                    >
                                                        {{ item.quantity }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <!-- Paginación: 15 por página -->
                                    <div
                                        v-if="stock.total > stock.per_page"
                                        class="bg-muted/20 flex items-center justify-between border-t px-4 py-3 dark:bg-gray-700/20"
                                    >
                                        <div class="text-xs text-slate-400">
                                            Mostrando <span class="font-medium">{{ stock.from }}</span> a
                                            <span class="font-medium">{{ stock.to }}</span> de <span class="font-medium">{{ stock.total }}</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <template v-for="(link, k) in stock.links" :key="k">
                                                <div
                                                    v-if="link.url === null"
                                                    class="flex h-7 min-w-[28px] items-center justify-center rounded-md px-2 text-xs text-slate-300"
                                                    v-html="link.label"
                                                />
                                                <Link
                                                    v-else
                                                    :href="link.url"
                                                    class="flex h-7 min-w-[28px] items-center justify-center rounded-md border px-2 text-xs transition-all"
                                                    :class="
                                                        link.active
                                                            ? 'border-amber-500 bg-amber-500 font-bold text-white shadow-sm'
                                                            : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'
                                                    "
                                                    v-html="link.label"
                                                    preserve-scroll
                                                    preserve-state
                                                />
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div
                                v-else-if="activeTab === 'supplies'"
                                class="flex flex-col items-center justify-center rounded-xl border border-dashed py-20 text-slate-400"
                            >
                                <HardHat class="mb-3 h-10 w-10 text-slate-300" />
                                <p class="font-medium">Insumos — Sin stock registrado</p>
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
                                No hay ítems disponibles para enviar desde este café
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="item in sendForm.items"
                                    :key="`${item.equipable_type}-${item.equipable_id}-${item.size ?? ''}-${item.color_id ?? ''}`"
                                    class="flex items-center gap-3 rounded-xl border bg-slate-50 px-4 py-3 dark:bg-gray-700/40"
                                >
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                        :class="{
                                            'bg-blue-100': item.equipable_type === 'computer',
                                            'bg-orange-100': item.equipable_type === 'kitchen',
                                            'bg-indigo-100': item.equipable_type === 'epp',
                                        }"
                                    >
                                        <Laptop v-if="item.equipable_type === 'computer'" class="h-4 w-4 text-blue-600" />
                                        <ShieldCheck v-else-if="item.equipable_type === 'epp'" class="h-4 w-4 text-indigo-600" />
                                        <UtensilsCrossed v-else class="h-4 w-4 text-orange-600" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ item.equipment_name }}</p>
                                        <p v-if="item.equipable_type === 'epp' && (item.size || item.color_name)" class="text-[11px] text-slate-400">
                                            {{ [item.size ? `Talla ${item.size}` : null, item.color_name].filter(Boolean).join(' · ') }} · Disponible:
                                            {{ item.max }}
                                        </p>
                                        <p v-else class="text-[11px] text-slate-400">Disponible: {{ item.max }}</p>
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

        <!-- ── Modal: Registrar Cargo y Asignación de Responsable ── -->
        <Teleport to="body">
            <div
                v-if="assignModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            >
                <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl transition-all dark:bg-gray-800">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b px-6 py-4 dark:border-gray-700">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400">
                                <FileCheck class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-800 dark:text-white">Asignar Responsable</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Registrar entrega y cargo de equipo</p>
                            </div>
                        </div>
                        <button
                            @click="closeAssignModal"
                            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-gray-700 dark:hover:text-slate-200"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="space-y-4 px-6 py-5">
                        <!-- Summary info box -->
                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/80 p-3.5 dark:border-gray-700 dark:bg-gray-900/40">
                            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Equipo a asignar</p>
                            <p class="text-sm font-bold text-slate-800 dark:text-white">
                                {{ targetAssignItem?.equipment_name }}
                            </p>
                            <div class="mt-1 flex flex-wrap gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                                <span v-if="targetAssignItem?.equipment_brand">Marca: {{ targetAssignItem.equipment_brand }}</span>
                                <span v-if="targetAssignItem?.equipment_model">Modelo: {{ targetAssignItem.equipment_model }}</span>
                                <span v-if="targetAssignItem?.equipment_series">S/N: {{ targetAssignItem.equipment_series }}</span>
                            </div>
                            <div class="mt-2.5 border-t border-slate-200/60 pt-2 text-xs dark:border-gray-700">
                                <span class="text-slate-500">Nuevo Responsable: </span>
                                <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ targetStaffName }}</span>
                            </div>
                        </div>

                        <!-- Upload zone -->
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Archivo de Cargo / Acta de Entrega <span class="font-normal text-slate-400">(Opcional)</span>
                            </label>

                            <div class="relative">
                                <input
                                    type="file"
                                    id="cargo-file-input"
                                    @change="handleCargoFileUpload"
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                                    class="hidden"
                                />

                                <div v-if="!cargoFile">
                                    <label
                                        for="cargo-file-input"
                                        class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50/50 p-4 transition-colors hover:border-indigo-400 hover:bg-indigo-50/30 dark:border-gray-600 dark:bg-gray-900/20 dark:hover:border-indigo-500"
                                    >
                                        <Upload class="mb-1 h-6 w-6 text-indigo-500" />
                                        <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                                            Haz clic para adjuntar archivo de cargo
                                        </p>
                                        <p class="text-[10px] text-slate-400">PDF, Imágenes, Word o Excel (máx. 10MB)</p>
                                    </label>
                                </div>

                                <div
                                    v-else
                                    class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/80 px-3.5 py-2.5 dark:border-emerald-800 dark:bg-emerald-950/40"
                                >
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <Paperclip class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-xs font-bold text-emerald-900 dark:text-emerald-200">
                                                {{ cargoFile.name }}
                                            </p>
                                            <p class="text-[10px] text-emerald-700 dark:text-emerald-400">
                                                {{ (cargoFile.size / 1024).toFixed(1) }} KB
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        @click="cargoFile = null"
                                        class="ml-2 rounded-lg p-1 text-emerald-700 hover:bg-emerald-100 dark:text-emerald-300 dark:hover:bg-emerald-900"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Observaciones / Notas -->
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Observaciones / Notas <span class="font-normal text-slate-400">(Opcional)</span>
                            </label>
                            <textarea
                                v-model="assignNotes"
                                rows="2"
                                placeholder="Notas adicionales sobre la entrega…"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs outline-none focus:border-indigo-400 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Footer / Buttons -->
                    <div class="flex items-center justify-end gap-2 border-t px-6 py-4 dark:border-gray-700">
                        <button
                            type="button"
                            @click="closeAssignModal"
                            :disabled="assignProcessing"
                            class="rounded-xl border px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-50 dark:border-gray-700 dark:text-slate-300 dark:hover:bg-gray-700"
                        >
                            Cancelar
                        </button>

                        <button
                            type="button"
                            @click="submitAssignment(false)"
                            :disabled="assignProcessing"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100 disabled:opacity-50 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300 dark:hover:bg-rose-900"
                            title="Registrar la asignación sin adjuntar documento de cargo"
                        >
                            <FileX class="h-3.5 w-3.5" />
                            Registrar sin cargo
                        </button>

                        <button
                            type="button"
                            @click="submitAssignment(true)"
                            :disabled="assignProcessing || !cargoFile"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                            title="Registrar la asignación adjuntando el documento de cargo"
                        >
                            <FileCheck class="h-3.5 w-3.5" />
                            {{ assignProcessing ? 'Guardando…' : 'Registrar con cargo' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
