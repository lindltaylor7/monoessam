<script setup lang="ts">
import { Dialog, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    BarChart3, CalendarDays, Check, CheckCircle2, ChevronDown, ChevronUp, Clock, FileSpreadsheet, Loader2,
    Package, RotateCcw, Search, ShoppingBag, Store, UserPlus, X,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';

// ── Types ──────────────────────────────────────────────────────────────────
interface Product {
    id: number;
    name: string;
    marca?: string | null;
    description?: string;
    sku?: string;
    category?: string;
    price: number;
    stock?: number;
    is_active: boolean;
}

interface Mercantil {
    id: number;
    name: string;
    unit?: { id: number; name: string };
}

interface Subdealership {
    id: number;
    name: string;
}

interface Dinner {
    id: number;
    name: string;
    dni: string;
    phone?: string | null;
    subdealership_id?: number | null;
    subdealership?: Subdealership | null;
}

interface Props {
    mercantiles: (Mercantil & { products: Product[] })[];
    sale_types: any[];
    subdealerships: Subdealership[];
}

const props = defineProps<Props>();

// ── Category colours ───────────────────────────────────────────────────────
const PALETTE = [
    { accent: 'bg-violet-500',  ring: 'ring-violet-400',  bg: 'bg-violet-50',  emoji: '🛒' },
    { accent: 'bg-blue-500',    ring: 'ring-blue-400',    bg: 'bg-blue-50',    emoji: '📦' },
    { accent: 'bg-emerald-500', ring: 'ring-emerald-400', bg: 'bg-emerald-50', emoji: '🏷️' },
    { accent: 'bg-amber-500',   ring: 'ring-amber-400',   bg: 'bg-amber-50',   emoji: '⭐' },
    { accent: 'bg-rose-500',    ring: 'ring-rose-400',    bg: 'bg-rose-50',    emoji: '🎁' },
    { accent: 'bg-cyan-500',    ring: 'ring-cyan-400',    bg: 'bg-cyan-50',    emoji: '💎' },
];

const categoryMeta = (() => {
    const map = new Map<string, (typeof PALETTE)[0]>();
    let i = 0;
    return (cat: string) => {
        if (!map.has(cat)) { map.set(cat, PALETTE[i++ % PALETTE.length]); }
        return map.get(cat)!;
    };
})();

// ── State ──────────────────────────────────────────────────────────────────
const mercantilSelected = ref<number>(props.mercantiles[0]?.id ?? 0);
const dateSelected      = ref<string>(new Date().toISOString().split('T')[0]);
const saletypeSelected  = ref<number>(props.sale_types[0]?.id ?? 0);
const activeCategory    = ref<string>('all');
const searchQuery       = ref('');

const PAYMENT_METHODS = [
    { id: 'efectivo',      label: 'Efectivo',      icon: '💵', bgActive: 'bg-emerald-600' },
    { id: 'yape',          label: 'Yape',          icon: '📱', bgActive: 'bg-purple-600' },
    { id: 'plin',          label: 'Plin',          icon: '🟣', bgActive: 'bg-cyan-600' },
    { id: 'tarjeta',       label: 'Tarjeta',       icon: '💳', bgActive: 'bg-indigo-600' },
    { id: 'transferencia', label: 'Transferencia', icon: '🏛️', bgActive: 'bg-slate-700' },
];
// "Valorizado" solo aplica a ventas al crédito (se factura contra la subdealership, no se cobra
// directo) — no tiene sentido como método de pago para una venta al contado.
const CREDIT_ONLY_METHOD = { id: 'valorizado', label: 'Valorizado', icon: '📊', bgActive: 'bg-teal-600' };

const paymentConditionSelected = ref<'contado' | 'credito'>('contado');
const paymentMethodSelected    = ref<string>('efectivo');
const buyerDni                 = ref('');
const subdealershipSelected    = ref<number | ''>('');

// Al crédito, "Valorizado" es la única forma de pago posible (se factura a la subdealership, no
// se cobra en el momento) — no tiene sentido ofrecer Efectivo/Yape/etc. junto a esa condición.
const availablePaymentMethods = computed(() => (paymentConditionSelected.value === 'credito' ? [CREDIT_ONLY_METHOD] : PAYMENT_METHODS));

// ── Búsqueda de comensal por DNI (solo crédito) ─────────────────────────────
type DinnerLookupStatus = 'idle' | 'checking' | 'found' | 'not_found';
const dinnerLookupStatus = ref<DinnerLookupStatus>('idle');
const dinnerFound        = ref<Dinner | null>(null);
const dinnerId           = ref<number | null>(null);
const showManualRegister = ref(false);
const registeringDinner  = ref(false);
const manualDinnerForm   = ref({ name: '', phone: '' });

async function lookupDinnerByDni() {
    dinnerId.value = null;
    dinnerFound.value = null;
    showManualRegister.value = false;

    if (buyerDni.value.length !== 8) {
        dinnerLookupStatus.value = 'idle';
        return;
    }

    dinnerLookupStatus.value = 'checking';
    try {
        const res = await axios.get('/pos/find-dinner-by-dni', { params: { dni: buyerDni.value } });
        if (res.data.found) {
            dinnerLookupStatus.value = 'found';
            dinnerFound.value = res.data.dinner;
            dinnerId.value = res.data.dinner.id;
            // Si el comensal ya tiene subdealership y todavía no se eligió una, se sugiere la suya.
            if (!subdealershipSelected.value && res.data.dinner.subdealership_id) {
                subdealershipSelected.value = res.data.dinner.subdealership_id;
            }
        } else {
            dinnerLookupStatus.value = 'not_found';
        }
    } catch (err) {
        console.error(err);
        dinnerLookupStatus.value = 'idle';
    }
}

watch(buyerDni, () => lookupDinnerByDni());

function openManualRegister() {
    manualDinnerForm.value = { name: '', phone: '' };
    showManualRegister.value = true;
}

async function registerDinnerManually() {
    if (!manualDinnerForm.value.name.trim()) {
        Swal.fire({ icon: 'warning', title: 'Falta el nombre', text: 'Ingresa el nombre del comensal.', confirmButtonColor: '#dc2626' });
        return;
    }

    registeringDinner.value = true;
    try {
        const res = await axios.post('/dinners/quick-register', {
            name: manualDinnerForm.value.name.trim(),
            dni: buyerDni.value,
            phone: manualDinnerForm.value.phone.trim() || null,
            subdealership_id: subdealershipSelected.value || null,
        });
        dinnerFound.value = res.data.dinner;
        dinnerId.value = res.data.dinner.id;
        dinnerLookupStatus.value = 'found';
        showManualRegister.value = false;
    } catch (err: any) {
        const errors = err.response?.data?.errors ?? {};
        const msg = errors.dni?.[0] ?? errors.name?.[0] ?? 'No se pudo registrar el comensal.';
        Swal.fire({ icon: 'error', title: 'Error', text: msg, confirmButtonColor: '#dc2626' });
    } finally {
        registeringDinner.value = false;
    }
}

// Al volver a "Contado" se limpia todo el sub-flujo de crédito para no arrastrar datos a medias.
watch(paymentConditionSelected, (val) => {
    if (val === 'credito') {
        // Único método de pago posible al crédito — se fuerza sin dejar elegir otro.
        paymentMethodSelected.value = 'valorizado';
    } else {
        if (paymentMethodSelected.value === 'valorizado') paymentMethodSelected.value = 'efectivo';
        buyerDni.value = '';
        subdealershipSelected.value = '';
        dinnerLookupStatus.value = 'idle';
        dinnerFound.value = null;
        dinnerId.value = null;
        showManualRegister.value = false;
    }
});

const submitting = ref(false);

// ── Report Modal State ──────────────────────────────────────────────────────
const showReportModal   = ref(false);
const reportFromDate       = ref('');
const reportToDate         = ref('');
const reportMercantilId    = ref<number | string>('all');
const reportPaymentMethod  = ref<string>('all');
const reportSubdealershipId = ref<number | string>('all');
const loadingReport     = ref(false);
const expandedSaleId    = ref<number | null>(null);

interface ReportData {
    sales: any[];
    total_money: number;
    total_subtotal: number;
    total_igv: number;
    total_sales_count: number;
    total_items_count: number;
}
const reportData = ref<ReportData | null>(null);

interface CartItem {
    productId:  number;
    name:       string;
    category:   string;
    unit_price: number;
    quantity:   number;
    total:      number;
}
const cart = ref<CartItem[]>([]);

// ── Derived ────────────────────────────────────────────────────────────────
const mercantilProducts = computed<Product[]>(() =>
    props.mercantiles.find(m => m.id === mercantilSelected.value)?.products ?? [],
);

const categories = computed<string[]>(() =>
    [...new Set(mercantilProducts.value.map((p: Product) => p.category ?? 'Sin categoría'))].sort(),
);

const filtered = computed<Product[]>(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (query) {
        // Mientras se busca, se ignora la categoría activa — el cajero puede estar parado en
        // "Bebidas" y buscar algo de "Abarrotes" sin tener que cambiar de pestaña primero.
        return mercantilProducts.value.filter(
            (p: Product) =>
                p.name.toLowerCase().includes(query) ||
                (p.sku ?? '').toLowerCase().includes(query) ||
                (p.marca ?? '').toLowerCase().includes(query),
        );
    }

    if (activeCategory.value !== 'all')
        return mercantilProducts.value.filter((p: Product) => (p.category ?? 'Sin categoría') === activeCategory.value);

    return mercantilProducts.value;
});

