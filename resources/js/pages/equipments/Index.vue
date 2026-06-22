<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    CheckCircle2,
    ClipboardList,
    Clock,
    FileText,
    History,
    Laptop,
    Monitor,
    Pencil,
    Plus,
    Search,
    Trash2,
    Truck,
    UtensilsCrossed,
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
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

// ── Types ──────────────────────────────────────────────────────────────────
interface StaffRef {
    id: number;
    name: string;
}

interface HQRef {
    id: number;
    name: string;
    business: { id: number; name: string } | null;
}

interface ComputerEquipment {
    id: number;
    name: string;
    brand: string | null;
    model: string | null;
    description: string | null;
    presentation: string | null;
    color: string | null;
    series: string | null;
    code: string | null;
    status: number;
    quantity: number;
    responsible_id: number | null;
    responsible: StaffRef | null;
    storage_headquarter_id: number | null;
    storage_headquarter: HQRef | null;
    histories_count: number;
}

interface KitchenEquipment {
    id: number;
    name: string;
    brand: string | null;
    model: string | null;
    size: string | null;
    description: string | null;
    color: string | null;
    current_type: string | null;
    series: string | null;
    manual: string | null;
    code: string | null;
    status: number;
    quantity: number;
    responsible_id: number | null;
    responsible: StaffRef | null;
    storage_headquarter_id: number | null;
    storage_headquarter: HQRef | null;
    histories_count: number;
}

interface HistoryEntry {
    id: number;
    action: string;
    notes: string | null;
    staff: StaffRef | null;
    user: StaffRef | null;
    created_at: string;
}

interface HQRef {
    id: number;
    name: string;
    business: { id: number; name: string } | null;
}

interface ProviderRef {
    id: number;
    name: string;
}
interface BusinessRef {
    id: number;
    name: string;
}

