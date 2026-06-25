<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    Bell,
    CheckCircle2,
    FileDown,
    FileText,
    Laptop,
    Layers,
    MapPin,
    Package,
    PackageCheck,
    Plus,
    RotateCcw,
    Search,
    Truck,
    UtensilsCrossed,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { AdvancedMarker, GoogleMap, Polyline } from 'vue3-google-map';

// ── Map ───────────────────────────────────────────────────────────────────
const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
const DEFAULT_CENTER = { lat: -12.05166, lng: -75.21871 };
const mapOptions = {
    disableDefaultUI: true,
    mapId: import.meta.env.VITE_GOOGLE_MAPS_MAP_ID ?? 'DEMO_MAP_ID',
};

// Estado del mapa para feedback al usuario
const mapState = computed(() => {
    const d = selectedDispatch.value;
    if (!d) return 'idle'; // Ningún despacho seleccionado
    if (!d.origin_lat || !d.origin_lng) return 'no-origin-coords'; // HQ sin coords
    if (!d.destination_lat || !d.destination_lng) return 'no-dest-coords'; // Destino sin coords
    return 'ready';
});

const mapRouteData = computed(() => {
    if (mapState.value !== 'ready' && mapState.value !== 'no-dest-coords') return null;
    const d = selectedDispatch.value!;
    const origin = { lat: d.origin_lat!, lng: d.origin_lng! };
    const dest = d.destination_lat && d.destination_lng ? { lat: d.destination_lat, lng: d.destination_lng } : null;
    const center = dest ? { lat: (origin.lat + dest.lat) / 2, lng: (origin.lng + dest.lng) / 2 } : origin;
    return { origin, dest, center };
});

const mapCenter = computed(() => mapRouteData.value?.center ?? DEFAULT_CENTER);

const routePath = computed(() => {
    const r = mapRouteData.value;
    if (!r?.dest) return [];
    return [r.origin, r.dest];
});

const polylineOptions = {
    strokeColor: '#2563EB',
    strokeOpacity: 0.85,
    strokeWeight: 3,
    geodesic: true,
};

// ── Types ─────────────────────────────────────────────────────────────────
interface StaffRef {
    id: number;
    name: string;
}
interface HQRef {
    id: number;
    name: string;
    business: { id: number; name: string } | null;
}
interface EquipmentBase {
    id: number;
    name: string;
    brand: string | null;
    model: string | null;
    code: string | null;
    series: string | null;
    status: number;
    quantity: number;
    storage_headquarter_id: number | null;
    storage_headquarter: HQRef | null;
    responsible: StaffRef | null;
}
interface Dispatch {
    id: number;
    dispatch_number: string;
    guide_number: string | null;
    status: 'active' | 'returned';
    equipable_type: 'computer' | 'kitchen';
    equipable_id: number;
    quantity: number;
    equipment_name: string;
    equipment_brand: string | null;
    equipment_model: string | null;
    equipment_code: string | null;
    equipment_series: string | null;
    equipment_status: number;
    origin_id: number | null;
    origin_name: string;
    origin_label?: string;
    origin_business?: string | null;
    destination_type: string;
    destination_label: string;
    destination_name: string;
    destination_business?: string | null;
    destination_id: number;
    staff_id: number | null;
    staff_name: string | null;
    description: string | null;
    dispatched_by: string;
    dispatched_at: string;
    dispatched_at_raw: string;
    returned_at: string | null;
    received_at: string | null;
    received_by: string | null;
    origin_lat: number | null;
    origin_lng: number | null;
    destination_lat: number | null;
    destination_lng: number | null;
}
interface CafeRef {
    id: number;
    name: string;
}
interface UnitRef {
    id: number;
    name: string;
    cafes: CafeRef[];
}
interface MineRef {
    id: number;
    name: string;
    units: UnitRef[];
}
interface EquipRow {
    id: number;
    equipable_type: 'computer' | 'kitchen';
    name: string;
    brand: string | null;
    model: string | null;
    code: string | null;
    quantity: number;
    storage_headquarter: HQRef | null;
    qty_to_dispatch: number;
}

// ── Props ─────────────────────────────────────────────────────────────────
const props = defineProps<{
    headquarters: HQRef[];
    dispatches: Dispatch[];
    computerEquipments: EquipmentBase[];
    kitchenEquipments: EquipmentBase[];
    cafes: Array<{ id: number; name: string; unit: { id: number; name: string; mine: { id: number; name: string } } }>;
    units: Array<{ id: number; name: string; mine: { id: number; name: string } }>;
    mines: MineRef[];
    staff: StaffRef[];
}>();

// ── Dashboard stats ───────────────────────────────────────────────────────
const TODAY = new Date().toDateString();
const YESTERDAY = (() => {
    const d = new Date();
    d.setDate(d.getDate() - 1);
    return d.toDateString();
})();

const dashStats = computed(() => {
    const todayDisp = props.dispatches.filter((d) => d.dispatched_at_raw && new Date(d.dispatched_at_raw).toDateString() === TODAY);
    const yestDisp = props.dispatches.filter((d) => d.dispatched_at_raw && new Date(d.dispatched_at_raw).toDateString() === YESTERDAY);
    const entregados = props.dispatches.filter((d) => d.received_at).length;
    const enTransito = props.dispatches.filter((d) => d.status === 'active' && !d.received_at).length;
    const retrasados = props.dispatches.filter((d) => {
        if (d.status !== 'active' || d.received_at) return false;
        if (!d.dispatched_at_raw) return false;
        return (Date.now() - new Date(d.dispatched_at_raw).getTime()) / 86400000 > 5;
    }).length;
    const totalUnits = props.dispatches.reduce((s, d) => s + d.quantity, 0);

    const todayN = todayDisp.length;
    const yestN = yestDisp.length;
    const pctHoy = yestN === 0 ? null : Math.round(((todayN - yestN) / yestN) * 100);

    return { todayN, yestN, pctHoy, entregados, enTransito, retrasados, total: props.dispatches.length, totalUnits };
});

// ── Donut chart data ──────────────────────────────────────────────────────
const donutData = computed(() => {
    const t = dashStats.value.total || 1;
    const p1 = Math.round((dashStats.value.entregados / t) * 100);
    const p2 = Math.round((dashStats.value.enTransito / t) * 100);
    const p3 = Math.round((dashStats.value.retrasados / t) * 100);
    const p4 = Math.max(0, 100 - p1 - p2 - p3);
    return { p1, p2, p3, p4, o1: 25, o2: 25 - p1, o3: 25 - p1 - p2, o4: 25 - p1 - p2 - p3 };
});