const cartSubtotal = computed(() => cart.value.reduce((s, i) => s + i.total, 0));
const igv          = computed(() => +(cartSubtotal.value * 0.18).toFixed(2));
const cartTotal    = computed(() => cartSubtotal.value);

const inCart       = (id: number) => cart.value.some(i => i.productId === id);
const cartItem     = (id: number) => cart.value.find(i => i.productId === id);

const dniValid = computed(() => paymentConditionSelected.value !== 'credito' || /^\d{8}$/.test(buyerDni.value.trim()));
const canSubmit = computed(() => !submitting.value && cart.value.length > 0 && dniValid.value);

// ── Cart ───────────────────────────────────────────────────────────────────
const addToCart = (product: Product) => {
    const fullProduct = mercantilProducts.value.find(p => p.id === product.id) ?? product;
    const existing = cartItem(fullProduct.id);
    const currentQty = existing ? existing.quantity : 0;
    const stockAvailable = fullProduct.stock ?? 0;

    if (stockAvailable <= 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Sin stock',
            text: `El producto "${fullProduct.name}" no cuenta con stock disponible.`,
            confirmButtonColor: '#dc2626',
            timer: 2000,
            timerProgressBar: true,
        });
        return;
    }

    if (currentQty >= stockAvailable) {
        Swal.fire({
            icon: 'warning',
            title: 'Límite de stock',
            text: `Solo hay ${stockAvailable} unidades disponibles de "${fullProduct.name}".`,
            confirmButtonColor: '#dc2626',
            timer: 2000,
            timerProgressBar: true,
        });
        return;
    }

    if (existing) {
        existing.quantity++;
        existing.total = +(existing.quantity * existing.unit_price).toFixed(2);
    } else {
        cart.value.push({
            productId:  fullProduct.id,
            name:       fullProduct.name,
            category:   fullProduct.category ?? 'Sin categoría',
            unit_price: fullProduct.price,
            quantity:   1,
            total:      fullProduct.price,
        });
    }
};