const props = defineProps<{
    computerEquipments: ComputerEquipment[];
    kitchenEquipments: KitchenEquipment[];
    staff: StaffRef[];
    headquarters: HQRef[];
    businesses: BusinessRef[];
    equipmentProviders: ProviderRef[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Equipos', href: '/equipments' }];

// ── Constants ──────────────────────────────────────────────────────────────
const STATUSES = [
    { value: '0', label: 'Nuevo', cls: 'bg-blue-100 text-blue-700 border-blue-200' },
    { value: '1', label: 'Bueno', cls: 'bg-green-100 text-green-700 border-green-200' },
    { value: '2', label: 'Regular', cls: 'bg-yellow-100 text-yellow-700 border-yellow-200' },
    { value: '3', label: 'Dañado', cls: 'bg-red-100 text-red-700 border-red-200' },
    { value: '4', label: 'Baja', cls: 'bg-gray-100 text-gray-600 border-gray-200' },
];

const ACTIONS = ['Registro', 'Asignación', 'Mantenimiento', 'Reparación', 'Transferencia', 'Daño', 'Baja', 'Observación'];

const ACTION_COLORS: Record<string, string> = {
    Registro: 'bg-blue-100 text-blue-700',
    Asignación: 'bg-purple-100 text-purple-700',
    Mantenimiento: 'bg-yellow-100 text-yellow-700',
    Reparación: 'bg-orange-100 text-orange-700',
    Transferencia: 'bg-cyan-100 text-cyan-700',
    Daño: 'bg-red-100 text-red-700',
    Baja: 'bg-gray-100 text-gray-600',
    Observación: 'bg-green-100 text-green-700',
};

function statusInfo(val: number) {
    return STATUSES.find((s) => s.value === String(val)) ?? STATUSES[0];
}

// ── State ──────────────────────────────────────────────────────────────────
const activeTab = ref<'computer' | 'kitchen'>('computer');
const searchComputer = ref('');
const searchKitchen = ref('');

// Form modal
const showForm = ref(false);
const editTarget = ref<(ComputerEquipment | KitchenEquipment) | null>(null);

const form = useForm({
    type: 'computer' as 'computer' | 'kitchen',
    name: '',
    brand: '',
    model: '',
    description: '',
    color: '',
    series: '',
    code: '',
    presentation: '',
    size: '',
    current_type: '',
    manual: '',
    status: '0',
    quantity: 1,
    responsible_id: 'none' as string | number,
    storage_headquarter_id: 'none' as string | number,
});

// History sheet
const showHistory = ref(false);
const historyItem = ref<{ id: number; name: string; type: string } | null>(null);
const historyList = ref<HistoryEntry[]>([]);
const historyLoading = ref(false);

const historyForm = reactive({ action: 'Observación', notes: '', staff_id: 'none', status: 'none' });
const historyProcessing = ref(false);

// Delete confirm
const deleteTarget = ref<{ id: number; type: string; name: string } | null>(null);

// ── Filtered lists ─────────────────────────────────────────────────────────
const filteredComputer = computed(() => {
    const q = searchComputer.value.toLowerCase();
    return q
        ? props.computerEquipments.filter((e) => [e.name, e.brand, e.model, e.code, e.series].some((f) => f?.toLowerCase().includes(q)))
        : props.computerEquipments;
});

const filteredKitchen = computed(() => {
    const q = searchKitchen.value.toLowerCase();
    return q
        ? props.kitchenEquipments.filter((e) => [e.name, e.brand, e.model, e.code, e.series].some((f) => f?.toLowerCase().includes(q)))
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
    form.type = type;
    form.name = item.name ?? '';
    form.brand = item.brand ?? '';
    form.model = item.model ?? '';
    form.description = item.description ?? '';
    form.color = item.color ?? '';
    form.series = item.series ?? '';
    form.code = item.code ?? '';
    form.status = String(item.status ?? 0);
    form.quantity = item.quantity ?? 1;
    form.responsible_id = item.responsible_id ? String(item.responsible_id) : 'none';
    form.storage_headquarter_id = item.storage_headquarter_id ? String(item.storage_headquarter_id) : 'none';
    if (type === 'computer') {
        form.presentation = (item as ComputerEquipment).presentation ?? '';
        form.size = '';
        form.current_type = '';
        form.manual = '';
    } else {
        const k = item as KitchenEquipment;
        form.size = k.size ?? '';
        form.current_type = k.current_type ?? '';
        form.manual = k.manual ?? '';
        form.presentation = '';
    }
    showForm.value = true;
}

function submitForm() {
    if (form.responsible_id === 'none') form.responsible_id = '';
    if (form.storage_headquarter_id === 'none') form.storage_headquarter_id = '';
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        },
    };
    if (editTarget.value) {
        form.put(route('equipments.update', { type: form.type, id: editTarget.value.id }), opts);
    } else {
        form.post(route('equipments.store'), opts);
    }
}

// ── History ────────────────────────────────────────────────────────────────
function resetHistoryForm() {
    historyForm.action = 'Observación';
    historyForm.notes = '';
    historyForm.staff_id = 'none';
    historyForm.status = 'none';
}

async function openHistory(item: { id: number; name: string }, type: string) {
    historyItem.value = { id: item.id, name: item.name, type };
    historyList.value = [];
    historyLoading.value = true;
    showHistory.value = true;
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
            action: historyForm.action,
            notes: historyForm.notes || null,
            staff_id: historyForm.staff_id === 'none' ? null : historyForm.staff_id,
            status: historyForm.status === 'none' ? null : historyForm.status,
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
        onSuccess: () => {
            deleteTarget.value = null;
        },
    });
}

// ── Equipment Invoice ──────────────────────────────────────────────────────
const showInvoiceModal = ref(false);
const showNewProviderForm = ref(false);
const newProviderName = ref('');
const localEquipmentProviders = ref([...props.equipmentProviders]);

function saveNewProvider() {
    if (!newProviderName.value.trim()) return;
    router.post(
        route('equipments.providers.store'),
        { name: newProviderName.value.trim() },
        {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page.props as any).flash?.newEquipmentProvider;
                if (flash) {
                    localEquipmentProviders.value.push(flash);
                    invoiceForm.value.provider_id = String(flash.id);
                }
                showNewProviderForm.value = false;
                newProviderName.value = '';
            },
        },
    );
}

const invoiceForm = ref({
    business_id: '',
    provider_id: '',
    document_type: 'factura' as string,
    invoice_number: '',
    date: new Date().toISOString().split('T')[0],
    notes: '',
    invoice_image: null as File | null,
    items: [
        {
            type: 'computer' as 'computer' | 'kitchen',
            name: '',
            brand: '',
            model: '',
            code: '',
            series: '',
            color: '',
            status: '0',
            unit_price: 0,
            quantity: 1,
        },
    ],
});