// ── Selected guide panel ──────────────────────────────────────────────────
const selectedDispatch = ref<Dispatch | null>(null);

function selectDispatch(d: Dispatch) {
    selectedDispatch.value = selectedDispatch.value?.id === d.id ? null : d;
}

function getStatus(d: Dispatch) {
    if (d.received_at) return { label: 'Entregado', cls: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-500' };
    if (d.status === 'returned') return { label: 'Retornado', cls: 'bg-slate-100 text-slate-500', dot: 'bg-slate-400' };
    if (d.dispatched_at_raw && (Date.now() - new Date(d.dispatched_at_raw).getTime()) / 86400000 > 5)
        return { label: 'Retrasado', cls: 'bg-red-100 text-red-700', dot: 'bg-red-500' };
    return { label: 'En Tránsito', cls: 'bg-blue-100 text-blue-700', dot: 'bg-blue-500' };
}

const selectedTimeline = computed(() => {
    const d = selectedDispatch.value;
    if (!d) return [];
    const base = d.dispatched_at_raw ? new Date(d.dispatched_at_raw) : null;
    const fmt = (dt: Date) =>
        dt.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' }) +
        ' ' +
        dt.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
    return [
        { label: 'Guía generada', time: base ? fmt(base) : '', done: true, current: false },
        { label: 'Salida de almacén', time: base ? fmt(new Date(base.getTime() + 70 * 60000)) : '', done: true, current: false },
        {
            label: 'En tránsito',
            time: base ? fmt(new Date(base.getTime() + 125 * 60000)) : '',
            done: d.status === 'active' || !!d.received_at,
            current: d.status === 'active' && !d.received_at,
        },
        {
            label: d.received_at ? 'Recepcionado' : 'Entrega estimada',
            time: d.received_at ?? (base ? fmt(new Date(base.getTime() + 86400000)) : ''),
            done: !!d.received_at,
            current: false,
        },
    ];
});

// ── Recent dispatches table ───────────────────────────────────────────────
const tableSearch = ref('');

interface DispatchGroup {
    key: string;
    guide_number: string | null;
    items: Dispatch[];
    destination_name: string;
    destination_label: string;
    destination_business?: string | null;
    origin_name: string;
    origin_label?: string;
    origin_business?: string | null;
    dispatched_at: string;
    dispatched_at_raw: string;
    staff_name: string | null;
    dispatched_by: string;
}

const groupedDispatches = computed((): DispatchGroup[] => {
    const q = tableSearch.value.toLowerCase();
    const sorted = [...props.dispatches]
        .sort((a, b) => new Date(b.dispatched_at_raw ?? 0).getTime() - new Date(a.dispatched_at_raw ?? 0).getTime())
        .filter(
            (d) =>
                !q ||
                [d.dispatch_number, d.guide_number, d.equipment_name, d.origin_name, d.destination_name, d.staff_name].some((v) =>
                    v?.toLowerCase().includes(q),
                ),
        );

    const map = new Map<string, DispatchGroup>();
    for (const d of sorted) {
        const key = d.guide_number ?? `solo-${d.id}`;
        if (map.has(key)) {
            map.get(key)!.items.push(d);
        } else {
            map.set(key, {
                key,
                guide_number: d.guide_number,
                items: [d],
                destination_name: d.destination_name,
                destination_label: d.destination_label,
                destination_business: d.destination_business,
                origin_name: d.origin_name,
                origin_label: d.origin_label,
                origin_business: d.origin_business,
                dispatched_at: d.dispatched_at,
                dispatched_at_raw: d.dispatched_at_raw,
                staff_name: d.staff_name,
                dispatched_by: d.dispatched_by,
            });
        }
    }
    return [...map.values()].slice(0, 15);
});

function groupStatus(g: DispatchGroup) {
    const allReceived = g.items.every((d) => d.received_at);
    if (allReceived) return { label: 'Entregado', cls: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-500' };
    if (g.items.every((d) => d.status === 'returned')) return { label: 'Retornado', cls: 'bg-slate-100 text-slate-500', dot: 'bg-slate-400' };
    const anyDelayed = g.items.some(
        (d) =>
            d.status === 'active' && !d.received_at && d.dispatched_at_raw && (Date.now() - new Date(d.dispatched_at_raw).getTime()) / 86400000 > 5,
    );
    if (anyDelayed) return { label: 'Retrasado', cls: 'bg-red-100 text-red-700', dot: 'bg-red-500' };
    return { label: 'En Tránsito', cls: 'bg-blue-100 text-blue-700', dot: 'bg-blue-500' };
}

function downloadGroup(g: DispatchGroup) {
    if (g.guide_number) {
        window.open(route('equipment-dispatches.guide-pdf', g.guide_number), '_blank');
    } else {
        window.open(route('equipment-dispatches.pdf', g.items[0].id), '_blank');
    }
}

function downloadGuide(d: Dispatch) {
    if (d.guide_number) {
        window.open(route('equipment-dispatches.guide-pdf', d.guide_number), '_blank');
    } else {
        window.open(route('equipment-dispatches.pdf', d.id), '_blank');
    }
}

// ── Dispatch form ─────────────────────────────────────────────────────────
const showForm = ref(false);
const confirmReturn = ref<Dispatch | null>(null);
const equipmentTable = ref<EquipRow[]>([]);
const itemSearch = ref('');
const itemTypeFilter = ref<'all' | 'computer' | 'kitchen'>('all');

const form = useForm({
    origin_headquarter_id: '' as string | number,
    destination_type: 'cafe' as string,
    destination_id: '' as string | number,
    staff_id: 'none' as string | number,
    description: '',
});

const filteredEquipmentTable = computed(() => {
    const q = itemSearch.value.toLowerCase().trim();
    return equipmentTable.value.filter((eq) => {
        if (itemTypeFilter.value !== 'all' && eq.equipable_type !== itemTypeFilter.value) return false;
        if (!q) return true;
        return eq.name.toLowerCase().includes(q) || (eq.storage_headquarter?.name.toLowerCase().includes(q) ?? false);
    });
});

const dispatchItems = computed(() =>
    equipmentTable.value
        .filter((eq) => eq.qty_to_dispatch > 0)
        .map((eq) => ({ equipable_type: eq.equipable_type, equipable_id: eq.id, quantity: eq.qty_to_dispatch })),
);

const formValid = computed(() => {
    if (!form.origin_headquarter_id || !form.destination_id || dispatchItems.value.length === 0) return false;
    return equipmentTable.value.every((eq) => eq.qty_to_dispatch <= 0 || eq.qty_to_dispatch <= eq.quantity);
});

const destinationOptions = computed(() => {
    switch (form.destination_type) {
        case 'cafe':
            return props.cafes.map((c) => ({ id: c.id, label: `${c.name} — ${c.unit?.mine?.name ?? ''}` }));
        case 'unit':
            return props.units.map((u) => ({ id: u.id, label: `${u.name} — ${u.mine?.name ?? ''}` }));
        case 'mine':
            return props.mines.map((m) => ({ id: m.id, label: m.name }));
        case 'headquarter':
            return props.headquarters.map((h) => ({ id: h.id, label: h.name }));
        default:
            return [];
    }
});

function openCreate() {
    form.reset();
    form.destination_type = 'cafe';
    itemSearch.value = '';
    itemTypeFilter.value = 'all';
    equipmentTable.value = [
        ...props.computerEquipments
            .filter((e) => e.quantity > 0)
            .map((e) => ({
                id: e.id,
                equipable_type: 'computer' as const,
                name: e.name,
                brand: e.brand,
                model: e.model,
                code: e.code,
                quantity: e.quantity,
                storage_headquarter: e.storage_headquarter,
                qty_to_dispatch: 0,
            })),
        ...props.kitchenEquipments
            .filter((e) => e.quantity > 0)
            .map((e) => ({
                id: e.id,
                equipable_type: 'kitchen' as const,
                name: e.name,
                brand: e.brand,
                model: e.model,
                code: e.code,
                quantity: e.quantity,
                storage_headquarter: e.storage_headquarter,
                qty_to_dispatch: 0,
            })),
    ];
    showForm.value = true;
}

function submitForm() {
    form.transform((data) => ({
        items: dispatchItems.value,
        ...data,
        staff_id: data.staff_id === 'none' ? null : data.staff_id,
    })).post(route('equipment-dispatches.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            equipmentTable.value = [];
            form.reset();
        },
    });
}

function doReturn() {
    if (!confirmReturn.value) return;
    router.put(
        route('equipment-dispatches.return', confirmReturn.value.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                confirmReturn.value = null;
            },
        },
    );
}

