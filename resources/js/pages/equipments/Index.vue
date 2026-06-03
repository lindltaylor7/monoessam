<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle, CheckCircle2, ClipboardList, Clock, History,
    Laptop, Monitor, Pencil, Plus, Search, Trash2, UtensilsCrossed,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';

// ── Types ──────────────────────────────────────────────────────────────────
interface StaffRef { id: number; name: string }

interface ComputerEquipment {
    id: number; name: string; brand: string | null; model: string | null;
    description: string | null; presentation: string | null; color: string | null;
    series: string | null; code: string | null; status: number;
    responsible_id: number | null; responsible: StaffRef | null;
    histories_count: number;
}

interface KitchenEquipment {
    id: number; name: string; brand: string | null; model: string | null;
    size: string | null; description: string | null; color: string | null;
    current_type: string | null; series: string | null; manual: string | null;
    code: string | null; status: number;
    responsible_id: number | null; responsible: StaffRef | null;
    histories_count: number;
}

interface HistoryEntry {
    id: number; action: string; notes: string | null;
    staff: StaffRef | null; user: StaffRef | null;
    created_at: string;
}

const props = defineProps<{
    computerEquipments: ComputerEquipment[];
    kitchenEquipments:  KitchenEquipment[];
    staff: StaffRef[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Equipos', href: '/equipments' }];

// ── Constants ──────────────────────────────────────────────────────────────
const STATUSES = [
    { value: '0', label: 'Nuevo',   cls: 'bg-blue-100 text-blue-700 border-blue-200'     },
    { value: '1', label: 'Bueno',   cls: 'bg-green-100 text-green-700 border-green-200'  },
    { value: '2', label: 'Regular', cls: 'bg-yellow-100 text-yellow-700 border-yellow-200' },
    { value: '3', label: 'Dañado',  cls: 'bg-red-100 text-red-700 border-red-200'         },
    { value: '4', label: 'Baja',    cls: 'bg-gray-100 text-gray-600 border-gray-200'      },
];

const ACTIONS = ['Registro', 'Asignación', 'Mantenimiento', 'Reparación', 'Transferencia', 'Daño', 'Baja', 'Observación'];

const ACTION_COLORS: Record<string, string> = {
    Registro: 'bg-blue-100 text-blue-700', Asignación: 'bg-purple-100 text-purple-700',
    Mantenimiento: 'bg-yellow-100 text-yellow-700', Reparación: 'bg-orange-100 text-orange-700',
    Transferencia: 'bg-cyan-100 text-cyan-700', Daño: 'bg-red-100 text-red-700',
    Baja: 'bg-gray-100 text-gray-600', Observación: 'bg-green-100 text-green-700',
};

function statusInfo(val: number) {
    return STATUSES.find(s => s.value === String(val)) ?? STATUSES[0];
}

// ── State ──────────────────────────────────────────────────────────────────
const activeTab      = ref<'computer' | 'kitchen'>('computer');
const searchComputer = ref('');
const searchKitchen  = ref('');

// Form modal
const showForm   = ref(false);
const editTarget = ref<(ComputerEquipment | KitchenEquipment) | null>(null);

const form = useForm({
    type: 'computer' as 'computer' | 'kitchen',
    name: '', brand: '', model: '', description: '', color: '',
    series: '', code: '', presentation: '', size: '', current_type: '', manual: '',
    status: '0', responsible_id: 'none' as string | number,
});

// History sheet
const showHistory    = ref(false);
const historyItem    = ref<{ id: number; name: string; type: string } | null>(null);
const historyList    = ref<HistoryEntry[]>([]);
const historyLoading = ref(false);

const historyForm = reactive({ action: 'Observación', notes: '', staff_id: 'none', status: 'none' });
const historyProcessing = ref(false);

// Delete confirm
const deleteTarget = ref<{ id: number; type: string; name: string } | null>(null);

// ── Filtered lists ─────────────────────────────────────────────────────────
const filteredComputer = computed(() => {
    const q = searchComputer.value.toLowerCase();
    return q
        ? props.computerEquipments.filter(e =>
            [e.name, e.brand, e.model, e.code, e.series].some(f => f?.toLowerCase().includes(q)))
        : props.computerEquipments;
});

const filteredKitchen = computed(() => {
    const q = searchKitchen.value.toLowerCase();
    return q
        ? props.kitchenEquipments.filter(e =>
            [e.name, e.brand, e.model, e.code, e.series].some(f => f?.toLowerCase().includes(q)))
        : props.kitchenEquipments;
});

// ── Equipment form ─────────────────────────────────────────────────────────
function openCreate() {
    editTarget.value = null;
    form.reset();
    form.type = activeTab.value;
    form.status = '0';
    showForm.value = true;
}

function openEdit(item: ComputerEquipment | KitchenEquipment, type: 'computer' | 'kitchen') {
    editTarget.value = item;
    form.type           = type;
    form.name           = item.name ?? '';
    form.brand          = item.brand ?? '';
    form.model          = item.model ?? '';
    form.description    = item.description ?? '';
    form.color          = item.color ?? '';
    form.series         = item.series ?? '';
    form.code           = item.code ?? '';
    form.status         = String(item.status ?? 0);
    form.responsible_id = item.responsible_id ? String(item.responsible_id) : 'none';
    if (type === 'computer') {
        form.presentation = (item as ComputerEquipment).presentation ?? '';
        form.size = ''; form.current_type = ''; form.manual = '';
    } else {
        const k = item as KitchenEquipment;
        form.size = k.size ?? ''; form.current_type = k.current_type ?? ''; form.manual = k.manual ?? '';
        form.presentation = '';
    }
    showForm.value = true;
}

function submitForm() {
    if (form.responsible_id === 'none') form.responsible_id = '';
    const opts = {
        preserveScroll: true,
        onSuccess: () => { showForm.value = false; form.reset(); },
    };
    if (editTarget.value) {
        form.put(route('equipments.update', { type: form.type, id: editTarget.value.id }), opts);
    } else {
        form.post(route('equipments.store'), opts);
    }
}

// ── History ────────────────────────────────────────────────────────────────
function resetHistoryForm() {
    historyForm.action   = 'Observación';
    historyForm.notes    = '';
    historyForm.staff_id = 'none';
    historyForm.status   = 'none';
}

async function openHistory(item: { id: number; name: string }, type: string) {
    historyItem.value    = { id: item.id, name: item.name, type };
    historyList.value    = [];
    historyLoading.value = true;
    showHistory.value    = true;
    resetHistoryForm();

    try {
        const res = await fetch(route('equipments.history', { type, id: item.id }));
        historyList.value = await res.json();
    } finally {
        historyLoading.value = false;
    }
}

async function submitHistory() {
    if (!historyItem.value || historyProcessing.value) return;
    const item = { ...historyItem.value };

    historyProcessing.value = true;
    try {
        await axios.post(route('equipments.history.store', { type: item.type, id: item.id }), {
            action:     historyForm.action,
            notes:      historyForm.notes || null,
            staff_id:   historyForm.staff_id === 'none' ? null : historyForm.staff_id,
            status:     historyForm.status   === 'none' ? null : historyForm.status,
        });
        resetHistoryForm();
        router.reload({ only: ['computerEquipments', 'kitchenEquipments'] });
        await openHistory({ id: item.id, name: item.name }, item.type);
    } finally {
        historyProcessing.value = false;
    }
}

// ── Delete ─────────────────────────────────────────────────────────────────
function confirmDelete(item: { id: number; name: string }, type: string) {
    deleteTarget.value = { id: item.id, type, name: item.name };
}

function doDelete() {
    if (!deleteTarget.value) return;
    router.delete(route('equipments.destroy', { type: deleteTarget.value.type, id: deleteTarget.value.id }), {
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null; },
    });
}

// ── Helpers ────────────────────────────────────────────────────────────────
function fmtDate(d: string) {
    return new Date(d).toLocaleDateString('es-PE', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Equipos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 pb-8">

            <!-- ── Header ──────────────────────────────────────────────── -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Gestión de Equipos</h1>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Tecnológicos y de menaje · Estado, responsable e historial</p>
                </div>
                <Button class="bg-red-600 hover:bg-red-700" @click="openCreate">
                    <Plus class="mr-1.5 h-4 w-4" /> Nuevo equipo
                </Button>
            </div>

            <!-- ── Tabs ────────────────────────────────────────────────── -->
            <Tabs v-model="activeTab">
                <TabsList class="mb-1">
                    <TabsTrigger value="computer" class="flex items-center gap-1.5">
                        <Laptop class="h-4 w-4" /> Tecnológico
                        <span class="ml-1 rounded-full bg-gray-200 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            {{ computerEquipments.length }}
                        </span>
                    </TabsTrigger>
                    <TabsTrigger value="kitchen" class="flex items-center gap-1.5">
                        <UtensilsCrossed class="h-4 w-4" /> Menaje / Cocina
                        <span class="ml-1 rounded-full bg-gray-200 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            {{ kitchenEquipments.length }}
                        </span>
                    </TabsTrigger>
                </TabsList>

                <!-- ─ Tab: Tecnológico ─────────────────────────────────── -->
                <TabsContent value="computer">
                    <div class="rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                            <Search class="h-4 w-4 flex-shrink-0 text-gray-400" />
                            <Input v-model="searchComputer" placeholder="Buscar por nombre, marca, modelo, código…" class="border-0 shadow-none focus-visible:ring-0 dark:bg-transparent" />
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        <th class="px-4 py-3">Código</th>
                                        <th class="px-4 py-3">Equipo</th>
                                        <th class="px-4 py-3">Marca / Modelo</th>
                                        <th class="px-4 py-3">Serie</th>
                                        <th class="px-4 py-3">Estado</th>
                                        <th class="px-4 py-3">Responsable</th>
                                        <th class="px-4 py-3 text-center">Historial</th>
                                        <th class="px-4 py-3 text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                                    <tr v-if="filteredComputer.length === 0">
                                        <td colspan="8" class="px-4 py-14 text-center text-gray-400">
                                            <Monitor class="mx-auto mb-2 h-8 w-8 opacity-30" />
                                            <p class="text-sm">No hay equipos tecnológicos registrados</p>
                                        </td>
                                    </tr>
                                    <tr v-for="item in filteredComputer" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ item.code || '—' }}</td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-900 dark:text-white">{{ item.name }}</p>
                                            <p v-if="item.presentation" class="text-xs text-gray-400">{{ item.presentation }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                            {{ [item.brand, item.model].filter(Boolean).join(' · ') || '—' }}
                                        </td>
                                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ item.series || '—' }}</td>
                                        <td class="px-4 py-3">
                                            <span :class="['inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold', statusInfo(item.status).cls]">
                                                {{ statusInfo(item.status).label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span v-if="item.responsible" class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                                                <CheckCircle2 class="h-3.5 w-3.5 text-green-500" />{{ item.responsible.name }}
                                            </span>
                                            <span v-else class="flex items-center gap-1 text-xs text-gray-400">
                                                <AlertTriangle class="h-3.5 w-3.5 text-amber-400" /> Sin asignar
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button
                                                class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 transition-colors hover:bg-red-50 hover:text-red-600 dark:bg-gray-700 dark:text-gray-300"
                                                @click="openHistory(item, 'computer')"
                                            >
                                                <History class="h-3 w-3" /> {{ item.histories_count }}
                                            </button>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-center gap-1">
                                                <button class="rounded-md p-1.5 text-gray-400 transition-colors hover:bg-blue-50 hover:text-blue-600" title="Editar" @click="openEdit(item, 'computer')">
                                                    <Pencil class="h-4 w-4" />
                                                </button>
                                                <button class="rounded-md p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600" title="Eliminar" @click="confirmDelete(item, 'computer')">
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </TabsContent>

                <!-- ─ Tab: Menaje ──────────────────────────────────────── -->
                <TabsContent value="kitchen">
                    <div class="rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                            <Search class="h-4 w-4 flex-shrink-0 text-gray-400" />
                            <Input v-model="searchKitchen" placeholder="Buscar por nombre, marca, modelo, código…" class="border-0 shadow-none focus-visible:ring-0 dark:bg-transparent" />
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        <th class="px-4 py-3">Código</th>
                                        <th class="px-4 py-3">Equipo</th>
                                        <th class="px-4 py-3">Marca / Modelo</th>
                                        <th class="px-4 py-3">Serie</th>
                                        <th class="px-4 py-3">Estado</th>
                                        <th class="px-4 py-3">Responsable</th>
                                        <th class="px-4 py-3 text-center">Historial</th>
                                        <th class="px-4 py-3 text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                                    <tr v-if="filteredKitchen.length === 0">
                                        <td colspan="8" class="px-4 py-14 text-center text-gray-400">
                                            <UtensilsCrossed class="mx-auto mb-2 h-8 w-8 opacity-30" />
                                            <p class="text-sm">No hay equipos de menaje registrados</p>
                                        </td>
                                    </tr>
                                    <tr v-for="item in filteredKitchen" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ item.code || '—' }}</td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-900 dark:text-white">{{ item.name }}</p>
                                            <p v-if="item.size" class="text-xs text-gray-400">{{ item.size }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                            {{ [item.brand, item.model].filter(Boolean).join(' · ') || '—' }}
                                        </td>
                                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ item.series || '—' }}</td>
                                        <td class="px-4 py-3">
                                            <span :class="['inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold', statusInfo(item.status).cls]">
                                                {{ statusInfo(item.status).label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span v-if="item.responsible" class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                                                <CheckCircle2 class="h-3.5 w-3.5 text-green-500" />{{ item.responsible.name }}
                                            </span>
                                            <span v-else class="flex items-center gap-1 text-xs text-gray-400">
                                                <AlertTriangle class="h-3.5 w-3.5 text-amber-400" /> Sin asignar
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button
                                                class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 transition-colors hover:bg-red-50 hover:text-red-600 dark:bg-gray-700 dark:text-gray-300"
                                                @click="openHistory(item, 'kitchen')"
                                            >
                                                <History class="h-3 w-3" /> {{ item.histories_count }}
                                            </button>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-center gap-1">
                                                <button class="rounded-md p-1.5 text-gray-400 transition-colors hover:bg-blue-50 hover:text-blue-600" title="Editar" @click="openEdit(item, 'kitchen')">
                                                    <Pencil class="h-4 w-4" />
                                                </button>
                                                <button class="rounded-md p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600" title="Eliminar" @click="confirmDelete(item, 'kitchen')">
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>

    <!-- ══ MODAL: Create / Edit ════════════════════════════════════════════ -->
    <Dialog v-model:open="showForm">
        <DialogScrollContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <component :is="form.type === 'computer' ? Laptop : UtensilsCrossed" class="h-5 w-5 text-red-600" />
                    {{ editTarget ? 'Editar' : 'Nuevo' }} equipo {{ form.type === 'computer' ? 'tecnológico' : 'de menaje' }}
                </DialogTitle>
            </DialogHeader>

            <div class="grid gap-4 py-2">
                <!-- Type toggle (solo al crear) -->
                <div v-if="!editTarget" class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        :class="['flex items-center justify-center gap-2 rounded-lg border-2 py-2.5 text-sm font-medium transition-colors',
                            form.type === 'computer' ? 'border-red-600 bg-red-50 text-red-700' : 'border-gray-200 text-gray-500 hover:border-gray-300']"
                        @click="form.type = 'computer'"
                    >
                        <Laptop class="h-4 w-4" /> Tecnológico
                    </button>
                    <button
                        type="button"
                        :class="['flex items-center justify-center gap-2 rounded-lg border-2 py-2.5 text-sm font-medium transition-colors',
                            form.type === 'kitchen' ? 'border-red-600 bg-red-50 text-red-700' : 'border-gray-200 text-gray-500 hover:border-gray-300']"
                        @click="form.type = 'kitchen'"
                    >
                        <UtensilsCrossed class="h-4 w-4" /> Menaje
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <!-- Nombre -->
                    <div class="col-span-2 grid gap-1.5">
                        <Label>Nombre <span class="text-red-500">*</span></Label>
                        <Input v-model="form.name" placeholder="Nombre del equipo" />
                        <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>
                    <!-- Código / Serie -->
                    <div class="grid gap-1.5">
                        <Label>Código interno</Label>
                        <Input v-model="form.code" placeholder="Ej. TEC-001" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>N° de serie</Label>
                        <Input v-model="form.series" placeholder="Ej. SN-123456" />
                    </div>
                    <!-- Marca / Modelo -->
                    <div class="grid gap-1.5">
                        <Label>Marca</Label>
                        <Input v-model="form.brand" placeholder="Ej. HP, Oster" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Modelo</Label>
                        <Input v-model="form.model" placeholder="Ej. ProBook 440" />
                    </div>
                    <!-- Color -->
                    <div class="grid gap-1.5">
                        <Label>Color</Label>
                        <Input v-model="form.color" placeholder="Ej. Negro" />
                    </div>
                    <!-- Tecnológico exclusive -->
                    <div v-if="form.type === 'computer'" class="grid gap-1.5">
                        <Label>Presentación</Label>
                        <Input v-model="form.presentation" placeholder="Ej. Laptop, Torre, All-in-One" />
                    </div>
                    <!-- Menaje exclusive -->
                    <template v-if="form.type === 'kitchen'">
                        <div class="grid gap-1.5">
                            <Label>Tamaño / Capacidad</Label>
                            <Input v-model="form.size" placeholder="Ej. 50 Litros" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Tipo de corriente</Label>
                            <Input v-model="form.current_type" placeholder="Ej. 220V" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Instructivo</Label>
                            <Input v-model="form.manual" placeholder="Físico / Digital" />
                        </div>
                    </template>
                    <!-- Descripción -->
                    <div class="col-span-2 grid gap-1.5">
                        <Label>Descripción</Label>
                        <Textarea v-model="form.description" :rows="2" placeholder="Notas adicionales…" />
                    </div>
                    <!-- Estado / Responsable -->
                    <div class="grid gap-1.5">
                        <Label>Estado</Label>
                        <Select v-model="form.status">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in STATUSES" :key="s.value" :value="s.value">{{ s.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Responsable</Label>
                        <Select v-model="form.responsible_id">
                            <SelectTrigger><SelectValue placeholder="Sin asignar" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Sin asignar</SelectItem>
                                <SelectItem v-for="s in staff" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="showForm = false">Cancelar</Button>
                <Button class="bg-red-600 hover:bg-red-700" :disabled="form.processing" @click="submitForm">
                    {{ editTarget ? 'Guardar cambios' : 'Registrar equipo' }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>

    <!-- ══ SHEET: Historial ════════════════════════════════════════════════ -->
    <Sheet v-model:open="showHistory">
        <SheetContent class="flex w-full flex-col gap-0 p-0 sm:max-w-md" side="right">
            <SheetHeader class="border-b px-5 py-4">
                <SheetTitle class="flex items-center gap-2">
                    <History class="h-5 w-5 text-red-600" /> Historial del equipo
                </SheetTitle>
                <p class="mt-0.5 truncate text-sm text-gray-500 dark:text-gray-400">{{ historyItem?.name }}</p>
            </SheetHeader>

            <!-- Add entry form -->
            <div class="border-b bg-gray-50 px-5 py-4 dark:bg-gray-800/60">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Nueva entrada</p>
                <div class="grid gap-2.5">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="grid gap-1">
                            <Label class="text-xs">Acción</Label>
                            <Select v-model="historyForm.action">
                                <SelectTrigger class="h-8 text-xs"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="a in ACTIONS" :key="a" :value="a" class="text-xs">{{ a }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1">
                            <Label class="text-xs">Cambiar estado</Label>
                            <Select v-model="historyForm.status">
                                <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Sin cambio" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Sin cambio</SelectItem>
                                    <SelectItem v-for="s in STATUSES" :key="s.value" :value="s.value" class="text-xs">{{ s.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-xs">Responsable (opcional)</Label>
                        <Select v-model="historyForm.staff_id">
                            <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Sin cambio" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none" class="text-xs">Sin cambio</SelectItem>
                                <SelectItem v-for="s in staff" :key="s.id" :value="String(s.id)" class="text-xs">{{ s.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Textarea v-model="historyForm.notes" :rows="2" placeholder="Notas u observaciones…" class="text-xs" />
                    <Button class="h-8 bg-red-600 text-xs hover:bg-red-700" :disabled="historyProcessing" @click="submitHistory">
                        <Plus class="mr-1 h-3.5 w-3.5" /> Agregar entrada
                    </Button>
                </div>
            </div>

            <!-- Timeline -->
            <div class="flex-1 overflow-y-auto px-5 py-4">
                <div v-if="historyLoading" class="flex items-center justify-center py-12 text-gray-400">
                    <Clock class="mr-2 h-4 w-4 animate-spin" /> Cargando…
                </div>
                <div v-else-if="historyList.length === 0" class="flex flex-col items-center justify-center gap-2 py-12 text-gray-400">
                    <ClipboardList class="h-8 w-8 opacity-30" />
                    <p class="text-sm">Sin registros aún</p>
                </div>
                <div v-else class="relative pl-5">
                    <div class="absolute top-2 bottom-2 left-2 w-px bg-gray-200 dark:bg-gray-600" />
                    <div v-for="entry in historyList" :key="entry.id" class="relative mb-4">
                        <div :class="['absolute -left-[13px] top-1.5 h-2.5 w-2.5 rounded-full border-2 border-white dark:border-gray-900', ACTION_COLORS[entry.action]?.split(' ')[0]?.replace('-100', '-400') ?? 'bg-gray-400']" />
                        <div class="rounded-lg border border-gray-100 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <div class="flex items-start justify-between gap-2">
                                <span :class="['inline-block rounded-full px-2 py-0.5 text-[10px] font-bold', ACTION_COLORS[entry.action] ?? 'bg-gray-100 text-gray-600']">
                                    {{ entry.action }}
                                </span>
                                <time class="flex-shrink-0 text-[10px] text-gray-400">{{ fmtDate(entry.created_at) }}</time>
                            </div>
                            <p v-if="entry.notes" class="mt-1.5 text-xs text-gray-700 dark:text-gray-300">{{ entry.notes }}</p>
                            <div class="mt-2 flex flex-wrap gap-3 text-[11px] text-gray-500 dark:text-gray-400">
                                <span v-if="entry.staff" class="flex items-center gap-1">
                                    <CheckCircle2 class="h-3 w-3 text-green-500" /> {{ entry.staff.name }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <Clock class="h-3 w-3" /> por {{ entry.user?.name ?? '—' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>

    <!-- ══ DIALOG: Confirmar eliminación ══════════════════════════════════ -->
    <Dialog :open="!!deleteTarget" @update:open="v => { if (!v) deleteTarget = null }">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-red-600">
                    <Trash2 class="h-5 w-5" /> Eliminar equipo
                </DialogTitle>
            </DialogHeader>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                ¿Estás seguro de eliminar <strong>{{ deleteTarget?.name }}</strong>?
                Se eliminará todo su historial y no se puede deshacer.
            </p>
            <DialogFooter>
                <Button variant="outline" @click="deleteTarget = null">Cancelar</Button>
                <Button variant="destructive" @click="doDelete">Sí, eliminar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