function addInvoiceItem() {
    invoiceForm.value.items.push({
        type: activeTab.value,
        name: '',
        brand: '',
        model: '',
        code: '',
        series: '',
        color: '',
        status: '0',
        unit_price: 0,
        quantity: 1,
    });
}

function removeInvoiceItem(index: number) {
    if (invoiceForm.value.items.length > 1) invoiceForm.value.items.splice(index, 1);
}

function handleInvoiceImageUpload(e: Event) {
    const target = e.target as HTMLInputElement;
    invoiceForm.value.invoice_image = target.files?.[0] || null;
}

const invoiceSubtotal = computed(() => {
    const total = invoiceForm.value.items.reduce((acc, i) => acc + Number(i.unit_price) * Number(i.quantity), 0);
    return invoiceForm.value.document_type === 'factura' ? total / 1.18 : total;
});

const invoiceIgv = computed(() => {
    const total = invoiceForm.value.items.reduce((acc, i) => acc + Number(i.unit_price) * Number(i.quantity), 0);
    return total - invoiceSubtotal.value;
});

const invoiceTotal = computed(() => invoiceForm.value.items.reduce((acc, i) => acc + Number(i.unit_price) * Number(i.quantity), 0));

const isInvoiceSubmitDisabled = computed(
    () => !invoiceForm.value.date || invoiceForm.value.items.some((i) => !i.name.trim() || Number(i.unit_price) < 0),
);

function resetInvoiceForm() {
    invoiceForm.value = {
        business_id: '',
        provider_id: '',
        document_type: 'factura',
        invoice_number: '',
        date: new Date().toISOString().split('T')[0],
        notes: '',
        invoice_image: null,
        items: [{ type: activeTab.value, name: '', brand: '', model: '', code: '', series: '', color: '', status: '0', unit_price: 0, quantity: 1 }],
    };
}

function submitInvoice() {
    if (isInvoiceSubmitDisabled.value) return;

    router.post(
        route('equipments.invoice.store'),
        {
            ...invoiceForm.value,
            items: invoiceForm.value.items.map((i) => ({ ...i, unit_price: Number(i.unit_price) })),
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                showInvoiceModal.value = false;
                resetInvoiceForm();
            },
            onError: (e) => {
                console.error(e);
                alert('Error al guardar la factura. Revise los campos.');
            },
        },
    );
}