// ── Reception ─────────────────────────────────────────────────────────────
const confirmId = ref<number | null>(null);
const processing = ref(false);

const activeDispatches = computed(() => props.dispatches.filter((d) => d.status === 'active'));

function doReceive(id: number) {
    processing.value = true;
    router.put(
        route('equipment-dispatches.receive', id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                confirmId.value = null;
            },
        },
    );
}

// ── Helpers ───────────────────────────────────────────────────────────────
const currentDate = new Date().toLocaleDateString('es-PE', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
function pctLabel(v: number | null): string {
    return v === null ? '—' : v >= 0 ? `+${v}%` : `${v}%`;
}
function pctCls(v: number | null): string {
    return v === null ? 'text-slate-400' : v >= 0 ? 'text-emerald-500' : 'text-red-500';
}
</script>

<template>
    <Head title="Dashboard Logístico" />
    <AppLayout>
        <!-- ═══ MAIN CONTENT ═══ -->
        <div class="flex h-full flex-col overflow-hidden bg-slate-100">
            <!-- Top bar -->
            <div class="flex shrink-0 items-center justify-between border-b bg-white px-6 py-3 shadow-sm">
                <div>
                    <h1 class="text-lg font-bold text-slate-900">Dashboard Logístico</h1>
                    <p class="text-xs text-slate-500">Resumen general de operaciones</p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Date chip -->
                    <div class="flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-600">
                        <span>{{ currentDate }}</span>
                    </div>
                    <!-- Bell -->
                    <button class="relative rounded-full border border-slate-200 p-2 hover:bg-slate-50">
                        <Bell class="h-4 w-4 text-slate-600" />
                        <span
                            v-if="dashStats.retrasados > 0"
                            class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[9px] font-bold text-white"
                        >
                            {{ dashStats.retrasados }}
                        </span>
                    </button>
                    <!-- New dispatch -->
                    <Button size="sm" class="gap-2 bg-red-600 text-white hover:bg-red-700" @click="openCreate">
                        <Plus class="h-3.5 w-3.5" />
                        Nueva Guía
                    </Button>
                </div>
            </div>

            <!-- Scrollable body -->
            <div class="flex-1 space-y-5 overflow-y-auto p-5">
                <!-- ── STATS ROW ── -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                    <!-- Despachos hoy -->
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <div class="flex items-center gap-2">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100">
                                <Truck class="h-4 w-4 text-blue-600" />
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-[11px] text-slate-500">Despachos Hoy</p>
                                <p class="text-2xl font-bold text-slate-900">{{ dashStats.todayN }}</p>
                            </div>
                        </div>
                        <p class="mt-1.5 text-[11px]" :class="pctCls(dashStats.pctHoy)">{{ pctLabel(dashStats.pctHoy) }} vs ayer</p>
                    </div>
                    <!-- Entregados -->
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <div class="flex items-center gap-2">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-100">
                                <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-[11px] text-slate-500">Entregados</p>
                                <p class="text-2xl font-bold text-slate-900">{{ dashStats.entregados }}</p>
                            </div>
                        </div>
                        <p class="mt-1.5 text-[11px] text-slate-400">del total de {{ dashStats.total }}</p>
                    </div>
                    <!-- En Tránsito -->
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <div class="flex items-center gap-2">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-orange-100">
                                <Package class="h-4 w-4 text-orange-600" />
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-[11px] text-slate-500">En Tránsito</p>
                                <p class="text-2xl font-bold text-slate-900">{{ dashStats.enTransito }}</p>
                            </div>
                        </div>
                        <p class="mt-1.5 text-[11px] text-slate-400">despachos activos</p>
                    </div>
                    <!-- Retrasados -->
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <div class="flex items-center gap-2">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-red-100">
                                <AlertTriangle class="h-4 w-4 text-red-600" />
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-[11px] text-slate-500">Retrasados</p>
                                <p class="text-2xl font-bold text-slate-900">{{ dashStats.retrasados }}</p>
                            </div>
                        </div>
                        <p class="mt-1.5 text-[11px]" :class="dashStats.retrasados > 0 ? 'text-red-500' : 'text-slate-400'">
                            {{ dashStats.retrasados > 0 ? 'requieren atención' : 'sin retrasos' }}
                        </p>
                    </div>
                    <!-- Guías emitidas -->
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <div class="flex items-center gap-2">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-purple-100">
                                <FileText class="h-4 w-4 text-purple-600" />
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-[11px] text-slate-500">Guías Emitidas</p>
                                <p class="text-2xl font-bold text-slate-900">{{ dashStats.total }}</p>
                            </div>
                        </div>
                        <p class="mt-1.5 text-[11px] text-slate-400">total acumulado</p>
                    </div>
                    <!-- Unidades despachadas -->
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <div class="flex items-center gap-2">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-teal-100">
                                <Layers class="h-4 w-4 text-teal-600" />
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-[11px] text-slate-500">Uds. Despachadas</p>
                                <p class="text-2xl font-bold text-slate-900">{{ dashStats.totalUnits }}</p>
                            </div>
                        </div>
                        <p class="mt-1.5 text-[11px] text-slate-400">equipos en campo</p>
                    </div>
                </div>

                <!-- ── MAIN GRID ── -->
                <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                    <!-- LEFT col (xl:col-span-2) -->
                    <div class="space-y-5 xl:col-span-2">
                        <!-- Mapa -->
                        <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b px-4 py-3">
                                <h2 class="text-sm font-semibold text-slate-900">Mapa en tiempo real</h2>
                                <!-- Header dinámico según estado -->
                                <span v-if="mapState === 'ready'" class="text-xs font-medium text-blue-600">
                                    {{ selectedDispatch?.origin_name }} → {{ selectedDispatch?.destination_name }}
                                </span>
                                <span v-else-if="mapState === 'no-origin-coords'" class="flex items-center gap-1 text-xs font-medium text-amber-500">
                                    <AlertTriangle class="h-3.5 w-3.5" />
                                    Sede "{{ selectedDispatch?.origin_name }}" sin coordenadas
                                </span>
                                <span v-else-if="mapState === 'no-dest-coords'" class="flex items-center gap-1 text-xs font-medium text-amber-500">
                                    <AlertTriangle class="h-3.5 w-3.5" />
                                    Destino "{{ selectedDispatch?.destination_name }}" sin coordenadas
                                </span>
                                <span v-else class="text-xs text-slate-400">Selecciona un despacho para ver la ruta</span>
                            </div>
                            <div class="relative h-52">
                                <GoogleMap
                                    v-if="GOOGLE_MAPS_API_KEY"
                                    :api-key="GOOGLE_MAPS_API_KEY"
                                    :map-id="mapOptions.mapId"
                                    :center="mapCenter"
                                    :zoom="mapRouteData?.dest ? 6 : mapRouteData ? 10 : 9"
                                    :options="mapOptions"
                                    class="h-full w-full"
                                >
                                    <!-- Con coordenadas: origen + destino + línea -->
                                    <template v-if="mapRouteData">
                                        <AdvancedMarker
                                            :options="{ position: mapRouteData.origin, title: selectedDispatch?.origin_name ?? 'Origen' }"
                                        />
                                        <AdvancedMarker
                                            v-if="mapRouteData.dest"
                                            :options="{ position: mapRouteData.dest, title: selectedDispatch?.destination_name ?? 'Destino' }"
                                        />
                                        <Polyline v-if="routePath.length === 2" :options="{ ...polylineOptions, path: routePath }" />
                                    </template>
                                    <!-- Sin selección: marcadores de todos los orígenes con coords -->
                                    <template v-else>
                                        <AdvancedMarker
                                            v-for="d in activeDispatches.filter((x) => x.origin_lat && x.origin_lng)"
                                            :key="d.id"
                                            :options="{ position: { lat: d.origin_lat!, lng: d.origin_lng! }, title: d.origin_name }"
                                        />
                                    </template>
                                </GoogleMap>
                                <div v-else class="flex h-full flex-col items-center justify-center gap-2 bg-slate-100">
                                    <MapPin class="h-8 w-8 text-slate-400" />
                                    <p class="text-xs text-slate-500">Mapa no disponible</p>
                                </div>

                                <!-- Overlay cuando falta info de coords -->
                                <div
                                    v-if="GOOGLE_MAPS_API_KEY && (mapState === 'no-origin-coords' || mapState === 'no-dest-coords')"
                                    class="absolute right-2 bottom-2 left-2 rounded-lg border border-amber-200 bg-white/90 px-3 py-2 shadow-sm backdrop-blur-sm"
                                >
                                    <p class="text-[11px] leading-snug font-medium text-amber-700">
                                        <span v-if="mapState === 'no-origin-coords'">
                                            Ve a <strong>Empresas → Sede "{{ selectedDispatch?.origin_name }}"</strong> y agrega las coordenadas para
                                            ver la ruta.
                                        </span>
                                        <span v-else>
                                            Ve a
                                            <strong
                                                >Gestión → {{ selectedDispatch?.destination_label }} "{{
                                                    selectedDispatch?.destination_name
                                                }}"</strong
                                            >
                                            y agrega las coordenadas para ver el destino.
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom: Resumen + Últimos Despachos side by side on wide screens -->
                        <div class="grid grid-cols-1 gap-5 lg:grid-cols-5">
                            <!-- Resumen de Operaciones -->
                            <div class="rounded-xl border bg-white p-4 shadow-sm lg:col-span-2">
                                <div class="mb-3 flex items-center justify-between">
                                    <h2 class="text-sm font-semibold text-slate-900">Resumen de Operaciones</h2>
                                    <button class="text-xs font-medium text-blue-600 hover:underline">Ver reporte</button>
                                </div>

                                <!-- Donut -->
                                <div class="flex items-center gap-4">
                                    <div class="relative h-24 w-24 shrink-0">
                                        <svg viewBox="0 0 36 36" class="h-full w-full">
                                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#f1f5f9" stroke-width="3" />
                                            <circle
                                                v-if="donutData.p1 > 0"
                                                cx="18"
                                                cy="18"
                                                r="15.9"
                                                fill="none"
                                                stroke="#22c55e"
                                                stroke-width="3.5"
                                                :stroke-dasharray="`${donutData.p1} ${100 - donutData.p1}`"
                                                :stroke-dashoffset="donutData.o1"
                                            />
                                            <circle
                                                v-if="donutData.p2 > 0"
                                                cx="18"
                                                cy="18"
                                                r="15.9"
                                                fill="none"
                                                stroke="#3b82f6"
                                                stroke-width="3.5"
                                                :stroke-dasharray="`${donutData.p2} ${100 - donutData.p2}`"
                                                :stroke-dashoffset="donutData.o2"
                                            />
                                            <circle
                                                v-if="donutData.p3 > 0"
                                                cx="18"
                                                cy="18"
                                                r="15.9"
                                                fill="none"
                                                stroke="#ef4444"
                                                stroke-width="3.5"
                                                :stroke-dasharray="`${donutData.p3} ${100 - donutData.p3}`"
                                                :stroke-dashoffset="donutData.o3"
                                            />
                                            <circle
                                                v-if="donutData.p4 > 0"
                                                cx="18"
                                                cy="18"
                                                r="15.9"
                                                fill="none"
                                                stroke="#f59e0b"
                                                stroke-width="3.5"
                                                :stroke-dasharray="`${donutData.p4} ${100 - donutData.p4}`"
                                                :stroke-dashoffset="donutData.o4"
                                            />
                                        </svg>
                                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                                            <span class="text-lg font-bold text-slate-900">{{ dashStats.total }}</span>
                                            <span class="text-[10px] text-slate-500">Total</span>
                                        </div>
                                    </div>

                                    <!-- Legend -->
                                    <div class="flex-1 space-y-2">
                                        <div
                                            v-for="(seg, i) in [
                                                { label: 'Entregados', n: dashStats.entregados, p: donutData.p1, color: 'bg-emerald-500' },
                                                { label: 'En Tránsito', n: dashStats.enTransito, p: donutData.p2, color: 'bg-blue-500' },
                                                { label: 'Retrasados', n: dashStats.retrasados, p: donutData.p3, color: 'bg-red-500' },
                                                {
                                                    label: 'Pendientes',
                                                    n: dashStats.total - dashStats.entregados - dashStats.enTransito - dashStats.retrasados,
                                                    p: donutData.p4,
                                                    color: 'bg-amber-400',
                                                },
                                            ]"
                                            :key="i"
                                            class="space-y-0.5"
                                        >
                                            <div class="flex items-center justify-between text-[11px]">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="h-2 w-2 rounded-full" :class="seg.color"></span>
                                                    <span class="text-slate-600">{{ seg.label }}</span>
                                                </div>
                                                <span class="font-semibold text-slate-800">{{ seg.n }} ({{ seg.p }}%)</span>
                                            </div>
                                            <div class="h-1 w-full overflow-hidden rounded-full bg-slate-100">
                                                <div
                                                    class="h-full rounded-full transition-all"
                                                    :class="seg.color"
                                                    :style="{ width: seg.p + '%' }"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Estado de despachos -->
                                <div class="mt-4 border-t pt-3">
                                    <p class="mb-2 text-[11px] font-semibold tracking-wide text-slate-400 uppercase">Estados de Despachos</p>
                                    <div class="space-y-1">
                                        <div
                                            v-for="(row, i) in [
                                                { label: 'Entregados', n: dashStats.entregados, p: donutData.p1, color: 'bg-emerald-500' },
                                                { label: 'En Tránsito', n: dashStats.enTransito, p: donutData.p2, color: 'bg-blue-500' },
                                                { label: 'Retrasados', n: dashStats.retrasados, p: donutData.p3, color: 'bg-red-500' },
                                            ]"
                                            :key="i"
                                            class="flex items-center gap-2 text-[11px]"
                                        >
                                            <span class="h-2 w-2 shrink-0 rounded-sm" :class="row.color"></span>
                                            <span class="flex-1 text-slate-600">{{ row.label }}</span>
                                            <span class="font-semibold text-slate-800">{{ row.n }}</span>
                                            <div class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-100">
                                                <div class="h-full rounded-full" :class="row.color" :style="{ width: row.p + '%' }"></div>
                                            </div>
                                            <span class="w-7 text-right text-slate-500">{{ row.p }}%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Últimos Despachos -->
                            <div class="overflow-hidden rounded-xl border bg-white shadow-sm lg:col-span-3">
                                <div class="flex items-center justify-between border-b px-4 py-3">
                                    <h2 class="text-sm font-semibold text-slate-900">Últimos Despachos</h2>
                                    <div class="flex items-center gap-2">
                                        <div class="relative">
                                            <Search class="absolute top-1/2 left-2 h-3 w-3 -translate-y-1/2 text-slate-400" />
                                            <input
                                                v-model="tableSearch"
                                                placeholder="Buscar…"
                                                class="h-7 rounded-lg border border-slate-200 pr-3 pl-6 text-[11px] outline-none focus:ring-1 focus:ring-slate-300"
                                            />
                                        </div>
                                        <button class="text-xs font-medium whitespace-nowrap text-blue-600 hover:underline">Ver todos</button>
                                    </div>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="w-full text-[11px]">
                                        <thead>
                                            <tr class="border-b bg-slate-50">
                                                <th class="px-3 py-2 text-left font-semibold text-slate-500">Guía</th>
                                                <th class="hidden px-3 py-2 text-left font-semibold text-slate-500 sm:table-cell">Origen</th>
                                                <th class="px-3 py-2 text-left font-semibold text-slate-500">Destino</th>

                                                <th class="px-3 py-2 text-left font-semibold text-slate-500">Fecha</th>
                                                <th class="px-3 py-2 text-left font-semibold text-slate-500">Estado</th>
                                                <th class="hidden px-3 py-2 text-left font-semibold text-slate-500 lg:table-cell">Encargado</th>
                                                <th class="px-3 py-2 text-center font-semibold text-slate-500">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="g in groupedDispatches"
                                                :key="g.key"
                                                class="cursor-pointer border-b transition-colors last:border-0"
                                                :class="g.items.some((i) => i.id === selectedDispatch?.id) ? 'bg-blue-50' : 'hover:bg-slate-50'"
                                                @click="selectDispatch(g.items[0])"
                                            >
                                                <!-- Guía / N° despacho -->
                                                <td class="px-3 py-2.5">
                                                    <span class="font-mono font-semibold text-slate-900">
                                                        {{ g.guide_number ?? g.items[0].dispatch_number }}
                                                    </span>
                                                    <span
                                                        v-if="g.items.length > 1"
                                                        class="ml-1.5 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500"
                                                    >
                                                        {{ g.items.length }} ítems
                                                    </span>
                                                </td>
                                                <!-- Origen -->
                                                <td class="hidden px-3 py-2.5 sm:table-cell">
                                                    <p v-if="g.origin_business" class="truncate text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ g.origin_business }}</p>
                                                    <p class="truncate font-medium text-slate-800">{{ g.origin_name }}</p>
                                                    <p class="text-[11px] text-slate-400">{{ g.origin_label ?? 'Sede / Almacén' }}</p>
                                                </td>
                                                <!-- Destino -->
                                                <td class="max-w-[120px] px-3 py-2.5">
                                                    <p v-if="g.destination_business" class="truncate text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ g.destination_business }}</p>
                                                    <p class="truncate font-medium text-slate-800">{{ g.destination_name }}</p>
                                                    <p class="text-[11px] text-slate-400">{{ g.destination_label }}</p>
                                                </td>

                                                <!-- Fecha -->
                                                <td class="px-3 py-2.5 whitespace-nowrap text-slate-600">{{ g.dispatched_at }}</td>
                                                <!-- Estado -->
                                                <td class="px-3 py-2.5">
                                                    <span
                                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium"
                                                        :class="groupStatus(g).cls"
                                                    >
                                                        <span class="h-1.5 w-1.5 rounded-full" :class="groupStatus(g).dot"></span>
                                                        {{ groupStatus(g).label }}
                                                    </span>
                                                </td>
                                                <!-- Encargado -->
                                                <td class="hidden truncate px-3 py-2.5 text-slate-600 lg:table-cell">
                                                    {{ g.staff_name || g.dispatched_by }}
                                                </td>
                                                <!-- Acciones -->
                                                <td class="px-3 py-2.5">
                                                    <div class="flex items-center justify-center gap-1">
                                                        <button
                                                            title="Descargar guía PDF"
                                                            class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                                            @click.stop="downloadGroup(g)"
                                                        >
                                                            <FileDown class="h-3.5 w-3.5" />
                                                        </button>
                                                        <button
                                                            v-if="g.items.some((i) => i.status === 'active' && !i.received_at)"
                                                            title="Registrar retorno"
                                                            class="rounded p-1 text-slate-400 hover:bg-amber-50 hover:text-amber-600"
                                                            @click.stop="
                                                                confirmReturn = g.items.find((i) => i.status === 'active' && !i.received_at) ?? null
                                                            "
                                                        >
                                                            <RotateCcw class="h-3.5 w-3.5" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="groupedDispatches.length === 0">
                                                <td colspan="7" class="px-4 py-8 text-center text-slate-400">No hay despachos registrados</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT col -->
                    <div class="space-y-5">
                        <!-- Guía Seleccionada -->
                        <div class="rounded-xl border bg-white shadow-sm">
                            <!-- Header -->
                            <div class="flex items-center justify-between border-b px-4 py-3">
                                <div v-if="selectedDispatch" class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-slate-500">Guía Seleccionada</span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase" :class="getStatus(selectedDispatch).cls">
                                        {{ getStatus(selectedDispatch).label }}
                                    </span>
                                </div>
                                <h2 v-else class="text-sm font-semibold text-slate-900">Guía Seleccionada</h2>
                            </div>

                            <!-- Empty state -->
                            <div v-if="!selectedDispatch" class="flex flex-col items-center gap-3 py-10 text-center">
                                <FileText class="h-10 w-10 text-slate-200" />
                                <div>
                                    <p class="text-sm font-medium text-slate-500">Selecciona una guía</p>
                                    <p class="text-xs text-slate-400">Haz clic en una fila de la tabla</p>
                                </div>
                            </div>

                            <!-- Detail -->
                            <div v-else class="space-y-4 px-4 py-4">
                                <p class="text-base font-bold text-slate-900">Guía: {{ selectedDispatch.dispatch_number }}</p>

                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <p class="text-slate-400">Destino / Cliente</p>
                                        <p v-if="selectedDispatch.destination_business" class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ selectedDispatch.destination_business }}</p>
                                        <p class="font-medium text-slate-800">{{ selectedDispatch.destination_name }}</p>
                                        <p class="text-[10px] text-slate-400">{{ selectedDispatch.destination_label }}</p>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <MapPin class="mt-0.5 h-3 w-3 shrink-0 text-red-500" />
                                        <div>
                                            <p class="text-slate-400">Origen</p>
                                            <p v-if="selectedDispatch.origin_business" class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ selectedDispatch.origin_business }}</p>
                                            <p class="font-medium text-slate-800">{{ selectedDispatch.origin_name }}</p>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="text-slate-400">Encargado</p>
                                        <p class="font-medium text-slate-800">{{ selectedDispatch.staff_name || selectedDispatch.dispatched_by }}</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-400">Equipo</p>
                                        <p class="truncate font-medium text-slate-800">{{ selectedDispatch.equipment_name }}</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-400">Tipo destino</p>
                                        <p class="font-medium text-slate-800">{{ selectedDispatch.destination_label }}</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-400">Cantidad</p>
                                        <p class="font-medium text-slate-800">{{ selectedDispatch.quantity }} und.</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-400">Fecha despacho</p>
                                        <p class="font-medium text-slate-800">{{ selectedDispatch.dispatched_at }}</p>
                                    </div>
                                    <div v-if="selectedDispatch.received_at">
                                        <p class="text-slate-400">Recepcionado</p>
                                        <p class="font-medium text-emerald-600">{{ selectedDispatch.received_at }}</p>
                                    </div>
                                </div>

                                <!-- Description -->
                                <p v-if="selectedDispatch.description" class="rounded-lg bg-slate-50 p-2.5 text-xs text-slate-600 italic">
                                    "{{ selectedDispatch.description }}"
                                </p>

                                <!-- Actions -->
                                <div class="flex gap-2">
                                    <Button variant="outline" size="sm" class="flex-1 gap-1.5 text-xs" @click="downloadGuide(selectedDispatch)">
                                        <FileDown class="h-3.5 w-3.5" />
                                        Ver Guía
                                    </Button>
                                    <Button
                                        size="sm"
                                        class="flex-1 gap-1.5 bg-red-600 text-xs hover:bg-red-700"
                                        @click="downloadGuide(selectedDispatch)"
                                    >
                                        <FileText class="h-3.5 w-3.5" />
                                        Ver Documentos
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Línea de Tiempo -->
                        <div class="rounded-xl border bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b px-4 py-3">
                                <h2 class="text-sm font-semibold text-slate-900">Línea de Tiempo de la Guía</h2>
                                <button v-if="selectedDispatch" class="text-xs font-medium text-blue-600 hover:underline">Ver detalle</button>
                            </div>

                            <div v-if="!selectedDispatch" class="flex flex-col items-center gap-2 py-8 text-center">
                                <Activity class="h-8 w-8 text-slate-200" />
                                <p class="text-xs text-slate-400">Selecciona una guía para ver su línea de tiempo</p>
                            </div>

                            <div v-else class="px-4 py-4">
                                <div class="relative space-y-4 pl-5">
                                    <!-- Vertical line -->
                                    <div class="absolute top-2 bottom-2 left-[9px] w-px bg-slate-200"></div>

                                    <div v-for="(step, i) in selectedTimeline" :key="i" class="relative">
                                        <!-- Dot -->
                                        <div
                                            class="absolute -left-5 mt-0.5 flex h-4 w-4 items-center justify-center rounded-full ring-2 ring-white"
                                            :class="step.current ? 'bg-blue-500 ring-blue-200' : step.done ? 'bg-emerald-500' : 'bg-slate-200'"
                                        >
                                            <span v-if="step.current" class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                            <CheckCircle2 v-else-if="step.done" class="h-3 w-3 text-white" />
                                        </div>

                                        <div>
                                            <p
                                                class="text-xs font-semibold"
                                                :class="step.current ? 'text-blue-600' : step.done ? 'text-slate-800' : 'text-slate-400'"
                                            >
                                                {{ step.label }}
                                            </p>
                                            <p class="text-[11px] text-slate-400">{{ step.time }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recepciones Pendientes -->
                        <div class="rounded-xl border bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b px-4 py-3">
                                <h2 class="text-sm font-semibold text-slate-900">Recepciones Pendientes</h2>
                                <span class="rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-semibold text-orange-700">
                                    {{ activeDispatches.filter((d) => !d.received_at).length }}
                                </span>
                            </div>

                            <div
                                v-if="activeDispatches.filter((d) => !d.received_at).length === 0"
                                class="flex flex-col items-center gap-2 py-6 text-center"
                            >
                                <PackageCheck class="h-8 w-8 text-emerald-300" />
                                <p class="text-xs text-slate-400">Todas las guías han sido recibidas</p>
                            </div>

                            <div v-else class="divide-y">
                                <div
                                    v-for="d in activeDispatches.filter((d) => !d.received_at).slice(0, 5)"
                                    :key="d.id"
                                    class="flex items-center gap-3 px-4 py-2.5"
                                >
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-orange-100">
                                        <component
                                            :is="d.equipable_type === 'computer' ? Laptop : UtensilsCrossed"
                                            class="h-3.5 w-3.5 text-orange-600"
                                        />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-[11px] font-semibold text-slate-800">{{ d.dispatch_number }}</p>
                                        <p class="truncate text-[10px] text-slate-500">{{ d.destination_name }}</p>
                                    </div>
                                    <button
                                        class="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-semibold whitespace-nowrap text-emerald-700 hover:bg-emerald-100"
                                        @click="confirmId = d.id"
                                    >
                                        Recibir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- ═══ DIALOG: Nueva Guía de Remisión ═══ -->
    <Dialog :open="showForm" @update:open="(v) => !v && (showForm = false)">
        <DialogScrollContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100">
                        <FileText class="h-4 w-4 text-red-600" />
                    </div>
                    Nueva Guía de Remisión
                </DialogTitle>
            </DialogHeader>

            <div class="space-y-4 py-2">
                <!-- Origin & Dest type -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <Label>Sede Origen <span class="text-red-500">*</span></Label>
                        <Select v-model="form.origin_headquarter_id">
                            <SelectTrigger><SelectValue placeholder="Seleccionar sede…" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="h in headquarters" :key="h.id" :value="String(h.id)">
                                    {{ h.name }}{{ h.business ? ` — ${h.business.name}` : '' }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.origin_headquarter_id" class="text-xs text-red-500">{{ form.errors.origin_headquarter_id }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Tipo Destino <span class="text-red-500">*</span></Label>
                        <Select v-model="form.destination_type" @update:model-value="() => (form.destination_id = '')">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cafe">Café / Comedor</SelectItem>
                                <SelectItem value="unit">Unidad</SelectItem>
                                <SelectItem value="mine">Mina</SelectItem>
                                <SelectItem value="headquarter">Sede</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Destination & Staff -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <Label>Destino <span class="text-red-500">*</span></Label>
                        <Select v-model="form.destination_id">
                            <SelectTrigger><SelectValue placeholder="Seleccionar destino…" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in destinationOptions" :key="opt.id" :value="String(opt.id)">{{ opt.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.destination_id" class="text-xs text-red-500">{{ form.errors.destination_id }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Encargado de Recepción</Label>
                        <Select v-model="form.staff_id">
                            <SelectTrigger><SelectValue placeholder="Sin asignar" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Sin asignar</SelectItem>
                                <SelectItem v-for="s in staff" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Equipment Table -->
                <div class="space-y-2">
                    <Label>Equipos a Despachar <span class="text-red-500">*</span></Label>

                    <!-- Search + type filter -->
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <Search class="absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="itemSearch"
                                placeholder="Buscar por nombre o sede…"
                                class="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-8 text-sm outline-none focus:ring-2 focus:ring-slate-300"
                            />
                        </div>
                        <div class="flex overflow-hidden rounded-lg border border-slate-200">
                            <button
                                @click="itemTypeFilter = 'all'"
                                class="px-3 py-2 text-xs font-medium transition-colors"
                                :class="itemTypeFilter === 'all' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
                            >
                                Todos
                            </button>
                            <button
                                @click="itemTypeFilter = 'computer'"
                                class="border-x px-3 py-2 transition-colors"
                                :class="itemTypeFilter === 'computer' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
                            >
                                <Laptop class="h-3.5 w-3.5" />
                            </button>
                            <button
                                @click="itemTypeFilter = 'kitchen'"
                                class="px-3 py-2 transition-colors"
                                :class="itemTypeFilter === 'kitchen' ? 'bg-orange-500 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
                            >
                                <UtensilsCrossed class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="max-h-64 overflow-y-auto rounded-lg border">
                        <table class="w-full text-xs">
                            <thead class="sticky top-0 bg-slate-50">
                                <tr class="border-b">
                                    <th class="px-3 py-2 text-left font-semibold text-slate-500">Tipo</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-500">Equipo</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-500">Sede</th>
                                    <th class="px-3 py-2 text-center font-semibold text-slate-500">Stock</th>
                                    <th class="px-3 py-2 text-center font-semibold text-slate-500">Despachar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="eq in filteredEquipmentTable"
                                    :key="`${eq.equipable_type}-${eq.id}`"
                                    class="border-b transition-colors last:border-0"
                                    :class="eq.qty_to_dispatch > 0 ? 'bg-red-50/60' : 'hover:bg-slate-50'"
                                >
                                    <td class="px-3 py-2">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                                            :class="eq.equipable_type === 'computer' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700'"
                                        >
                                            <component :is="eq.equipable_type === 'computer' ? Laptop : UtensilsCrossed" class="h-2.5 w-2.5" />
                                            {{ eq.equipable_type === 'computer' ? 'IT' : 'Cocina' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <p class="font-medium text-slate-800">{{ eq.name }}</p>
                                        <p v-if="eq.brand || eq.model" class="text-slate-400">
                                            {{ [eq.brand, eq.model].filter(Boolean).join(' · ') }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">{{ eq.storage_headquarter?.name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700">{{
                                            eq.quantity
                                        }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <input
                                            v-model.number="eq.qty_to_dispatch"
                                            type="number"
                                            min="0"
                                            :max="eq.quantity"
                                            class="w-16 rounded-lg border border-slate-200 px-2 py-1 text-center text-xs outline-none focus:ring-2 focus:ring-red-300"
                                            :class="eq.qty_to_dispatch > 0 ? 'border-red-300 bg-red-50 font-bold text-red-700' : ''"
                                            @input="eq.qty_to_dispatch = eq.qty_to_dispatch > eq.quantity ? eq.quantity : eq.qty_to_dispatch"
                                        />
                                    </td>
                                </tr>
                                <tr v-if="filteredEquipmentTable.length === 0">
                                    <td colspan="5" class="px-4 py-6 text-center text-slate-400">Sin equipos disponibles</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Selected summary chips -->
                    <div v-if="dispatchItems.length > 0" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="item in dispatchItems"
                            :key="`${item.equipable_type}-${item.equipable_id}`"
                            class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-1 text-[11px] font-semibold text-red-700"
                        >
                            <component :is="item.equipable_type === 'computer' ? Laptop : UtensilsCrossed" class="h-3 w-3" />
                            {{ equipmentTable.find((e) => e.id === item.equipable_id && e.equipable_type === item.equipable_type)?.name }}
                            × {{ item.quantity }}
                        </span>
                    </div>
                    <p v-else class="text-[11px] text-slate-400">Ingresa cantidad mayor a 0 en la columna "Despachar" para añadir equipos.</p>
                </div>

                <!-- Description -->
                <div class="space-y-1.5">
                    <Label>Descripción / Observaciones</Label>
                    <Textarea v-model="form.description" rows="2" placeholder="Notas adicionales…" />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="showForm = false">Cancelar</Button>
                <Button class="gap-2 bg-red-600 hover:bg-red-700" :disabled="!formValid || form.processing" @click="submitForm">
                    <FileText class="h-4 w-4" />
                    {{ form.processing ? 'Generando…' : 'Generar Guía de Remisión' }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>

    <!-- ═══ DIALOG: Confirmar Retorno ═══ -->
    <Dialog :open="!!confirmReturn" @update:open="(v) => !v && (confirmReturn = null)">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <RotateCcw class="h-5 w-5 text-amber-500" />
                    Confirmar Retorno
                </DialogTitle>
            </DialogHeader>
            <div v-if="confirmReturn" class="space-y-1 py-2 text-sm text-slate-600">
                <p>
                    ¿Confirmar el retorno del despacho <strong class="text-slate-900">{{ confirmReturn.dispatch_number }}</strong
                    >?
                </p>
                <p class="rounded-lg bg-slate-50 p-2 text-xs text-slate-500">
                    Equipo: <strong>{{ confirmReturn.equipment_name }}</strong> ({{ confirmReturn.quantity }} unidades)<br />
                    Esto devolverá el stock al almacén de origen.
                </p>
            </div>
            <DialogFooter>
                <Button variant="outline" size="sm" @click="confirmReturn = null">Cancelar</Button>
                <Button size="sm" class="gap-1.5 bg-amber-500 hover:bg-amber-600" @click="doReturn">
                    <RotateCcw class="h-4 w-4" />
                    Confirmar Retorno
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ═══ DIALOG: Confirmar Recepción ═══ -->
    <Dialog :open="!!confirmId" @update:open="(v) => !v && (confirmId = null)">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <PackageCheck class="h-5 w-5 text-emerald-500" />
                    Confirmar Recepción
                </DialogTitle>
            </DialogHeader>
            <p class="py-2 text-sm text-slate-600">
                ¿Confirmar la recepción del despacho
                <strong class="text-slate-900">{{ activeDispatches.find((d) => d.id === confirmId)?.dispatch_number }}</strong
                >?
            </p>
            <DialogFooter>
                <Button variant="outline" size="sm" @click="confirmId = null">Cancelar</Button>
                <Button size="sm" class="gap-1.5 bg-emerald-600 hover:bg-emerald-700" :disabled="processing" @click="doReceive(confirmId!)">
                    <PackageCheck class="h-4 w-4" />
                    {{ processing ? 'Procesando…' : 'Confirmar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
