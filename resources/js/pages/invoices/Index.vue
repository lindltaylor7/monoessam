<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    Box, Building2, ChevronDown, ChevronUp, FileText, Laptop,
    Package, Receipt, Search, UtensilsCrossed,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface InvoiceItem {
    id: number;
    name: string;
    type: string;
    size?: string | null;
    color?: { name: string; hex_code: string } | null;
    brand?: string | null;
    model?: string | null;
    code?: string | null;
    series?: string | null;
    status?: number | null;
    quantity?: number | null;
    unit_price: number | null;
    total_price: number | null;
}

interface UnifiedInvoice {
    id: number;
    source: 'cloth' | 'equipment';
    category: 'epp' | 'cloth' | 'mixed_cloth' | 'computer' | 'kitchen' | 'mixed_equipment';
    invoice_number: string | null;
    document_type: string | null;
    date: string;
    provider: { id: number; name: string } | null;
    business: { id: number; name: string } | null;
    headquarter: { id: number; name: string } | null;
    total_amount: number;
    items_count: number;
    user: { id: number; name: string } | null;
    invoice_image: string | null;
    notes: string | null;
    items: InvoiceItem[];
}

interface MonthlyStat {
    month: string;
    total: number;
    count: number;
}

interface Stats {
    total_invoices: number;
    total_amount: number;
    cloth_count: number;
    cloth_amount: number;
    equipment_count: number;
    equipment_amount: number;
    computer_count: number;
    kitchen_count: number;
}

const props = defineProps<{
    invoices: UnifiedInvoice[];
    stats: Stats;
    monthly: MonthlyStat[];
}>();

// ── Filters ────────────────────────────────────────────────────────────────
const search     = ref('');
const filterType = ref('all');
const sortField  = ref<'date' | 'total_amount'>('date');
const sortAsc    = ref(false);

const CATEGORY_META: Record<string, { label: string; color: string; icon: any }> = {
    epp:             { label: 'EPP',               color: 'bg-violet-100 text-violet-700 border-violet-200', icon: Box },
    cloth:           { label: 'Prendas',            color: 'bg-sky-100 text-sky-700 border-sky-200',         icon: Package },
    mixed_cloth:     { label: 'EPP + Prendas',      color: 'bg-indigo-100 text-indigo-700 border-indigo-200', icon: Package },
    computer:        { label: 'Tecnológico',         color: 'bg-blue-100 text-blue-700 border-blue-200',      icon: Laptop },
    kitchen:         { label: 'Menaje',              color: 'bg-orange-100 text-orange-700 border-orange-200', icon: UtensilsCrossed },
    mixed_equipment: { label: 'Tec. + Menaje',      color: 'bg-amber-100 text-amber-700 border-amber-200',   icon: Laptop },
};

const EQUIP_STATUSES = ['Nuevo', 'Bueno', 'Regular', 'Dañado', 'Baja'];

const filtered = computed(() => {
    let list = props.invoices;

    if (filterType.value !== 'all') {
        if (filterType.value === 'cloth') {
            list = list.filter(i => i.source === 'cloth');
        } else if (filterType.value === 'equipment') {
            list = list.filter(i => i.source === 'equipment');
        } else {
            list = list.filter(i => i.category === filterType.value);
        }
    }

    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        list = list.filter(i =>
            i.invoice_number?.toLowerCase().includes(q) ||
            i.provider?.name.toLowerCase().includes(q) ||
            i.business?.name.toLowerCase().includes(q)
        );
    }

    return [...list].sort((a, b) => {
        const va = sortField.value === 'date' ? a.date : a.total_amount;
        const vb = sortField.value === 'date' ? b.date : b.total_amount;
        const cmp = va < vb ? -1 : va > vb ? 1 : 0;
        return sortAsc.value ? cmp : -cmp;
    });
});

function toggleSort(field: 'date' | 'total_amount') {
    if (sortField.value === field) sortAsc.value = !sortAsc.value;
    else { sortField.value = field; sortAsc.value = false; }
}

// ── Detail modal ───────────────────────────────────────────────────────────
const selectedInvoice = ref<UnifiedInvoice | null>(null);

