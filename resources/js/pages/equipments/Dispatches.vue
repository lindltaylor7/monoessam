<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Building2,
    CheckCircle2,
    FileDown,
    Laptop,
    MapPin,
    PackageCheck,
    PackageOpen,
    Plus,
    RotateCcw,
    Search,
    Truck,
    UtensilsCrossed,
    Warehouse,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

// ── Types ──────────────────────────────────────────────────────────────────
interface StaffRef { id: number; name: string }
interface HQRef    { id: number; name: string; business: { id: number; name: string } | null }

interface EquipmentBase {
    id: number; name: string; brand: string | null; model: string | null;
    code: string | null; series: string | null; status: number; quantity: number;
    storage_headquarter_id: number | null;
    storage_headquarter: HQRef | null;
    responsible: StaffRef | null;
}

interface Dispatch {
    id: number; dispatch_number: string; status: 'active' | 'returned';
    equipable_type: 'computer' | 'kitchen'; equipable_id: number; quantity: number;
    equipment_name: string; equipment_brand: string | null; equipment_model: string | null;
    equipment_code: string | null; equipment_series: string | null; equipment_status: number;
    origin_id: number | null; origin_name: string;
    destination_type: string; destination_label: string; destination_name: string; destination_id: number;
    staff_id: number | null; staff_name: string | null;
    description: string | null;
    dispatched_by: string; dispatched_at: string; dispatched_at_raw: string; returned_at: string | null;
}