// ── Helpers ────────────────────────────────────────────────────────────────
function fmtDate(d: string) {
    return new Date(d).toLocaleDateString('es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
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
                    <h1 class="text-2xl font-semibold tracking-tight">Gestión de Equipos</h1>
                    <p class="text-muted-foreground mt-0.5 text-sm">Tecnológicos y de menaje · Estado, responsable e historial</p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="router.visit(route('equipment-dispatches.index'))">
                        <Truck class="mr-1.5 h-4 w-4" /> Despachos
                    </Button>

                    <Button variant="outline" class="gap-2 border-indigo-200 text-indigo-600 hover:bg-indigo-50" @click="showInvoiceModal = true">
                        <FileText class="h-4 w-4" /> Ingresar con Factura
                    </Button>
                    <Button class="bg-red-600 hover:bg-red-700" @click="openCreate"> <Plus class="mr-1.5 h-4 w-4" /> Nuevo equipo </Button>
                </div>
            </div>

            <!-- ── Tabs ────────────────────────────────────────────────── -->
            <Tabs v-model="activeTab">
                <TabsList class="mb-1">
                    <TabsTrigger value="computer" class="flex items-center gap-1.5">
                        <Laptop class="h-4 w-4" /> Tecnológico
                        <span class="bg-muted text-muted-foreground ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-bold">
                            {{ computerEquipments.length }}
                        </span>
                    </TabsTrigger>
                    <TabsTrigger value="kitchen" class="flex items-center gap-1.5">
                        <UtensilsCrossed class="h-4 w-4" /> Menaje / Cocina
                        <span class="bg-muted text-muted-foreground ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-bold">
                            {{ kitchenEquipments.length }}
                        </span>
                    </TabsTrigger>
                </TabsList>

                <!-- ─ Tab: Tecnológico ─────────────────────────────────── -->
                <TabsContent value="computer">
                    <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                        <div class="flex items-center gap-2 border-b px-4 py-3">
                            <Search class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                            <Input
                                v-model="searchComputer"
                                placeholder="Buscar por nombre, marca, modelo, código…"
                                class="border-0 shadow-none focus-visible:ring-0"
                            />
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full table-auto border-collapse">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold">Código</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold">Equipo</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold">Marca / Modelo</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold">Serie</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold">Cantidad</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold">Estado</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold">Responsable</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold">Historial</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="filteredComputer.length === 0">
                                        <td colspan="9" class="text-muted-foreground py-14 text-center">
                                            <Monitor class="mx-auto mb-2 h-8 w-8 opacity-30" />
                                            <p class="text-sm">No hay equipos tecnológicos registrados</p>
                                        </td>
                                    </tr>
                                    <tr v-for="item in filteredComputer" :key="item.id" class="hover:bg-muted/30 border-t transition">
                                        <td class="px-4 py-3">
                                            <span class="bg-muted rounded px-2 py-0.5 font-mono text-xs">{{ item.code || '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="text-sm font-semibold">{{ item.name }}</p>
                                            <p v-if="item.presentation" class="text-muted-foreground text-xs">{{ item.presentation }}</p>
                                        </td>
                                        <td class="text-muted-foreground px-4 py-3 text-sm">
                                            {{ [item.brand, item.model].filter(Boolean).join(' · ') || '—' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-muted-foreground font-mono text-xs">{{ item.series || '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span
                                                class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 font-mono text-xs font-semibold text-slate-700"
                                            >
                                                {{ item.quantity ?? 1 }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                :class="[
                                                    'inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                                                    statusInfo(item.status).cls,
                                                ]"
                                            >
                                                {{ statusInfo(item.status).label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span v-if="item.responsible" class="flex items-center gap-1.5 text-sm">
                                                <CheckCircle2 class="h-3.5 w-3.5 text-green-500" />{{ item.responsible.name }}
                                            </span>
                                            <span v-else class="text-muted-foreground flex items-center gap-1 text-xs">
                                                <AlertTriangle class="h-3.5 w-3.5 text-amber-400" /> Sin asignar
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button
                                                class="bg-muted text-muted-foreground inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium transition-colors hover:bg-red-50 hover:text-red-600"
                                                @click="openHistory(item, 'computer')"
                                            >
                                                <History class="h-3 w-3" /> {{ item.histories_count }}
                                            </button>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-center gap-1">
                                                <TooltipProvider
                                                    ><Tooltip>
                                                        <TooltipTrigger as-child>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                class="h-8 w-8 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                                                                @click="openEdit(item, 'computer')"
                                                            >
                                                                <Pencil class="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>Editar</TooltipContent>
                                                    </Tooltip></TooltipProvider
                                                >
                                                <TooltipProvider
                                                    ><Tooltip>
                                                        <TooltipTrigger as-child>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                class="h-8 w-8 text-red-500 hover:bg-red-50 hover:text-red-600"
                                                                @click="confirmDelete(item, 'computer')"
                                                            >
                                                                <Trash2 class="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>Eliminar</TooltipContent>
                                                    </Tooltip></TooltipProvider
                                                >
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
                    <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                        <div class="flex items-center gap-2 border-b px-4 py-3">
                            <Search class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                            <Input
                                v-model="searchKitchen"
                                placeholder="Buscar por nombre, marca, modelo, código…"
                                class="border-0 shadow-none focus-visible:ring-0"
                            />
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full table-auto border-collapse">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold">Código</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold">Equipo</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold">Marca / Modelo</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold">Serie</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold">Cantidad</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold">Estado</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold">Responsable</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold">Historial</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="filteredKitchen.length === 0">
                                        <td colspan="9" class="text-muted-foreground py-14 text-center">
                                            <UtensilsCrossed class="mx-auto mb-2 h-8 w-8 opacity-30" />
                                            <p class="text-sm">No hay equipos de menaje registrados</p>
                                        </td>
                                    </tr>
                                    <tr v-for="item in filteredKitchen" :key="item.id" class="hover:bg-muted/30 border-t transition">
                                        <td class="px-4 py-3">
                                            <span class="bg-muted rounded px-2 py-0.5 font-mono text-xs">{{ item.code || '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="text-sm font-semibold">{{ item.name }}</p>
                                            <p v-if="item.size" class="text-muted-foreground text-xs">{{ item.size }}</p>
                                        </td>
                                        <td class="text-muted-foreground px-4 py-3 text-sm">
                                            {{ [item.brand, item.model].filter(Boolean).join(' · ') || '—' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-muted-foreground font-mono text-xs">{{ item.series || '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span
                                                class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 font-mono text-xs font-semibold text-slate-700"
                                            >
                                                {{ item.quantity ?? 1 }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                :class="[
                                                    'inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                                                    statusInfo(item.status).cls,
                                                ]"
                                            >
                                                {{ statusInfo(item.status).label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span v-if="item.responsible" class="flex items-center gap-1.5 text-sm">
                                                <CheckCircle2 class="h-3.5 w-3.5 text-green-500" />{{ item.responsible.name }}
                                            </span>
                                            <span v-else class="text-muted-foreground flex items-center gap-1 text-xs">
                                                <AlertTriangle class="h-3.5 w-3.5 text-amber-400" /> Sin asignar
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button
                                                class="bg-muted text-muted-foreground inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium transition-colors hover:bg-red-50 hover:text-red-600"
                                                @click="openHistory(item, 'kitchen')"
                                            >
                                                <History class="h-3 w-3" /> {{ item.histories_count }}
                                            </button>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-center gap-1">
                                                <TooltipProvider
                                                    ><Tooltip>
                                                        <TooltipTrigger as-child>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                class="h-8 w-8 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                                                                @click="openEdit(item, 'kitchen')"
                                                            >
                                                                <Pencil class="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>Editar</TooltipContent>
                                                    </Tooltip></TooltipProvider
                                                >
                                                <TooltipProvider
                                                    ><Tooltip>
                                                        <TooltipTrigger as-child>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                class="h-8 w-8 text-red-500 hover:bg-red-50 hover:text-red-600"
                                                                @click="confirmDelete(item, 'kitchen')"
                                                            >
                                                                <Trash2 class="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>Eliminar</TooltipContent>
                                                    </Tooltip></TooltipProvider
                                                >
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
                        :class="[
                            'flex items-center justify-center gap-2 rounded-lg border-2 py-2.5 text-sm font-medium transition-colors',
                            form.type === 'computer'
                                ? 'border-red-600 bg-red-50 text-red-700'
                                : 'border-gray-200 text-gray-500 hover:border-gray-300',
                        ]"
                        @click="form.type = 'computer'"
                    >
                        <Laptop class="h-4 w-4" /> Tecnológico
                    </button>
                    <button
                        type="button"
                        :class="[
                            'flex items-center justify-center gap-2 rounded-lg border-2 py-2.5 text-sm font-medium transition-colors',
                            form.type === 'kitchen' ? 'border-red-600 bg-red-50 text-red-700' : 'border-gray-200 text-gray-500 hover:border-gray-300',
                        ]"
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
                    <!-- Cantidad / Estado -->
                    <div class="grid gap-1.5">
                        <Label>Cantidad</Label>
                        <Input type="number" v-model="form.quantity" min="1" step="1" placeholder="1" />
                    </div>
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
                    <div class="grid gap-1.5">
                        <Label>Almacén de Almacenamiento</Label>
                        <Select v-model="form.storage_headquarter_id">
                            <SelectTrigger><SelectValue placeholder="Sin sede asignada" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Sin sede asignada</SelectItem>
                                <SelectItem v-for="hq in headquarters" :key="hq.id" :value="String(hq.id)">
                                    {{ hq.name }}{{ hq.business ? ` · ${hq.business.name}` : '' }}
                                </SelectItem>
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
                <SheetTitle class="flex items-center gap-2"> <History class="h-5 w-5 text-red-600" /> Historial del equipo </SheetTitle>
                <p class="text-muted-foreground mt-0.5 truncate text-sm">{{ historyItem?.name }}</p>
            </SheetHeader>

            <!-- Add entry form -->
            <div class="bg-muted/30 border-b px-5 py-4">
                <p class="text-muted-foreground mb-3 text-xs font-semibold tracking-wide uppercase">Nueva entrada</p>
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
                        <div
                            :class="[
                                'absolute top-1.5 -left-[13px] h-2.5 w-2.5 rounded-full border-2 border-white dark:border-gray-900',
                                ACTION_COLORS[entry.action]?.split(' ')[0]?.replace('-100', '-400') ?? 'bg-gray-400',
                            ]"
                        />
                        <div class="bg-card rounded-lg border p-3 shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <span
                                    :class="[
                                        'inline-block rounded-full px-2 py-0.5 text-[10px] font-bold',
                                        ACTION_COLORS[entry.action] ?? 'bg-gray-100 text-gray-600',
                                    ]"
                                >
                                    {{ entry.action }}
                                </span>
                                <time class="flex-shrink-0 text-[10px] text-gray-400">{{ fmtDate(entry.created_at) }}</time>
                            </div>
                            <p v-if="entry.notes" class="mt-1.5 text-xs text-gray-700 dark:text-gray-300">{{ entry.notes }}</p>
                            <div class="mt-2 flex flex-wrap gap-3 text-[11px] text-gray-500 dark:text-gray-400">
                                <span v-if="entry.staff" class="flex items-center gap-1">
                                    <CheckCircle2 class="h-3 w-3 text-green-500" /> {{ entry.staff.name }}
                                </span>
                                <span class="flex items-center gap-1"> <Clock class="h-3 w-3" /> por {{ entry.user?.name ?? '—' }} </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>

    <!-- ══ DIALOG: Ingresar Equipos con Factura ══════════════════════════ -->
    <Dialog v-model:open="showInvoiceModal">
        <DialogContent class="max-h-[100vh] overflow-y-auto sm:max-w-[1300px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <FileText class="h-5 w-5 text-indigo-600" />
                    Ingresar Equipos con Factura
                </DialogTitle>
            </DialogHeader>

            <div class="grid gap-6 py-4">
                <!-- Invoice header -->
                <div class="grid grid-cols-1 gap-4 rounded-2xl border border-slate-100 bg-slate-50 p-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <Label class="text-xs font-bold text-slate-500 uppercase">Empresa</Label>
                        <Select v-model="invoiceForm.business_id">
                            <SelectTrigger class="bg-white"><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="b in businesses" :key="b.id" :value="String(b.id)">{{ b.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label class="text-xs font-bold text-slate-500 uppercase">Proveedor de Equipos</Label>
                            <button
                                type="button"
                                class="flex items-center gap-1 text-[10px] font-bold text-indigo-600 hover:text-indigo-800"
                                @click="showNewProviderForm = !showNewProviderForm"
                            >
                                <Plus class="h-3 w-3" /> Nuevo
                            </button>
                        </div>
                        <!-- Inline new provider form -->
                        <div v-if="showNewProviderForm" class="flex gap-1.5 rounded-xl border border-indigo-100 bg-indigo-50/50 p-2">
                            <Input v-model="newProviderName" placeholder="Nombre del proveedor" class="h-8 flex-1 bg-white text-xs" />
                            <Button size="sm" class="h-8 bg-indigo-600 px-3 text-xs hover:bg-indigo-700" @click="saveNewProvider"> Guardar </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="h-8 px-2 text-xs"
                                @click="
                                    showNewProviderForm = false;
                                    newProviderName = '';
                                "
                            >
                                ✕
                            </Button>
                        </div>
                        <Select v-model="invoiceForm.provider_id">
                            <SelectTrigger class="bg-white">
                                <SelectValue placeholder="Seleccionar proveedor" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-if="localEquipmentProviders.length === 0" value="__none__" disabled>
                                    Sin proveedores — crea uno arriba
                                </SelectItem>
                                <SelectItem v-for="p in localEquipmentProviders" :key="p.id" :value="String(p.id)">{{ p.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label class="text-xs font-bold text-slate-500 uppercase">Tipo de Documento</Label>
                        <Select v-model="invoiceForm.document_type">
                            <SelectTrigger class="bg-white"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="factura">Factura (Con IGV)</SelectItem>
                                <SelectItem value="boleta">Boleta (Sin IGV)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label class="text-xs font-bold text-slate-500 uppercase">Nº Documento</Label>
                        <Input v-model="invoiceForm.invoice_number" placeholder="Ej: F-001-123" class="bg-white" />
                    </div>
                    <div class="space-y-2">
                        <Label class="text-xs font-bold text-slate-500 uppercase">Fecha</Label>
                        <Input v-model="invoiceForm.date" type="date" class="bg-white" />
                    </div>
                    <div class="space-y-2">
                        <Label class="text-xs font-bold text-slate-500 uppercase">Notas</Label>
                        <Input v-model="invoiceForm.notes" placeholder="Observaciones..." class="bg-white" />
                    </div>
                    <div class="space-y-2">
                        <Label class="text-xs font-bold text-slate-500 uppercase">Evidencia (Opcional)</Label>
                        <Input
                            type="file"
                            @change="handleInvoiceImageUpload"
                            accept="image/*,.pdf"
                            class="bg-white text-xs file:mr-4 file:rounded-full file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-xs file:font-semibold hover:file:bg-slate-200"
                        />
                    </div>
                </div>

                <!-- Items table -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between px-1">
                        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-700">
                            <Laptop class="h-4 w-4 text-indigo-500" /> Equipos a Registrar
                        </h3>
                        <Button
                            @click="addInvoiceItem"
                            size="sm"
                            variant="outline"
                            class="h-8 gap-1.5 border-indigo-200 text-xs font-bold text-indigo-600 hover:bg-indigo-50"
                        >
                            <Plus class="h-3.5 w-3.5" /> Agregar Fila
                        </Button>
                    </div>

                    <div class="overflow-hidden rounded-2xl border shadow-sm">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-slate-50">
                                <tr class="text-left text-[10px] font-black text-slate-400 uppercase">
                                    <th class="px-3 py-3">Tipo</th>
                                    <th class="px-3 py-3">Nombre <span class="text-red-400">*</span></th>
                                    <th class="px-3 py-3">Marca</th>
                                    <th class="px-3 py-3">Modelo</th>
                                    <th class="px-3 py-3">Código</th>
                                    <th class="px-3 py-3">N° Serie</th>
                                    <th class="px-3 py-3">Estado</th>
                                    <th class="w-20 px-3 py-3">Cantidad</th>
                                    <th class="w-28 px-3 py-3">P. Unitario</th>
                                    <th v-if="invoiceForm.document_type === 'factura'" class="w-24 px-3 py-3 text-right">IGV (18%)</th>
                                    <th class="w-28 px-3 py-3 text-right">Total</th>
                                    <th class="w-10 px-3 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(item, index) in invoiceForm.items" :key="index" class="group transition-colors hover:bg-slate-50/50">
                                    <td class="p-2">
                                        <Select v-model="item.type">
                                            <SelectTrigger class="h-9 w-[120px] border-none shadow-none focus:ring-1">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="computer"
                                                    ><span class="flex items-center gap-1.5"
                                                        ><Laptop class="h-3.5 w-3.5" /> Tecnológico</span
                                                    ></SelectItem
                                                >
                                                <SelectItem value="kitchen"
                                                    ><span class="flex items-center gap-1.5"
                                                        ><UtensilsCrossed class="h-3.5 w-3.5" /> Menaje</span
                                                    ></SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </td>
                                    <td class="p-2">
                                        <Input v-model="item.name" placeholder="Nombre..." class="h-9 border-none shadow-none focus:ring-1" />
                                    </td>
                                    <td class="p-2">
                                        <Input v-model="item.brand" placeholder="Marca..." class="h-9 border-none shadow-none focus:ring-1" />
                                    </td>
                                    <td class="p-2">
                                        <Input v-model="item.model" placeholder="Modelo..." class="h-9 border-none shadow-none focus:ring-1" />
                                    </td>
                                    <td class="p-2">
                                        <Input v-model="item.code" placeholder="Código..." class="h-9 border-none shadow-none focus:ring-1" />
                                    </td>
                                    <td class="p-2">
                                        <Input v-model="item.series" placeholder="Serie..." class="h-9 border-none shadow-none focus:ring-1" />
                                    </td>
                                    <td class="p-2">
                                        <Select v-model="item.status">
                                            <SelectTrigger class="h-9 w-[100px] border-none shadow-none focus:ring-1"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="s in STATUSES" :key="s.value" :value="s.value">{{ s.label }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </td>
                                    <td class="p-2">
                                        <Input
                                            type="number"
                                            v-model="item.quantity"
                                            min="1"
                                            step="1"
                                            class="h-9 w-16 border-none text-center shadow-none focus:ring-1"
                                        />
                                    </td>
                                    <td class="p-2">
                                        <div class="relative">
                                            <span class="absolute top-2 left-2 text-xs text-slate-400">S/.</span>
                                            <Input
                                                type="number"
                                                v-model="item.unit_price"
                                                step="0.01"
                                                min="0"
                                                class="h-9 border-none pl-6 font-bold text-indigo-600 shadow-none focus:ring-1"
                                            />
                                        </div>
                                    </td>
                                    <td v-if="invoiceForm.document_type === 'factura'" class="p-2 text-right text-xs font-medium text-indigo-500">
                                        S/.{{
                                            ((Number(item.unit_price) - Number(item.unit_price) / 1.18) * Number(item.quantity)).toLocaleString(
                                                undefined,
                                                { minimumFractionDigits: 2 },
                                            )
                                        }}
                                    </td>
                                    <td class="p-2 text-right text-xs font-bold text-slate-900">
                                        S/.{{
                                            (Number(item.unit_price) * Number(item.quantity)).toLocaleString(undefined, { minimumFractionDigits: 2 })
                                        }}
                                    </td>
                                    <td class="p-2 text-center">
                                        <Button
                                            @click="removeInvoiceItem(index)"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 w-8 rounded-full p-0 text-slate-400 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-rose-50 hover:text-rose-600"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t border-slate-200 bg-slate-50">
                                <tr>
                                    <td :colspan="invoiceForm.document_type === 'factura' ? 9 : 8" rowspan="3" class="px-4 py-3 align-top">
                                        <div class="rounded-xl border border-dashed border-slate-200 bg-white p-3 text-[10px] text-slate-400">
                                            <p class="mb-1 font-bold tracking-widest uppercase">Notas de Cálculo:</p>
                                            <p v-if="invoiceForm.document_type === 'factura'">• Los precios ingresados ya incluyen IGV.</p>
                                            <p v-if="invoiceForm.document_type === 'factura'">• Se extrae el 18% para mostrar la base imponible.</p>
                                            <p v-else>• Las boletas no desglosan impuestos; el total es el monto bruto.</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-right text-[11px] font-bold tracking-wider text-slate-500 uppercase">
                                        Subtotal Gravado
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono font-bold text-slate-700">
                                        S/.{{ invoiceSubtotal.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2 text-right text-[11px] font-bold tracking-wider text-slate-500 uppercase">IGV (18%)</td>
                                    <td class="px-4 py-2 text-right font-mono font-bold text-slate-600">
                                        S/.{{ invoiceIgv.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td></td>
                                </tr>
                                <tr class="bg-indigo-50/50">
                                    <td class="px-4 py-3 text-right text-[12px] font-black tracking-widest text-indigo-900 uppercase">
                                        Total Factura
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="font-mono text-xl font-black text-indigo-700">
                                            S/.{{ invoiceTotal.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                                        </span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <DialogFooter class="-mx-6 mt-4 -mb-6 rounded-b-lg border-t bg-slate-50 p-6">
                <Button
                    variant="ghost"
                    @click="
                        showInvoiceModal = false;
                        resetInvoiceForm();
                    "
                    class="font-bold"
                    >Cancelar</Button
                >
                <Button
                    @click="submitInvoice"
                    :disabled="isInvoiceSubmitDisabled"
                    class="bg-indigo-600 font-bold text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700"
                >
                    Guardar y Registrar Equipos
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ══ DIALOG: Confirmar eliminación ══════════════════════════════════ -->
    <Dialog
        :open="!!deleteTarget"
        @update:open="
            (v) => {
                if (!v) deleteTarget = null;
            }
        "
    >
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-red-600"> <Trash2 class="h-5 w-5" /> Eliminar equipo </DialogTitle>
            </DialogHeader>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                ¿Estás seguro de eliminar <strong>{{ deleteTarget?.name }}</strong
                >? Se eliminará todo su historial y no se puede deshacer.
            </p>
            <DialogFooter>
                <Button variant="outline" @click="deleteTarget = null">Cancelar</Button>
                <Button variant="destructive" @click="doDelete">Sí, eliminar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