// ── Monthly chart ──────────────────────────────────────────────────────────
const maxMonthly = computed(() => Math.max(...props.monthly.map(m => m.total), 1));

function fmtMonth(ym: string) {
    const [y, m] = ym.split('-');
    return new Date(Number(y), Number(m) - 1).toLocaleDateString('es-PE', { month: 'short', year: '2-digit' });
}

function fmt(n: number) {
    return n.toLocaleString('es-PE', { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Facturas" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Logística', href: route('logistics') },
            { title: 'Facturas', href: route('invoices.index') },
        ]"
    >
        <div class="flex flex-col gap-6 bg-slate-50/50 p-4 sm:p-6">

            <!-- ── Header ──────────────────────────────────────────────────── -->
            <div class="flex flex-col items-start justify-between gap-3 md:flex-row md:items-center">
                <div>
                    <h1 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        <Receipt class="h-8 w-8 text-indigo-600" />
                        Facturas Unificadas
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">Historial consolidado de compras: EPPs, prendas y equipos.</p>
                </div>
            </div>

            <!-- ── Stats cards ─────────────────────────────────────────────── -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Facturas</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ stats.total_invoices }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">S/. {{ fmt(stats.total_amount) }}</p>
                </div>

                <div class="rounded-2xl border border-violet-100 bg-violet-50/50 p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-wider text-violet-400">EPP / Prendas</p>
                    <p class="mt-1 text-2xl font-black text-violet-900">{{ stats.cloth_count }}</p>
                    <p class="mt-1 text-xs font-semibold text-violet-600">S/. {{ fmt(stats.cloth_amount) }}</p>
                    <div class="mt-2 h-1.5 rounded-full bg-violet-100">
                        <div
                            class="h-1.5 rounded-full bg-violet-500 transition-all"
                            :style="{ width: stats.total_amount > 0 ? (stats.cloth_amount / stats.total_amount * 100) + '%' : '0%' }"
                        />
                    </div>
                </div>

                <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-wider text-blue-400">Tecnológico</p>
                    <p class="mt-1 text-2xl font-black text-blue-900">{{ stats.computer_count }}</p>
                    <p class="mt-1 text-xs font-semibold text-blue-600">facturas con equipos</p>
                    <div class="mt-2 h-1.5 rounded-full bg-blue-100">
                        <div
                            class="h-1.5 rounded-full bg-blue-500 transition-all"
                            :style="{ width: stats.total_invoices > 0 ? (stats.computer_count / stats.total_invoices * 100) + '%' : '0%' }"
                        />
                    </div>
                </div>

                <div class="rounded-2xl border border-orange-100 bg-orange-50/50 p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-wider text-orange-400">Menaje / Cocina</p>
                    <p class="mt-1 text-2xl font-black text-orange-900">{{ stats.kitchen_count }}</p>
                    <p class="mt-1 text-xs font-semibold text-orange-600">facturas con menaje</p>
                    <div class="mt-2 h-1.5 rounded-full bg-orange-100">
                        <div
                            class="h-1.5 rounded-full bg-orange-500 transition-all"
                            :style="{ width: stats.total_invoices > 0 ? (stats.kitchen_count / stats.total_invoices * 100) + '%' : '0%' }"
                        />
                    </div>
                </div>
            </div>

            <!-- ── Monthly bar chart ───────────────────────────────────────── -->
            <div v-if="monthly.length > 0" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <p class="mb-4 text-xs font-black uppercase tracking-wider text-slate-400">Gasto mensual (últimos 6 meses)</p>
                <div class="flex h-28 items-end gap-3">
                    <div
                        v-for="m in monthly"
                        :key="m.month"
                        class="group relative flex flex-1 flex-col items-center gap-1"
                    >
                        <!-- Tooltip -->
                        <div class="absolute -top-8 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg border border-slate-100 bg-white px-2 py-1 text-[10px] font-bold text-slate-700 shadow group-hover:block">
                            S/. {{ fmt(m.total) }}
                        </div>
                        <div
                            class="w-full rounded-t-lg bg-indigo-500 transition-all group-hover:bg-indigo-600"
                            :style="{ height: (m.total / maxMonthly * 100) + '%', minHeight: '4px' }"
                        />
                        <span class="text-[10px] font-semibold text-slate-400">{{ fmtMonth(m.month) }}</span>
                    </div>
                </div>
            </div>

            <!-- ── Filters ─────────────────────────────────────────────────── -->
            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="absolute top-2.5 left-3 h-4 w-4 text-slate-400" />
                    <Input
                        v-model="search"
                        placeholder="Buscar por N° factura, proveedor o empresa..."
                        class="border-slate-200 pl-9 shadow-sm"
                    />
                </div>
                <Select v-model="filterType">
                    <SelectTrigger class="w-52 border-slate-200 bg-white shadow-sm">
                        <SelectValue placeholder="Todos los tipos" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos los tipos</SelectItem>
                        <SelectItem value="cloth">EPP / Prendas</SelectItem>
                        <SelectItem value="equipment">Equipos (todos)</SelectItem>
                        <SelectItem value="computer">Solo Tecnológico</SelectItem>
                        <SelectItem value="kitchen">Solo Menaje</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- ── Table ───────────────────────────────────────────────────── -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow class="bg-slate-50/50 hover:bg-slate-50/50">
                            <TableHead class="text-[10px] font-bold uppercase text-slate-500">Tipo</TableHead>
                            <TableHead class="text-[10px] font-bold uppercase text-slate-500">N° Documento</TableHead>
                            <TableHead
                                class="cursor-pointer select-none text-[10px] font-bold uppercase text-slate-500"
                                @click="toggleSort('date')"
                            >
                                <span class="flex items-center gap-1">
                                    Fecha
                                    <component :is="sortField === 'date' ? (sortAsc ? ChevronUp : ChevronDown) : ChevronDown" class="h-3 w-3" />
                                </span>
                            </TableHead>
                            <TableHead class="text-[10px] font-bold uppercase text-slate-500">Proveedor</TableHead>
                            <TableHead class="text-[10px] font-bold uppercase text-slate-500">
                                <span class="flex items-center gap-1"><Building2 class="h-3 w-3" /> Empresa</span>
                            </TableHead>
                            <TableHead class="text-center text-[10px] font-bold uppercase text-slate-500">Ítems</TableHead>
                            <TableHead class="text-[10px] font-bold uppercase text-slate-500">Registrado por</TableHead>
                            <TableHead
                                class="cursor-pointer select-none text-right text-[10px] font-bold uppercase text-slate-500"
                                @click="toggleSort('total_amount')"
                            >
                                <span class="flex items-center justify-end gap-1">
                                    Total
                                    <component :is="sortField === 'total_amount' ? (sortAsc ? ChevronUp : ChevronDown) : ChevronDown" class="h-3 w-3" />
                                </span>
                            </TableHead>
                            <TableHead class="w-10" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="inv in filtered"
                            :key="`${inv.source}-${inv.id}`"
                            class="group cursor-pointer transition-colors hover:bg-slate-50/60"
                            @click="selectedInvoice = inv"
                        >
                            <TableCell>
                                <Badge
                                    :class="['flex w-fit items-center gap-1 border text-[10px] font-black', CATEGORY_META[inv.category]?.color]"
                                    variant="outline"
                                >
                                    <component :is="CATEGORY_META[inv.category]?.icon" class="h-3 w-3" />
                                    {{ CATEGORY_META[inv.category]?.label }}
                                </Badge>
                            </TableCell>
                            <TableCell class="font-mono text-sm font-bold text-indigo-600">
                                {{ inv.invoice_number || 'S/N' }}
                                <span v-if="inv.document_type" class="ml-1 text-[9px] font-semibold uppercase text-slate-400">
                                    ({{ inv.document_type }})
                                </span>
                            </TableCell>
                            <TableCell class="text-sm text-slate-600">{{ inv.date }}</TableCell>
                            <TableCell class="font-medium text-slate-700">{{ inv.provider?.name || '—' }}</TableCell>
                            <TableCell>
                                <div class="flex flex-col leading-tight">
                                    <span class="text-sm font-semibold text-slate-800">{{ inv.business?.name || '—' }}</span>
                                    <span v-if="inv.headquarter" class="text-xs text-slate-400">{{ inv.headquarter.name }}</span>
                                </div>
                            </TableCell>
                            <TableCell class="text-center">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">
                                    {{ inv.items_count }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-1.5">
                                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-500">
                                        {{ inv.user?.name?.charAt(0) || '?' }}
                                    </div>
                                    <span class="text-xs text-slate-500">{{ inv.user?.name || 'Sistema' }}</span>
                                </div>
                            </TableCell>
                            <TableCell class="text-right font-black text-slate-900">
                                S/. {{ fmt(inv.total_amount) }}
                            </TableCell>
                            <TableCell>
                                <FileText class="h-4 w-4 text-slate-300 transition-colors group-hover:text-indigo-500" />
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="filtered.length === 0">
                            <TableCell colspan="9" class="py-16 text-center">
                                <Receipt class="mx-auto mb-2 h-8 w-8 text-slate-200" />
                                <p class="text-sm text-slate-400 italic">No se encontraron facturas.</p>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div v-if="filtered.length > 0" class="border-t bg-slate-50/50 px-4 py-2 text-right text-xs text-slate-500">
                    Mostrando <span class="font-bold text-slate-700">{{ filtered.length }}</span> de {{ invoices.length }} facturas
                    &nbsp;·&nbsp;
                    Total filtrado: <span class="font-black text-indigo-700">S/. {{ fmt(filtered.reduce((a, i) => a + i.total_amount, 0)) }}</span>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- ══ DETAIL MODAL ═══════════════════════════════════════════════════════ -->
    <Dialog :open="!!selectedInvoice" @update:open="v => { if (!v) selectedInvoice = null }">
        <DialogContent class="flex max-h-[90vh] flex-col overflow-hidden p-0 sm:max-w-[700px]">
            <DialogHeader class="border-b bg-slate-50/50 p-6">
                <div class="flex items-start gap-3">
                    <component
                        :is="CATEGORY_META[selectedInvoice?.category ?? 'cloth']?.icon"
                        class="mt-0.5 h-5 w-5 text-indigo-600"
                    />
                    <div>
                        <DialogTitle class="text-xl font-black text-slate-900">
                            Factura {{ selectedInvoice?.invoice_number || 'S/N' }}
                            <span v-if="selectedInvoice?.document_type" class="ml-2 text-sm font-semibold text-slate-400 capitalize">
                                ({{ selectedInvoice.document_type }})
                            </span>
                        </DialogTitle>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{ selectedInvoice?.provider?.name || 'Sin proveedor' }}
                            &nbsp;·&nbsp;{{ selectedInvoice?.date }}
                            &nbsp;·&nbsp;Reg. por {{ selectedInvoice?.user?.name || 'Sistema' }}
                        </p>
                    </div>
                    <Badge
                        v-if="selectedInvoice"
                        :class="['ml-auto flex shrink-0 items-center gap-1 border text-[10px] font-black', CATEGORY_META[selectedInvoice.category]?.color]"
                        variant="outline"
                    >
                        {{ CATEGORY_META[selectedInvoice.category]?.label }}
                    </Badge>
                </div>
            </DialogHeader>

            <div class="flex-1 space-y-5 overflow-y-auto p-6">
                <!-- Info cards -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-3">
                        <p class="mb-1.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Empresa / Sede</p>
                        <p class="flex items-center gap-1.5 text-sm font-bold text-slate-800">
                            <Building2 class="h-3.5 w-3.5 text-slate-400" />
                            {{ selectedInvoice?.business?.name || '—' }}
                        </p>
                        <p v-if="selectedInvoice?.headquarter" class="mt-0.5 pl-5 text-xs text-slate-500">
                            {{ selectedInvoice.headquarter.name }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-indigo-100 bg-indigo-50/30 p-3">
                        <p class="mb-1.5 text-[10px] font-black uppercase tracking-wider text-indigo-400">Notas</p>
                        <p class="text-xs leading-relaxed text-slate-600">
                            {{ selectedInvoice?.notes || 'Sin observaciones.' }}
                        </p>
                    </div>
                </div>

                <!-- Items -->
                <div>
                    <p class="mb-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Ítems</p>
                    <div class="overflow-hidden rounded-xl border border-slate-100">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50">
                                <tr class="text-left text-[10px] font-black uppercase text-slate-400">
                                    <th class="px-3 py-2">Ítem</th>
                                    <th class="px-3 py-2">Detalle</th>
                                    <template v-if="selectedInvoice?.source === 'cloth'">
                                        <th class="px-3 py-2 text-center">Cant.</th>
                                        <th class="px-3 py-2 text-right">P. Unit.</th>
                                        <th class="px-3 py-2 text-right">Total</th>
                                    </template>
                                    <template v-else>
                                        <th class="px-3 py-2">Código</th>
                                        <th class="px-3 py-2">N° Serie</th>
                                        <th class="px-3 py-2 text-center">Estado</th>
                                        <th class="px-3 py-2 text-right">P. Unit.</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="item in selectedInvoice?.items"
                                    :key="item.id"
                                    class="hover:bg-slate-50/50"
                                >
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-2">
                                            <component
                                                :is="item.type === 'computer' ? Laptop : item.type === 'kitchen' ? UtensilsCrossed : item.type === 'epp' ? Box : Package"
                                                class="h-3.5 w-3.5 shrink-0 text-slate-400"
                                            />
                                            <span class="font-semibold text-slate-800">{{ item.name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-500">
                                        <template v-if="selectedInvoice?.source === 'cloth'">
                                            <span v-if="item.size">Talla: {{ item.size }}</span>
                                            <span v-if="item.color" class="ml-2 flex items-center gap-1">
                                                <span
                                                    class="inline-block h-2.5 w-2.5 rounded-full border border-slate-200"
                                                    :style="{ backgroundColor: item.color.hex_code }"
                                                />
                                                {{ item.color.name }}
                                            </span>
                                        </template>
                                        <template v-else>
                                            {{ [item.brand, item.model].filter(Boolean).join(' · ') || '—' }}
                                        </template>
                                    </td>
                                    <template v-if="selectedInvoice?.source === 'cloth'">
                                        <td class="px-3 py-2 text-center font-bold text-slate-700">{{ item.quantity }}</td>
                                        <td class="px-3 py-2 text-right text-slate-500">S/. {{ fmt(item.unit_price ?? 0) }}</td>
                                        <td class="px-3 py-2 text-right font-black text-indigo-700">S/. {{ fmt(item.total_price ?? 0) }}</td>
                                    </template>
                                    <template v-else>
                                        <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ item.code || '—' }}</td>
                                        <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ item.series || '—' }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                                                {{ EQUIP_STATUSES[item.status ?? 0] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-right font-black text-indigo-700">
                                            {{ item.unit_price !== null ? 'S/. ' + fmt(item.unit_price) : '—' }}
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Total row -->
                <div class="flex items-center justify-between rounded-2xl border border-indigo-100 bg-indigo-50/40 px-5 py-3">
                    <span class="text-xs font-black uppercase tracking-wider text-indigo-600">Total Factura</span>
                    <span class="font-mono text-xl font-black text-indigo-700">
                        S/. {{ fmt(selectedInvoice?.total_amount ?? 0) }}
                    </span>
                </div>

                <!-- Evidence -->
                <div v-if="selectedInvoice?.invoice_image" class="flex items-center justify-between rounded-xl border border-slate-100 p-3">
                    <span class="text-xs font-semibold text-slate-500">Evidencia adjunta</span>
                    <a
                        :href="selectedInvoice.invoice_image"
                        target="_blank"
                        class="flex items-center gap-1.5 text-sm font-bold text-indigo-600 hover:text-indigo-800"
                    >
                        <FileText class="h-4 w-4" /> Ver documento
                    </a>
                </div>
            </div>

            <div class="border-t bg-slate-50/50 p-4">
                <Button variant="outline" class="w-full font-bold" @click="selectedInvoice = null">Cerrar</Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