const props = defineProps<{
    dispatches: Dispatch[];
    computerEquipments: EquipmentBase[];
    kitchenEquipments: EquipmentBase[];
    headquarters: HQRef[];
    cafes: Array<{ id: number; name: string; unit: { id: number; name: string; mine: { id: number; name: string } } }>;
    units: Array<{ id: number; name: string; mine: { id: number; name: string } }>;
    mines: Array<{ id: number; name: string }>;
    staff: StaffRef[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Equipos', href: '/equipments' },
    { title: 'Despachos', href: '/equipment-dispatches' },
];

// ── Status helpers ─────────────────────────────────────────────────────────
const EQUIP_STATUSES: Record<number, { label: string; cls: string }> = {
    0: { label: 'Nuevo',   cls: 'bg-blue-100 text-blue-700 border-blue-200'    },
    1: { label: 'Bueno',   cls: 'bg-green-100 text-green-700 border-green-200' },
    2: { label: 'Regular', cls: 'bg-yellow-100 text-yellow-700 border-yellow-200' },
    3: { label: 'Dañado',  cls: 'bg-red-100 text-red-700 border-red-200'       },
    4: { label: 'Baja',    cls: 'bg-gray-100 text-gray-600 border-gray-200'    },
};

// ── State ──────────────────────────────────────────────────────────────────
const searchQuery    = ref('');
const statusFilter   = ref<'all' | 'active' | 'returned'>('all');
const activeTab      = ref('dispatches');
const showForm       = ref(false);
const confirmReturn  = ref<Dispatch | null>(null);

// ── Form ───────────────────────────────────────────────────────────────────
const form = useForm({
    equipable_type:        'computer' as 'computer' | 'kitchen',
    equipable_id:          '' as string | number,
    quantity:              1,
    origin_headquarter_id: '' as string | number,
    destination_type:      'cafe' as string,
    destination_id:        '' as string | number,
    staff_id:              'none' as string | number,
    description:           '',
});

// ── Computed: available equipment (quantity > 0) ───────────────────────────
const availableEquipment = computed(() => {
    const list = form.equipable_type === 'computer'
        ? props.computerEquipments
        : props.kitchenEquipments;
    return list.filter(e => e.quantity > 0);
});

const selectedEquipment = computed(() =>
    availableEquipment.value.find(e => String(e.id) === String(form.equipable_id)) ?? null
);

// ── Computed: destination options ─────────────────────────────────────────
const destinationOptions = computed(() => {
    switch (form.destination_type) {
        case 'cafe':        return props.cafes.map(c => ({ id: c.id, label: `${c.name} — ${c.unit?.mine?.name ?? ''}` }));
        case 'unit':        return props.units.map(u => ({ id: u.id, label: `${u.name} — ${u.mine?.name ?? ''}` }));
        case 'mine':        return props.mines.map(m => ({ id: m.id, label: m.name }));
        case 'headquarter': return props.headquarters.map(h => ({ id: h.id, label: h.name }));
        default: return [];
    }
});

// ── Computed: filtered dispatches ─────────────────────────────────────────
const filteredDispatches = computed(() => {
    const q = searchQuery.value.toLowerCase();
    return props.dispatches.filter(d => {
        if (statusFilter.value !== 'all' && d.status !== statusFilter.value) return false;
        if (!q) return true;
        return [d.dispatch_number, d.equipment_name, d.origin_name, d.destination_name, d.staff_name]
            .some(v => v?.toLowerCase().includes(q));
    });
});

// ── Computed: equipment in storage grouped by HQ (quantity > 0) ───────────
const storageGroups = computed(() => {
    const all = [
        ...props.computerEquipments.map(e => ({ ...e, equipable_type: 'computer' as const })),
        ...props.kitchenEquipments.map(e => ({ ...e, equipable_type: 'kitchen' as const })),
    ].filter(e => e.quantity > 0);

    const groups: Record<string, { hq: HQRef | null; items: typeof all }> = {};
    for (const e of all) {
        const key = e.storage_headquarter_id ? String(e.storage_headquarter_id) : '__none__';
        if (!groups[key]) groups[key] = { hq: e.storage_headquarter, items: [] };
        groups[key].items.push(e);
    }
    return Object.values(groups);
});

// ── Stats (based on quantities) ────────────────────────────────────────────
const stats = computed(() => {
    const allEquipments = [
        ...props.computerEquipments,
        ...props.kitchenEquipments,
    ];
    const inStorage  = allEquipments.reduce((s, e) => s + (e.quantity ?? 0), 0);
    const dispatched = props.dispatches
        .filter(d => d.status === 'active')
        .reduce((s, d) => s + (d.quantity ?? 1), 0);
    return { total: allEquipments.length, inStorage, active: dispatched };
});

// ── Actions ────────────────────────────────────────────────────────────────
function openCreate() {
    form.reset();
    form.equipable_type   = 'computer';
    form.destination_type = 'cafe';
    form.quantity         = 1;
    showForm.value        = true;
}

function submitForm() {
    const data = { ...form.data() };
    if (data.staff_id === 'none') data.staff_id = '';
    router.post(route('equipment-dispatches.store'), data, {
        preserveScroll: true,
        onSuccess: () => { showForm.value = false; form.reset(); },
    });
}

function doReturn() {
    if (!confirmReturn.value) return;
    router.put(route('equipment-dispatches.return', confirmReturn.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { confirmReturn.value = null; },
    });
}

function downloadPdf(id: number) {
    window.open(route('equipment-dispatches.pdf', id), '_blank');
}
</script>

<template>
    <Head title="Despachos de Equipos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 pb-8">

            <!-- ── Header ──────────────────────────────────────────── -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="router.visit(route('equipments.index'))">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 class="text-xl font-bold">Despachos de Equipos</h1>
                        <p class="text-muted-foreground mt-0.5 text-sm">Control de almacenes Lima / Huancayo y destinos</p>
                    </div>
                </div>
                <Button class="bg-red-600 hover:bg-red-700" @click="openCreate">
                    <Plus class="mr-1.5 h-4 w-4" /> Nuevo Despacho
                </Button>
            </div>

            <!-- ── Stats ───────────────────────────────────────────── -->
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="bg-card rounded-xl border p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-slate-100 p-2"><Laptop class="h-5 w-5 text-slate-600" /></div>
                        <div>
                            <p class="text-muted-foreground text-xs font-semibold uppercase">Total Equipos</p>
                            <p class="text-2xl font-black">{{ stats.total }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-card rounded-xl border p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-emerald-50 p-2"><Warehouse class="h-5 w-5 text-emerald-600" /></div>
                        <div>
                            <p class="text-muted-foreground text-xs font-semibold uppercase">En Almacén</p>
                            <p class="text-2xl font-black text-emerald-700">{{ stats.inStorage }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-card rounded-xl border p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-amber-50 p-2"><Truck class="h-5 w-5 text-amber-600" /></div>
                        <div>
                            <p class="text-muted-foreground text-xs font-semibold uppercase">Despachados</p>
                            <p class="text-2xl font-black text-amber-700">{{ stats.active }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-card rounded-xl border p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-blue-50 p-2"><PackageCheck class="h-5 w-5 text-blue-600" /></div>
                        <div>
                            <p class="text-muted-foreground text-xs font-semibold uppercase">Total Despachos</p>
                            <p class="text-2xl font-black text-blue-700">{{ dispatches.length }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Tabs ─────────────────────────────────────────────── -->
            <Tabs v-model="activeTab">
                <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                    <TabsList>
                        <TabsTrigger value="dispatches" class="gap-1.5">
                            <Truck class="h-3.5 w-3.5" /> Despachos
                        </TabsTrigger>
                        <TabsTrigger value="storage" class="gap-1.5">
                            <Warehouse class="h-3.5 w-3.5" /> En Almacén
                        </TabsTrigger>
                    </TabsList>

                    <div v-if="activeTab === 'dispatches'" class="flex flex-wrap items-center gap-2">
                        <div class="relative">
                            <Search class="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                            <Input v-model="searchQuery" placeholder="Buscar despacho…" class="h-9 w-56 pl-9" />
                        </div>
                        <Select v-model="statusFilter">
                            <SelectTrigger class="h-9 w-36"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                <SelectItem value="active">Activos</SelectItem>
                                <SelectItem value="returned">Retornados</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- ─ Tab: Despachos ────────────────────────────────── -->
                <TabsContent value="dispatches" class="mt-4">
                    <div class="bg-card overflow-x-auto rounded-xl border shadow-sm">
                        <table class="w-full table-auto border-collapse">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold">N° Despacho</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold">Equipo</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold">Cant.</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold">Origen</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold">Destino</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold">Encargado</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold">Fecha</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold">Estado</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="filteredDispatches.length === 0">
                                    <td colspan="9" class="py-14 text-center">
                                        <div class="text-muted-foreground flex flex-col items-center gap-2">
                                            <PackageOpen class="h-10 w-10 opacity-30" />
                                            <span class="text-sm">No hay despachos registrados</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-for="d in filteredDispatches"
                                    :key="d.id"
                                    class="hover:bg-muted/30 border-t transition"
                                >
                                    <td class="px-4 py-3">
                                        <span class="bg-muted rounded px-2 py-0.5 font-mono text-xs font-bold">
                                            {{ d.dispatch_number }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <component
                                                :is="d.equipable_type === 'computer' ? Laptop : UtensilsCrossed"
                                                class="h-4 w-4 flex-shrink-0"
                                                :class="d.equipable_type === 'computer' ? 'text-blue-500' : 'text-orange-500'"
                                            />
                                            <div>
                                                <p class="text-sm font-semibold">{{ d.equipment_name }}</p>
                                                <p class="text-muted-foreground text-xs">
                                                    {{ [d.equipment_brand, d.equipment_model].filter(Boolean).join(' · ') || '—' }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 font-mono text-xs font-bold text-amber-700">
                                            {{ d.quantity }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="flex items-center gap-1 text-sm">
                                            <Warehouse class="h-3.5 w-3.5 text-slate-400" />
                                            {{ d.origin_name }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>
                                            <span class="text-muted-foreground mb-0.5 block text-[10px] font-bold uppercase">
                                                {{ d.destination_label }}
                                            </span>
                                            <span class="flex items-center gap-1 text-sm font-medium">
                                                <MapPin class="h-3.5 w-3.5 text-red-400" />
                                                {{ d.destination_name }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span v-if="d.staff_name" class="flex items-center gap-1.5 text-sm">
                                            <CheckCircle2 class="h-3.5 w-3.5 text-green-500" />
                                            {{ d.staff_name }}
                                        </span>
                                        <span v-else class="text-muted-foreground flex items-center gap-1 text-xs">
                                            <AlertTriangle class="h-3.5 w-3.5 text-amber-400" /> Sin asignar
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">{{ d.dispatched_at }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            :class="[
                                                'inline-flex rounded-full border px-2.5 py-0.5 text-[11px] font-semibold',
                                                d.status === 'active'
                                                    ? 'border-amber-200 bg-amber-100 text-amber-700'
                                                    : 'border-gray-200 bg-gray-100 text-gray-500',
                                            ]"
                                        >
                                            {{ d.status === 'active' ? 'Activo' : 'Retornado' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-center gap-1">
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <Button variant="ghost" size="icon" class="h-8 w-8 text-slate-500 hover:text-blue-600" @click="downloadPdf(d.id)">
                                                            <FileDown class="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>Descargar PDF</TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                            <TooltipProvider v-if="d.status === 'active'">
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <Button variant="ghost" size="icon" class="h-8 w-8 text-slate-500 hover:text-emerald-600" @click="confirmReturn = d">
                                                            <RotateCcw class="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>Registrar Retorno</TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </TabsContent>

                <!-- ─ Tab: En Almacén ────────────────────────────────── -->
                <TabsContent value="storage" class="mt-4 space-y-4">
                    <div v-if="storageGroups.length === 0" class="bg-card flex flex-col items-center justify-center gap-2 rounded-xl border py-14 shadow-sm">
                        <Warehouse class="text-muted-foreground h-10 w-10 opacity-30" />
                        <span class="text-muted-foreground text-sm">Todos los equipos están despachados</span>
                    </div>

                    <div v-for="group in storageGroups" :key="group.hq?.id ?? '__none__'" class="bg-card overflow-hidden rounded-xl border shadow-sm">
                        <!-- Group header -->
                        <div class="flex items-center gap-2 border-b bg-slate-50 px-5 py-3 dark:bg-slate-800/40">
                            <Building2 class="h-4 w-4 text-slate-500" />
                            <span class="text-sm font-semibold">
                                {{ group.hq?.name ?? 'Sin sede asignada' }}
                            </span>
                            <span class="ml-auto rounded-full bg-slate-200 px-2 py-0.5 text-xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                {{ group.items.length }} equipos
                            </span>
                        </div>
                        <!-- Equipment table -->
                        <table class="w-full table-auto border-collapse">
                            <thead class="bg-muted/40">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold">Tipo</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold">Código</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold">Equipo</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold">Marca / Modelo</th>
                                    <th class="px-4 py-2 text-center text-xs font-semibold">Cant.</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold">Estado</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold">Responsable</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="eq in group.items" :key="`${eq.equipable_type}-${eq.id}`" class="hover:bg-muted/30 border-t transition">
                                    <td class="px-4 py-2.5">
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                                eq.equipable_type === 'computer'
                                                    ? 'bg-blue-100 text-blue-700'
                                                    : 'bg-orange-100 text-orange-700',
                                            ]"
                                        >
                                            <component :is="eq.equipable_type === 'computer' ? Laptop : UtensilsCrossed" class="h-3 w-3" />
                                            {{ eq.equipable_type === 'computer' ? 'IT' : 'Cocina' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span class="bg-muted rounded px-2 py-0.5 font-mono text-xs">{{ eq.code || '—' }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-sm font-semibold">{{ eq.name }}</td>
                                    <td class="text-muted-foreground px-4 py-2.5 text-sm">
                                        {{ [eq.brand, eq.model].filter(Boolean).join(' · ') || '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 font-mono text-xs font-bold text-emerald-700">
                                            {{ eq.quantity }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span :class="['inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold', EQUIP_STATUSES[eq.status]?.cls]">
                                            {{ EQUIP_STATUSES[eq.status]?.label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span v-if="eq.responsible" class="flex items-center gap-1 text-sm">
                                            <CheckCircle2 class="h-3.5 w-3.5 text-green-500" /> {{ eq.responsible.name }}
                                        </span>
                                        <span v-else class="text-muted-foreground text-xs">Sin asignar</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>

    <!-- ══ DIALOG: Nuevo Despacho ═══════════════════════════════════════════ -->
    <Dialog v-model:open="showForm">
        <DialogScrollContent class="max-w-xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Truck class="h-5 w-5 text-red-600" /> Registrar Nuevo Despacho
                </DialogTitle>
            </DialogHeader>

            <div class="grid gap-5 py-2">

                <!-- Tipo de equipo -->
                <div class="grid gap-2">
                    <Label class="text-xs font-bold uppercase tracking-wide">Tipo de Equipo</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            :class="[
                                'flex items-center justify-center gap-2 rounded-lg border-2 py-2.5 text-sm font-medium transition-colors',
                                form.equipable_type === 'computer'
                                    ? 'border-red-600 bg-red-50 text-red-700'
                                    : 'border-gray-200 text-gray-500 hover:border-gray-300',
                            ]"
                            @click="form.equipable_type = 'computer'; form.equipable_id = ''"
                        >
                            <Laptop class="h-4 w-4" /> Tecnológico (IT)
                        </button>
                        <button
                            type="button"
                            :class="[
                                'flex items-center justify-center gap-2 rounded-lg border-2 py-2.5 text-sm font-medium transition-colors',
                                form.equipable_type === 'kitchen'
                                    ? 'border-red-600 bg-red-50 text-red-700'
                                    : 'border-gray-200 text-gray-500 hover:border-gray-300',
                            ]"
                            @click="form.equipable_type = 'kitchen'; form.equipable_id = ''"
                        >
                            <UtensilsCrossed class="h-4 w-4" /> Menaje / Cocina
                        </button>
                    </div>
                </div>

                <!-- Selección de equipo -->
                <div class="grid gap-1.5">
                    <Label>Equipo <span class="text-red-500">*</span></Label>
                    <Select v-model="form.equipable_id" @update:model-value="form.quantity = 1">
                        <SelectTrigger>
                            <SelectValue placeholder="Seleccionar equipo disponible…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="eq in availableEquipment"
                                :key="eq.id"
                                :value="String(eq.id)"
                            >
                                {{ eq.name }}{{ eq.brand ? ` — ${eq.brand}` : '' }}{{ eq.code ? ` [${eq.code}]` : '' }}
                                <span class="ml-1 text-xs text-emerald-600 font-semibold">({{ eq.quantity }} disp.)</span>
                            </SelectItem>
                            <div v-if="availableEquipment.length === 0" class="px-3 py-4 text-center text-xs text-gray-400">
                                Sin stock disponible para este tipo
                            </div>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.equipable_id" class="text-xs text-red-500">{{ form.errors.equipable_id }}</p>
                </div>

                <!-- Cantidad a despachar -->
                <div class="grid gap-1.5">
                    <Label>
                        Cantidad a Despachar <span class="text-red-500">*</span>
                        <span v-if="selectedEquipment" class="text-muted-foreground ml-2 text-xs font-normal">
                            máx. {{ selectedEquipment.quantity }}
                        </span>
                    </Label>
                    <Input
                        type="number"
                        v-model="form.quantity"
                        min="1"
                        :max="selectedEquipment?.quantity ?? 9999"
                        :disabled="!form.equipable_id"
                        placeholder="1"
                    />
                    <p v-if="form.errors.quantity" class="text-xs text-red-500">{{ form.errors.quantity }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Origen -->
                    <div class="grid gap-1.5">
                        <Label>Almacén Origen <span class="text-red-500">*</span></Label>
                        <Select v-model="form.origin_headquarter_id">
                            <SelectTrigger><SelectValue placeholder="Lima / Huancayo…" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="hq in headquarters" :key="hq.id" :value="String(hq.id)">
                                    {{ hq.name }}{{ hq.business ? ` · ${hq.business.name}` : '' }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.origin_headquarter_id" class="text-xs text-red-500">{{ form.errors.origin_headquarter_id }}</p>
                    </div>

                    <!-- Tipo destino -->
                    <div class="grid gap-1.5">
                        <Label>Tipo de Destino <span class="text-red-500">*</span></Label>
                        <Select v-model="form.destination_type" @update:model-value="form.destination_id = ''">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cafe">Café / Comedor</SelectItem>
                                <SelectItem value="unit">Unidad</SelectItem>
                                <SelectItem value="mine">Mina</SelectItem>
                                <SelectItem value="headquarter">Sede / Almacén</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Destino específico -->
                <div class="grid gap-1.5">
                    <Label>
                        Destino
                        <span class="text-muted-foreground ml-1 text-xs font-normal">
                            ({{ form.destination_type === 'cafe' ? 'Café / Comedor' : form.destination_type === 'unit' ? 'Unidad' : form.destination_type === 'mine' ? 'Mina' : 'Sede' }})
                        </span>
                        <span class="text-red-500">*</span>
                    </Label>
                    <Select v-model="form.destination_id">
                        <SelectTrigger><SelectValue placeholder="Seleccionar destino…" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="opt in destinationOptions" :key="opt.id" :value="String(opt.id)">
                                {{ opt.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.destination_id" class="text-xs text-red-500">{{ form.errors.destination_id }}</p>
                </div>

                <!-- Encargado -->
                <div class="grid gap-1.5">
                    <Label>Encargado / Receptor</Label>
                    <Select v-model="form.staff_id">
                        <SelectTrigger><SelectValue placeholder="Sin encargado específico" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Sin encargado</SelectItem>
                            <SelectItem v-for="s in staff" :key="s.id" :value="String(s.id)">
                                {{ s.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Descripción -->
                <div class="grid gap-1.5">
                    <Label>Descripción / Motivo del Despacho</Label>
                    <Textarea
                        v-model="form.description"
                        :rows="3"
                        placeholder="Ej: Envío de laptop para soporte técnico en mina, requerimiento urgente…"
                    />
                    <p v-if="form.errors.description" class="text-xs text-red-500">{{ form.errors.description }}</p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="showForm = false">Cancelar</Button>
                <Button
                    class="bg-red-600 hover:bg-red-700"
                    :disabled="form.processing || !form.equipable_id || !form.origin_headquarter_id || !form.destination_id || form.quantity < 1 || (!!selectedEquipment && form.quantity > selectedEquipment.quantity)"
                    @click="submitForm"
                >
                    <Truck class="mr-1.5 h-4 w-4" />
                    {{ form.processing ? 'Registrando…' : 'Registrar Despacho' }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>

    <!-- ══ DIALOG: Confirmar Retorno ════════════════════════════════════════ -->
    <Dialog :open="!!confirmReturn" @update:open="v => { if (!v) confirmReturn = null }">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <RotateCcw class="h-5 w-5 text-emerald-600" /> Registrar Retorno
                </DialogTitle>
            </DialogHeader>
            <div class="space-y-3 py-1">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Confirma que
                    <strong>{{ confirmReturn?.quantity }} {{ (confirmReturn?.quantity ?? 1) > 1 ? 'unidades' : 'unidad' }}</strong>
                    de <strong>{{ confirmReturn?.equipment_name }}</strong>
                    han sido retornadas desde <strong>{{ confirmReturn?.destination_name }}</strong>.
                </p>
                <div class="flex items-center gap-2 rounded-lg border bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                    <ArrowRight class="h-4 w-4 flex-shrink-0" />
                    Volverán al almacén <strong class="ml-1">{{ confirmReturn?.origin_name }}</strong>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="confirmReturn = null">Cancelar</Button>
                <Button class="bg-emerald-600 hover:bg-emerald-700" @click="doReturn">
                    <RotateCcw class="mr-1.5 h-4 w-4" /> Confirmar Retorno
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