const decreaseQty = (id: number) => {
    const item = cartItem(id);
    if (!item) return;
    if (item.quantity <= 1) { removeItem(id); return; }
    item.quantity--;
    item.total = +(item.quantity * item.unit_price).toFixed(2);
};

const removeItem = (id: number) => { cart.value = cart.value.filter(i => i.productId !== id); };
const clearCart  = () => { cart.value = []; };

// ── Report Modal Functions ──────────────────────────────────────────────────
function getWeekRange() {
    const now = new Date();
    const day = now.getDay();
    const diffToMonday = (day === 0 ? -6 : 1 - day);
    const monday = new Date(now);
    monday.setDate(now.getDate() + diffToMonday);

    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);

    const fmt = (d: Date) => {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const date = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${date}`;
    };

    return { start: fmt(monday), end: fmt(sunday) };
}

function openReportModal() {
    const { start, end } = getWeekRange();
    reportFromDate.value = start;
    reportToDate.value   = end;
    reportMercantilId.value = mercantilSelected.value || 'all';
    reportPaymentMethod.value = 'all';
    reportSubdealershipId.value = 'all';
    showReportModal.value = true;
    fetchReportData();
}

function setPresetRange(type: 'today' | 'week' | 'month') {
    const now = new Date();
    const fmt = (d: Date) => {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const date = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${date}`;
    };

    if (type === 'today') {
        const todayStr = fmt(now);
        reportFromDate.value = todayStr;
        reportToDate.value   = todayStr;
    } else if (type === 'week') {
        const { start, end } = getWeekRange();
        reportFromDate.value = start;
        reportToDate.value   = end;
    } else if (type === 'month') {
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay  = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        reportFromDate.value = fmt(firstDay);
        reportToDate.value   = fmt(lastDay);
    }
    fetchReportData();
}

async function fetchReportData() {
    if (!reportFromDate.value || !reportToDate.value) return;
    loadingReport.value = true;
    try {
        const res = await axios.get('/pos/report', {
            params: {
                from: reportFromDate.value,
                to: reportToDate.value,
                mercantil_id: reportMercantilId.value === 'all' ? null : reportMercantilId.value,
                payment_method: reportPaymentMethod.value === 'all' ? null : reportPaymentMethod.value,
                subdealership_id: reportSubdealershipId.value === 'all' ? null : reportSubdealershipId.value,
            },
        });
        reportData.value = res.data;
    } catch (err) {
        console.error(err);
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo obtener el reporte de ventas.' });
    } finally {
        loadingReport.value = false;
    }
}

function toggleExpandSale(id: number) {
    expandedSaleId.value = expandedSaleId.value === id ? null : id;
}

function exportReportToExcel() {
    if (!reportFromDate.value || !reportToDate.value) return;
    const mercantil       = reportMercantilId.value === 'all' ? '' : reportMercantilId.value;
    const paymentMethod   = reportPaymentMethod.value === 'all' ? '' : reportPaymentMethod.value;
    const subdealershipId = reportSubdealershipId.value === 'all' ? '' : reportSubdealershipId.value;
    const url = `/pos/export-report?from=${reportFromDate.value}&to=${reportToDate.value}&mercantil_id=${mercantil}&payment_method=${paymentMethod}&subdealership_id=${subdealershipId}`;
    window.location.href = url;
}
const submit = async () => {
    if (!mercantilSelected.value) { Swal.fire({ icon: 'warning', title: 'Sin mercantil', text: 'Selecciona un mercantil.', confirmButtonColor: '#dc2626' }); return; }
    if (!cart.value.length)       { Swal.fire({ icon: 'warning', title: 'Carrito vacío', text: 'Agrega al menos un producto.', confirmButtonColor: '#dc2626' }); return; }

    if (paymentConditionSelected.value === 'credito' && !/^\d{8}$/.test(buyerDni.value.trim())) {
        Swal.fire({
            icon: 'warning',
            title: 'DNI requerido',
            text: 'Para ventas al crédito, ingresa el DNI del comprador (8 dígitos).',
            confirmButtonColor: '#dc2626',
        });
        return;
    }

    submitting.value = true;
    try {
        const fd = new FormData();
        fd.append('mercantil_id',      mercantilSelected.value.toString());
        fd.append('sale_type_id',      saletypeSelected.value.toString());
        fd.append('payment_condition', paymentConditionSelected.value);
        fd.append('payment_method',    paymentMethodSelected.value);
        fd.append('products',          JSON.stringify(cart.value));
        fd.append('date',              dateSelected.value);
        if (paymentConditionSelected.value === 'credito') {
            fd.append('buyer_dni', buyerDni.value.trim());
            if (subdealershipSelected.value) fd.append('subdealership_id', String(subdealershipSelected.value));
            if (dinnerId.value) fd.append('dinner_id', String(dinnerId.value));
        }

        await axios.post('/pos/store', fd);

        router.reload({ only: ['mercantiles'] });

        await Swal.fire({
            icon: 'success', title: '¡Venta registrada!',
            confirmButtonColor: '#6366f1', timer: 1800, timerProgressBar: true, showConfirmButton: false,
        });

        clearCart();
        buyerDni.value = '';
        subdealershipSelected.value = '';
        dinnerLookupStatus.value = 'idle';
        dinnerFound.value = null;
        dinnerId.value = null;
        showManualRegister.value = false;
    } catch (err: any) {
        const fieldError = err.response?.data?.errors?.buyer_dni?.[0];
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: fieldError ?? err.response?.data?.message ?? 'No se pudo registrar la venta.',
            confirmButtonColor: '#dc2626',
        });
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <Head title="Punto de Venta" />
    <AppLayout>
        <div class="flex h-[calc(100vh-4rem)] flex-col overflow-hidden bg-gray-100">

            <!-- ── CONFIG BAR ──────────────────────────────────────────────── -->
            <div class="flex flex-wrap items-center gap-2 border-b bg-white px-4 py-2.5 shadow-sm">
                <div class="flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5">
                    <Store class="h-3.5 w-3.5 text-zinc-500" />
                    <select v-model="mercantilSelected" class="border-none bg-transparent text-sm font-semibold text-zinc-700 outline-none">
                        <option :value="0" disabled>Seleccionar mercantil</option>
                        <option v-for="m in mercantiles" :key="m.id" :value="m.id">
                            {{ m.name }}<span v-if="m.unit"> · {{ m.unit.name }}</span>
                        </option>
                    </select>
                </div>

                <div class="flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5">
                    <CalendarDays class="h-3.5 w-3.5 text-zinc-500" />
                    <input type="date" v-model="dateSelected" class="border-none bg-transparent text-sm font-semibold text-zinc-700 outline-none" />
                </div>

                <div class="flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5">
                    <Clock class="h-3.5 w-3.5 text-zinc-500" />
                    <select v-model="saletypeSelected" class="border-none bg-transparent text-sm font-semibold text-zinc-700 outline-none">
                        <option :value="0" disabled>Tipo de venta</option>
                        <option v-for="st in sale_types" :key="st.id" :value="st.id">{{ st.name }}</option>
                    </select>
                </div>

                <!-- Search bar -->
                <div class="relative flex-1 max-w-xs">
                    <Search class="absolute top-2.5 left-3 h-4 w-4 text-zinc-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar por nombre, marca o SKU..."
                        class="w-full rounded-lg border border-zinc-200 bg-zinc-50 py-2 pr-8 pl-9 text-sm text-zinc-700 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        type="button"
                        class="absolute top-2 right-2 flex h-5 w-5 items-center justify-center rounded-full text-zinc-400 hover:bg-zinc-200 hover:text-zinc-600 transition-colors"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>

                <!-- Report button -->
                <button
                    @click="openReportModal"
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors shadow-2xs"
                >
                    <BarChart3 class="h-4 w-4 text-indigo-600" />
                    <span class="hidden sm:inline">Reporte de Ventas</span>
                </button>

                <div class="ml-auto flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-400">En carrito</p>
                        <p class="text-lg font-black text-zinc-800">{{ cart.length }} ítem{{ cart.length !== 1 ? 's' : '' }}</p>
                    </div>
                    <div class="h-8 w-px bg-zinc-100" />
                    <div class="text-right">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-400">Total</p>
                        <p class="text-lg font-black text-indigo-600">S/ {{ cartTotal.toFixed(2) }}</p>
                    </div>
                </div>
            </div>

            <!-- ── MAIN ────────────────────────────────────────────────────── -->
            <div class="flex flex-1 overflow-hidden">

                <!-- LEFT: product grid -->
                <div class="flex flex-1 flex-col overflow-hidden">

                    <!-- Category tabs -->
                    <div class="flex gap-2 overflow-x-auto border-b bg-white px-4 py-3">
                        <button
                            @click="activeCategory = 'all'"
                            :class="['flex shrink-0 items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-bold transition-all',
                                activeCategory === 'all' ? 'bg-slate-800 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                        >
                            📋 Todos
                        </button>
                        <button
                            v-for="cat in categories" :key="cat"
                            @click="activeCategory = cat"
                            :class="['flex shrink-0 items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-bold transition-all',
                                activeCategory === cat
                                    ? `${categoryMeta(cat).accent} text-white shadow-md`
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                        >
                            {{ categoryMeta(cat).emoji }} {{ cat }}
                        </button>
                    </div>

                    <!-- Product cards -->
                    <div class="flex-1 overflow-y-auto p-4">
                        <div v-if="filtered.length === 0" class="flex h-full flex-col items-center justify-center gap-3 text-zinc-400">
                            <Search v-if="searchQuery.trim()" class="h-12 w-12 opacity-30" />
                            <Package v-else class="h-12 w-12 opacity-30" />
                            <p class="font-medium">
                                {{ searchQuery.trim() ? `Sin resultados para "${searchQuery.trim()}"` : 'No hay productos disponibles' }}
                            </p>
                            <button
                                v-if="searchQuery.trim()"
                                @click="searchQuery = ''"
                                class="text-xs font-semibold text-indigo-600 hover:underline"
                            >
                                Limpiar búsqueda
                            </button>
                        </div>

                        <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                            <button
                                v-for="product in filtered" :key="product.id"
                                @click="addToCart(product)"
                                :disabled="(product.stock ?? 0) <= 0"
                                :class="['group relative flex flex-col items-center gap-2 rounded-2xl border-2 p-4 text-center transition-all duration-200 active:scale-95',
                                    (product.stock ?? 0) <= 0
                                        ? 'opacity-60 cursor-not-allowed border-zinc-200 bg-zinc-100'
                                        : inCart(product.id)
                                            ? `${categoryMeta(product.category ?? 'Sin categoría').accent} border-transparent text-white shadow-lg ring-4 ${categoryMeta(product.category ?? 'Sin categoría').ring}`
                                            : `border-white bg-white hover:shadow-md ${categoryMeta(product.category ?? 'Sin categoría').bg}`]"
                            >
                                <!-- Qty badge when in cart -->
                                <div
                                    v-if="inCart(product.id)"
                                    class="absolute top-2 right-2 flex h-6 w-6 items-center justify-center rounded-full bg-white/30 text-xs font-black text-white"
                                >
                                    {{ cartItem(product.id)?.quantity }}
                                </div>

                                <!-- Stock badge top-left -->
                                <div
                                    :class="[
                                        'absolute top-2 left-2 flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold tracking-tight',
                                        inCart(product.id)
                                            ? 'bg-white/20 text-white'
                                            : (product.stock ?? 0) <= 0
                                                ? 'bg-red-100 text-red-700 border border-red-200'
                                                : (product.stock ?? 0) <= 5
                                                    ? 'bg-amber-100 text-amber-800 border border-amber-200'
                                                    : 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'h-1.5 w-1.5 rounded-full',
                                            inCart(product.id)
                                                ? 'bg-white'
                                                : (product.stock ?? 0) <= 0
                                                    ? 'bg-red-500'
                                                    : (product.stock ?? 0) <= 5
                                                        ? 'bg-amber-500'
                                                        : 'bg-emerald-500'
                                        ]"
                                    ></span>
                                    {{ (product.stock ?? 0) <= 0 ? 'Sin stock' : `Stock: ${product.stock ?? 0}` }}
                                </div>

                                <!-- Icon -->
                                <div :class="['mt-2 flex h-14 w-14 items-center justify-center rounded-xl text-2xl transition-colors',
                                    inCart(product.id) ? 'bg-white/20' : `${categoryMeta(product.category ?? 'Sin categoría').bg} shadow-sm`]">
                                    {{ categoryMeta(product.category ?? 'Sin categoría').emoji }}
                                </div>

                                <!-- Info -->
                                <div class="w-full">
                                    <p :class="['text-sm font-bold leading-tight', inCart(product.id) ? 'text-white' : 'text-zinc-800']">
                                        {{ product.name }}
                                    </p>
                                    <p
                                        v-if="product.marca"
                                        :class="['mt-0.5 truncate text-[10px] font-semibold', inCart(product.id) ? 'text-white/80' : 'text-indigo-500']"
                                    >
                                        {{ product.marca }}
                                    </p>
                                    <p v-if="product.sku" :class="['mt-0.5 text-[10px]', inCart(product.id) ? 'text-white/70' : 'text-zinc-400']">
                                        {{ product.sku }}
                                    </p>
                                </div>

                                <!-- Price -->
                                <div :class="['w-full rounded-xl py-1.5 text-sm font-black',
                                    inCart(product.id) ? 'bg-white/20 text-white' : 'bg-white text-zinc-800 shadow-sm']">
                                    S/ {{ product.price.toFixed(2) }}
                                </div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: order panel -->
                <div class="flex w-[370px] shrink-0 flex-col overflow-hidden border-l bg-white shadow-xl">

                    <!-- Header -->
                    <div class="border-b bg-slate-800 px-5 py-4">
                        <h2 class="text-lg font-black text-white">Orden Actual</h2>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{ dateSelected
                                ? new Date(dateSelected + 'T00:00:00').toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long' })
                                : 'Sin fecha' }}
                        </p>
                    </div>

                    <!-- Cart items -->
                    <div class="flex-1 overflow-y-auto px-5 py-3">
                        <div v-if="cart.length === 0" class="flex h-full flex-col items-center justify-center gap-3 text-zinc-300">
                            <ShoppingBag class="h-10 w-10" />
                            <p class="text-sm font-medium">Agrega productos al carrito</p>
                        </div>

                        <TransitionGroup v-else name="cart" tag="div" class="space-y-2">
                            <div v-for="item in cart" :key="item.productId"
                                class="flex items-center gap-3 rounded-xl border border-zinc-100 bg-zinc-50 p-3">
                                <div :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-base', categoryMeta(item.category).bg]">
                                    {{ categoryMeta(item.category).emoji }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-bold text-zinc-800">{{ item.name }}</p>
                                    <p class="text-xs text-zinc-500">S/ {{ item.unit_price.toFixed(2) }} c/u</p>
                                </div>
                                <!-- Qty controls -->
                                <div class="flex items-center gap-1.5">
                                    <button @click="decreaseQty(item.productId)"
                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-zinc-200 text-zinc-600 hover:bg-red-100 hover:text-red-600 transition-colors text-xs font-black">
                                        −
                                    </button>
                                    <span class="w-5 text-center text-sm font-black text-zinc-800">{{ item.quantity }}</span>
                                    <button @click="addToCart({ id: item.productId, name: item.name, category: item.category, price: item.unit_price, is_active: true })"
                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-zinc-200 text-zinc-600 hover:bg-indigo-100 hover:text-indigo-600 transition-colors text-xs font-black">
                                        +
                                    </button>
                                </div>
                                <div class="flex flex-col items-end">
                                    <p class="text-sm font-black text-zinc-800">S/ {{ item.total.toFixed(2) }}</p>
                                    <button @click="removeItem(item.productId)" class="text-zinc-300 transition-colors hover:text-red-500">
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>

                    <!-- Totals & submit -->
                    <div class="space-y-3 border-t bg-white px-5 py-4">
                        <!-- Payment Condition & Method Options -->
                        <div class="space-y-2.5 rounded-xl border border-zinc-200 bg-zinc-50/80 p-3 text-xs">
                            <!-- Condición: Contado vs Crédito -->
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block mb-1">Condición de Pago</span>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <button
                                        type="button"
                                        @click="paymentConditionSelected = 'contado'"
                                        :class="['flex items-center justify-center gap-1 rounded-lg py-1.5 font-bold transition-all text-xs',
                                            paymentConditionSelected === 'contado'
                                                ? 'bg-emerald-600 text-white shadow-xs'
                                                : 'bg-white text-zinc-600 border border-zinc-200 hover:bg-zinc-100']"
                                    >
                                        <span>💵 Contado</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="paymentConditionSelected = 'credito'"
                                        :class="['flex items-center justify-center gap-1 rounded-lg py-1.5 font-bold transition-all text-xs',
                                            paymentConditionSelected === 'credito'
                                                ? 'bg-amber-600 text-white shadow-xs'
                                                : 'bg-white text-zinc-600 border border-zinc-200 hover:bg-zinc-100']"
                                    >
                                        <span>⏳ Crédito</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Bloque exclusivo de Crédito: Subdealership -> DNI -> resultado búsqueda -->
                            <template v-if="paymentConditionSelected === 'credito'">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block mb-1">Subdealership</span>
                                    <select
                                        v-model="subdealershipSelected"
                                        class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-semibold text-zinc-800 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
                                    >
                                        <option value="">Sin subdealership</option>
                                        <option v-for="sd in subdealerships" :key="sd.id" :value="sd.id">{{ sd.name }}</option>
                                    </select>
                                </div>

                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block mb-1">
                                        DNI del Comprador <span class="text-red-500">*</span>
                                    </span>
                                    <div class="relative">
                                        <input
                                            v-model="buyerDni"
                                            type="text"
                                            inputmode="numeric"
                                            maxlength="8"
                                            placeholder="Ej. 45678912"
                                            @input="buyerDni = buyerDni.replace(/\D/g, '').slice(0, 8)"
                                            :class="['w-full rounded-lg border bg-white px-3 py-1.5 pr-8 text-sm font-semibold text-zinc-800 outline-none focus:ring-2',
                                                buyerDni.length > 0 && buyerDni.length !== 8
                                                    ? 'border-red-300 focus:border-red-400 focus:ring-red-100'
                                                    : 'border-zinc-200 focus:border-amber-400 focus:ring-amber-100']"
                                        />
                                        <Loader2 v-if="dinnerLookupStatus === 'checking'" class="absolute right-2.5 top-2.5 h-4 w-4 animate-spin text-zinc-400" />
                                        <CheckCircle2 v-else-if="dinnerLookupStatus === 'found'" class="absolute right-2.5 top-2.5 h-4 w-4 text-emerald-500" />
                                    </div>
                                    <p v-if="buyerDni.length > 0 && buyerDni.length !== 8" class="mt-1 text-[10px] font-semibold text-red-500">
                                        El DNI debe tener 8 dígitos.
                                    </p>

                                    <!-- Comensal encontrado -->
                                    <div v-if="dinnerLookupStatus === 'found' && dinnerFound" class="mt-1.5 flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1.5 text-[11px] font-semibold text-emerald-700">
                                        <Check class="h-3.5 w-3.5 shrink-0" />
                                        <span class="truncate">{{ dinnerFound.name }}<span v-if="dinnerFound.subdealership"> · {{ dinnerFound.subdealership.name }}</span></span>
                                    </div>

                                    <!-- No encontrado: registrar manualmente o continuar sin vincular -->
                                    <div v-else-if="dinnerLookupStatus === 'not_found'" class="mt-1.5 space-y-1.5">
                                        <p class="text-[10px] font-semibold text-amber-600">
                                            No se encontró un comensal con este DNI. Puedes registrarlo o continuar sin vincularlo.
                                        </p>
                                        <button
                                            v-if="!showManualRegister"
                                            type="button"
                                            @click="openManualRegister"
                                            class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-amber-300 bg-white py-1.5 text-[11px] font-bold text-amber-700 hover:bg-amber-50 transition-colors"
                                        >
                                            <UserPlus class="h-3.5 w-3.5" /> Registrar comensal
                                        </button>

                                        <div v-else class="space-y-1.5 rounded-lg border border-amber-200 bg-amber-50/60 p-2.5">
                                            <input
                                                v-model="manualDinnerForm.name"
                                                type="text"
                                                placeholder="Nombre completo *"
                                                class="w-full rounded-md border border-zinc-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-zinc-800 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
                                            />
                                            <input
                                                v-model="manualDinnerForm.phone"
                                                type="text"
                                                placeholder="Teléfono (opcional)"
                                                class="w-full rounded-md border border-zinc-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-zinc-800 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
                                            />
                                            <div class="flex gap-1.5">
                                                <button
                                                    type="button"
                                                    @click="showManualRegister = false"
                                                    class="flex-1 rounded-md border border-zinc-200 bg-white py-1.5 text-[11px] font-bold text-zinc-500 hover:bg-zinc-100 transition-colors"
                                                >
                                                    Cancelar
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="registerDinnerManually"
                                                    :disabled="registeringDinner"
                                                    class="flex-1 rounded-md bg-amber-600 py-1.5 text-[11px] font-bold text-white hover:bg-amber-700 transition-colors disabled:opacity-50"
                                                >
                                                    {{ registeringDinner ? 'Guardando…' : 'Guardar' }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Método de Pago -->
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block mb-1">Tipo de Pago</span>
                                <div :class="['grid gap-1', paymentConditionSelected === 'credito' ? 'grid-cols-1' : 'grid-cols-3']">
                                    <button
                                        v-for="method in availablePaymentMethods"
                                        :key="method.id"
                                        type="button"
                                        @click="paymentMethodSelected = method.id"
                                        :class="['flex items-center justify-center gap-1 rounded-lg py-1.5 px-1 font-bold text-[11px] transition-all',
                                            paymentMethodSelected === method.id
                                                ? `${method.bgActive} text-white shadow-xs`
                                                : 'bg-white text-zinc-700 border border-zinc-200 hover:bg-zinc-100']"
                                    >
                                        <span>{{ method.icon }}</span>
                                        <span>{{ method.label }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1.5 rounded-xl bg-zinc-50 p-3 text-sm">
                            <div class="flex justify-between text-zinc-500">
                                <span>Subtotal</span>
                                <span class="font-semibold text-zinc-700">S/ {{ cartSubtotal.toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between text-zinc-500">
                                <span>IGV (18%)</span>
                                <span class="font-semibold text-zinc-700">S/ {{ igv.toFixed(2) }}</span>
                            </div>
                            <div class="mt-2 flex justify-between border-t pt-2 text-base font-black text-zinc-900">
                                <span>Total</span>
                                <span class="text-indigo-600">S/ {{ cartTotal.toFixed(2) }}</span>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button @click="clearCart" :disabled="!cart.length"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-zinc-200 text-zinc-400 transition-colors hover:border-red-300 hover:bg-red-50 hover:text-red-500 disabled:opacity-40">
                                <RotateCcw class="h-4 w-4" />
                            </button>
                            <button @click="submit" :disabled="!canSubmit"
                                class="flex h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-indigo-600 text-sm font-black text-white shadow-md shadow-indigo-200 transition-all hover:bg-indigo-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                                <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
                                <span v-else>Confirmar Venta</span>
                            </button>
                        </div>

                        <p v-if="!cart.length" class="text-center text-[11px] text-zinc-400">
                            Selecciona productos del catálogo
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- ── REPORT MODAL ─────────────────────────────────────────────────────── -->
    <Dialog v-model:open="showReportModal">
        <DialogScrollContent class="max-w-5xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-xl font-bold text-indigo-900">
                    <BarChart3 class="h-6 w-6 text-indigo-600" />
                    <span>Reporte de Ventas por Período</span>
                </DialogTitle>
            </DialogHeader>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 py-2">
                <!-- Left Side: Date controls & Filters (lg:col-span-4) -->
                <div class="space-y-4 lg:col-span-4">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 space-y-3">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-500 flex items-center gap-1.5">
                            <CalendarDays class="h-4 w-4 text-zinc-400" />
                            Rango de Fechas
                        </h3>

                        <!-- Shortcuts -->
                        <div class="grid grid-cols-3 gap-1.5">
                            <button
                                type="button"
                                @click="setPresetRange('today')"
                                class="rounded-md border border-zinc-200 bg-white py-1 text-xs font-semibold text-zinc-700 hover:bg-zinc-100 hover:text-indigo-600 transition-colors"
                            >
                                Hoy
                            </button>
                            <button
                                type="button"
                                @click="setPresetRange('week')"
                                class="rounded-md border border-indigo-200 bg-indigo-50 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors"
                            >
                                Esta Semana
                            </button>
                            <button
                                type="button"
                                @click="setPresetRange('month')"
                                class="rounded-md border border-zinc-200 bg-white py-1 text-xs font-semibold text-zinc-700 hover:bg-zinc-100 hover:text-indigo-600 transition-colors"
                            >
                                Este Mes
                            </button>
                        </div>

                        <div class="space-y-2 pt-1">
                            <div>
                                <label class="text-[11px] font-bold text-zinc-600">Fecha Inicio</label>
                                <input
                                    type="date"
                                    v-model="reportFromDate"
                                    @change="fetchReportData"
                                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium text-zinc-800 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                />
                            </div>
                            <div>
                                <label class="text-[11px] font-bold text-zinc-600">Fecha Fin</label>
                                <input
                                    type="date"
                                    v-model="reportToDate"
                                    @change="fetchReportData"
                                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium text-zinc-800 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                />
                            </div>
                            <div>
                                <label class="text-[11px] font-bold text-zinc-600">Mercantil / Local</label>
                                <select
                                    v-model="reportMercantilId"
                                    @change="fetchReportData"
                                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium text-zinc-800 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                >
                                    <option value="all">Todos los mercantiles</option>
                                    <option v-for="m in mercantiles" :key="m.id" :value="m.id">
                                        {{ m.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[11px] font-bold text-zinc-600">Tipo de Pago</label>
                                <select
                                    v-model="reportPaymentMethod"
                                    @change="fetchReportData"
                                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium text-zinc-800 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                >
                                    <option value="all">Todos los tipos de pago</option>
                                    <option v-for="method in [...PAYMENT_METHODS, CREDIT_ONLY_METHOD]" :key="method.id" :value="method.id">
                                        {{ method.icon }} {{ method.label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[11px] font-bold text-zinc-600">Subdealership</label>
                                <select
                                    v-model="reportSubdealershipId"
                                    @change="fetchReportData"
                                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium text-zinc-800 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                >
                                    <option value="all">Todas las subdealerships</option>
                                    <option v-for="sd in subdealerships" :key="sd.id" :value="sd.id">
                                        {{ sd.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- KPI Summary Cards -->
                    <div v-if="reportData" class="grid grid-cols-2 gap-2">
                        <div class="col-span-2 rounded-xl border border-indigo-100 bg-indigo-50/60 p-3 text-center">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-600">Total Recaudado</p>
                            <p class="text-2xl font-black text-indigo-700">S/ {{ reportData.total_money.toFixed(2) }}</p>
                        </div>
                        <div class="rounded-xl border border-zinc-200 bg-white p-3 text-center shadow-2xs">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-400">Ventas</p>
                            <p class="text-lg font-black text-zinc-800">{{ reportData.total_sales_count }}</p>
                        </div>
                        <div class="rounded-xl border border-zinc-200 bg-white p-3 text-center shadow-2xs">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-400">Ítems Vendidos</p>
                            <p class="text-lg font-black text-zinc-800">{{ reportData.total_items_count }}</p>
                        </div>
                    </div>

                    <!-- Export Excel Button -->
                    <button
                        type="button"
                        @click="exportReportToExcel"
                        :disabled="loadingReport || !reportData?.sales?.length"
                        class="w-full flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-emerald-200 transition-all hover:bg-emerald-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <FileSpreadsheet class="h-4 w-4" />
                        <span>Exportar Ventas a Excel</span>
                    </button>
                </div>

                <!-- Right Side: Sales list & Details (lg:col-span-8) -->
                <div class="lg:col-span-8 flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white">
                    <div class="flex items-center justify-between border-b bg-zinc-50 px-4 py-2.5">
                        <span class="text-xs font-bold uppercase tracking-wider text-zinc-700 flex items-center gap-1.5">
                            <ShoppingBag class="h-4 w-4 text-indigo-600" />
                            Historial de Ventas ({{ reportData?.sales?.length ?? 0 }})
                        </span>
                        <span v-if="loadingReport" class="flex items-center gap-1.5 text-xs text-indigo-600 font-semibold">
                            <Loader2 class="h-3.5 w-3.5 animate-spin" /> Cargando...
                        </span>
                    </div>

                    <div class="max-h-[420px] overflow-y-auto p-3">
                        <div v-if="loadingReport && !reportData" class="flex h-48 flex-col items-center justify-center gap-2 text-zinc-400">
                            <Loader2 class="h-8 w-8 animate-spin text-indigo-500" />
                            <p class="text-xs">Cargando reporte de ventas...</p>
                        </div>

                        <div v-else-if="!reportData?.sales?.length" class="flex h-48 flex-col items-center justify-center gap-2 text-zinc-400">
                            <Package class="h-10 w-10 opacity-30" />
                            <p class="text-sm font-medium">No se registraron ventas en este período</p>
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                v-for="sale in reportData.sales"
                                :key="sale.id"
                                class="rounded-xl border border-zinc-200 bg-white overflow-hidden transition-all hover:border-zinc-300"
                            >
                                <!-- Sale Header row -->
                                <div
                                    @click="toggleExpandSale(sale.id)"
                                    class="flex items-center justify-between gap-2 p-3 cursor-pointer hover:bg-zinc-50/80 transition-colors"
                                >
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="text-sm font-bold text-zinc-900">Venta #{{ sale.id }}</span>
                                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-semibold text-zinc-600">
                                                {{ sale.mercantil?.name || 'Mercantil' }}
                                            </span>
                                            <span
                                                :class="['rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-tight',
                                                    sale.payment_condition === 'credito' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800']"
                                            >
                                                {{ sale.payment_condition === 'credito' ? 'Crédito' : 'Contado' }}
                                            </span>
                                            <span
                                                v-if="sale.payment_condition === 'credito' && sale.buyer_dni"
                                                class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-bold text-zinc-600"
                                            >
                                                {{ sale.dinner?.name ? `${sale.dinner.name} · ` : '' }}DNI {{ sale.buyer_dni }}
                                            </span>
                                            <span
                                                v-if="sale.subdealership"
                                                class="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-sky-700"
                                            >
                                                {{ sale.subdealership.name }}
                                            </span>
                                            <span class="rounded-full bg-purple-50 text-purple-700 px-2 py-0.5 text-[10px] font-bold capitalize flex items-center gap-1">
                                                <span>{{ [...PAYMENT_METHODS, CREDIT_ONLY_METHOD].find(m => m.id === sale.payment_method)?.icon || '💵' }}</span>
                                                <span>{{ sale.payment_method || 'efectivo' }}</span>
                                            </span>
                                            <span v-if="sale.sale_type" class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">
                                                {{ sale.sale_type.name }}
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-zinc-400">
                                            {{ sale.date }} · {{ sale.user?.name || 'Usuario' }}
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div class="text-right">
                                            <p class="text-sm font-black text-indigo-600">S/ {{ Number(sale.total).toFixed(2) }}</p>
                                            <p class="text-[10px] text-zinc-400">{{ sale.details?.length || 0 }} productos</p>
                                        </div>
                                        <component :is="expandedSaleId === sale.id ? ChevronUp : ChevronDown" class="h-4 w-4 text-zinc-400" />
                                    </div>
                                </div>

                                <!-- Expanded Details -->
                                <div v-if="expandedSaleId === sale.id" class="border-t bg-zinc-50/60 p-3 text-xs space-y-1.5">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-400">Detalle de Productos</p>
                                    <div class="space-y-1">
                                        <div
                                            v-for="detail in sale.details"
                                            :key="detail.id"
                                            class="flex items-center justify-between text-zinc-700 bg-white rounded-lg px-2.5 py-1.5 border border-zinc-100"
                                        >
                                            <div>
                                                <span class="font-bold text-zinc-800">{{ detail.product_name }}</span>
                                                <span v-if="detail.category" class="ml-1 text-[10px] text-zinc-400">({{ detail.category }})</span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span class="font-medium text-zinc-500">{{ detail.quantity }} x S/ {{ Number(detail.unit_price).toFixed(2) }}</span>
                                                <span class="font-bold text-zinc-900">S/ {{ Number(detail.subtotal).toFixed(2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DialogScrollContent>
    </Dialog>
</template>

<style scoped>
.cart-enter-active, .cart-leave-active { transition: all 0.25s ease; }
.cart-enter-from,   .cart-leave-to     { opacity: 0; transform: translateX(16px); }

::-webkit-scrollbar       { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #e4e4e7; border-radius: 10px; }
</style>
