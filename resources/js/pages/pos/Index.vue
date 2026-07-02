<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    CalendarDays, ChevronRight, Clock, Loader2,
    Package, RotateCcw, Search, ShoppingBag, User, X,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref } from 'vue';

// ── Types ──────────────────────────────────────────────────────────────────
interface Product {
    id: number;
    name: string;
    description?: string;
    sku?: string;
    category?: string;
    price: number;
    is_active: boolean;
}

interface Cafe {
    id: number;
    name: string;
    unit?: { id: number; name: string };
}

interface Props {
    cafes:      (Cafe & { products: Product[] })[];
    sale_types: any[];
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
const cafeSelected     = ref<number>(props.cafes[0]?.id ?? 0);
const dateSelected     = ref<string>(new Date().toISOString().split('T')[0]);
const saletypeSelected = ref<number>(props.sale_types[0]?.id ?? 0);
const activeCategory   = ref<string>('all');
const searchQuery      = ref('');

const dniInput   = ref('');
const customer   = ref<any>(null);
const searching  = ref(false);
const submitting = ref(false);

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
const cafeProducts = computed<Product[]>(() =>
    props.cafes.find(c => c.id === cafeSelected.value)?.products ?? [],
);

const categories = computed<string[]>(() =>
    [...new Set(cafeProducts.value.map((p: Product) => p.category ?? 'Sin categoría'))].sort(),
);

const filtered = computed<Product[]>(() => {
    let list: Product[] = cafeProducts.value;
    if (activeCategory.value !== 'all')
        list = list.filter((p: Product) => (p.category ?? 'Sin categoría') === activeCategory.value);
    if (searchQuery.value.trim())
        list = list.filter((p: Product) => p.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
    return list;
});

const cartSubtotal = computed(() => cart.value.reduce((s, i) => s + i.total, 0));
const igv          = computed(() => +(cartSubtotal.value * 0.18).toFixed(2));
const cartTotal    = computed(() => cartSubtotal.value);

const inCart       = (id: number) => cart.value.some(i => i.productId === id);
const cartItem     = (id: number) => cart.value.find(i => i.productId === id);

// ── Cart ───────────────────────────────────────────────────────────────────
const addToCart = (product: Product) => {
    const existing = cartItem(product.id);
    if (existing) {
        existing.quantity++;
        existing.total = +(existing.quantity * existing.unit_price).toFixed(2);
    } else {
        cart.value.push({
            productId:  product.id,
            name:       product.name,
            category:   product.category ?? 'Sin categoría',
            unit_price: product.price,
            quantity:   1,
            total:      product.price,
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

// ── DNI search ─────────────────────────────────────────────────────────────
const searchCustomer = async () => {
    const dni = dniInput.value.trim();
    if (!/^\d{8}$/.test(dni)) {
        Swal.fire({ icon: 'warning', title: 'DNI inválido', text: 'Ingresa exactamente 8 dígitos.', confirmButtonColor: '#dc2626' });
        return;
    }
    if (!cafeSelected.value) {
        Swal.fire({ icon: 'warning', title: 'Sin cafetería', text: 'Selecciona una cafetería primero.', confirmButtonColor: '#dc2626' });
        return;
    }
    searching.value = true;
    try {
        const { data } = await axios.get(`/sales/search/${dni}/${cafeSelected.value}`);
        if (data?.length) { customer.value = data[0]; }
        else {
            Swal.fire({ icon: 'error', title: 'No encontrado', text: 'No se encontró un comensal con ese DNI.', confirmButtonColor: '#dc2626' });
            customer.value = null;
        }
    } catch {
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#dc2626' });
    } finally {
        searching.value = false;
    }
};

const clearCustomer = () => { customer.value = null; dniInput.value = ''; };

// ── Submit ─────────────────────────────────────────────────────────────────
const submit = async () => {
    if (!customer.value)   { Swal.fire({ icon: 'warning', title: 'Sin comensal', text: 'Busca al comensal por DNI.', confirmButtonColor: '#dc2626' }); return; }
    if (!cart.value.length){ Swal.fire({ icon: 'warning', title: 'Carrito vacío', text: 'Agrega al menos un producto.', confirmButtonColor: '#dc2626' }); return; }

    submitting.value = true;
    try {
        const fd = new FormData();
        fd.append('cafe_id',      cafeSelected.value.toString());
        fd.append('sale_type_id', saletypeSelected.value.toString());
        fd.append('products',     JSON.stringify(cart.value));
        fd.append('dni',          customer.value.dni);
        fd.append('date',         dateSelected.value);

        await axios.post('/pos/store', fd);

        await Swal.fire({
            icon: 'success', title: '¡Venta registrada!',
            html: `<p class="text-sm text-slate-600">Venta registrada para <strong>${customer.value.name}</strong>.</p>`,
            confirmButtonColor: '#6366f1', timer: 1800, timerProgressBar: true, showConfirmButton: false,
        });

        clearCart();
        clearCustomer();
    } catch (err: any) {
        Swal.fire({ icon: 'error', title: 'Error', text: err.response?.data?.message ?? 'No se pudo registrar la venta.', confirmButtonColor: '#dc2626' });
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
                    <ShoppingBag class="h-3.5 w-3.5 text-zinc-500" />
                    <select v-model="cafeSelected" class="border-none bg-transparent text-sm font-semibold text-zinc-700 outline-none">
                        <option :value="0" disabled>Seleccionar cafetería</option>
                        <option v-for="c in cafes" :key="c.id" :value="c.id">
                            {{ c.name }}<span v-if="c.unit"> · {{ c.unit.name }}</span>
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
                        placeholder="Buscar producto..."
                        class="w-full rounded-lg border border-zinc-200 bg-zinc-50 py-2 pr-3 pl-9 text-sm text-zinc-700 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                    />
                </div>

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
                            <Package class="h-12 w-12 opacity-30" />
                            <p class="font-medium">No hay productos disponibles</p>
                        </div>

                        <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                            <button
                                v-for="product in filtered" :key="product.id"
                                @click="addToCart(product)"
                                :class="['group relative flex flex-col items-center gap-2.5 rounded-2xl border-2 p-4 text-center transition-all duration-200 active:scale-95',
                                    inCart(product.id)
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

                                <!-- Icon -->
                                <div :class="['flex h-14 w-14 items-center justify-center rounded-xl text-2xl transition-colors',
                                    inCart(product.id) ? 'bg-white/20' : `${categoryMeta(product.category ?? 'Sin categoría').bg} shadow-sm`]">
                                    {{ categoryMeta(product.category ?? 'Sin categoría').emoji }}
                                </div>

                                <!-- Info -->
                                <div class="w-full">
                                    <p :class="['text-sm font-bold leading-tight', inCart(product.id) ? 'text-white' : 'text-zinc-800']">
                                        {{ product.name }}
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

                    <!-- Customer -->
                    <div class="border-b bg-slate-50 px-5 py-4">
                        <div v-if="customer" class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-indigo-600">
                                <User class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-zinc-800">{{ customer.name }}</p>
                                <p class="text-xs text-zinc-500">DNI {{ customer.dni }}<span v-if="customer.subdealership"> · {{ customer.subdealership.name }}</span></p>
                            </div>
                            <button @click="clearCustomer" class="rounded-full p-1.5 text-zinc-400 transition-colors hover:bg-red-50 hover:text-red-500">
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <div v-else class="flex gap-2">
                            <div class="relative flex-1">
                                <Search class="absolute top-2.5 left-3 h-4 w-4 text-zinc-400" />
                                <input
                                    v-model="dniInput" type="text" maxlength="8" placeholder="Buscar por DNI..."
                                    class="w-full rounded-xl border border-zinc-200 bg-white py-2 pr-3 pl-9 text-sm font-medium text-zinc-700 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                    @keydown.enter="searchCustomer"
                                />
                            </div>
                            <button @click="searchCustomer" :disabled="searching"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm transition-colors hover:bg-indigo-700 disabled:opacity-60">
                                <Loader2 v-if="searching" class="h-4 w-4 animate-spin" />
                                <ChevronRight v-else class="h-4 w-4" />
                            </button>
                        </div>
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
                            <button @click="submit" :disabled="submitting || !cart.length || !customer"
                                class="flex h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-indigo-600 text-sm font-black text-white shadow-md shadow-indigo-200 transition-all hover:bg-indigo-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">
                                <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
                                <span v-else>Confirmar Venta</span>
                            </button>
                        </div>

                        <p v-if="!customer" class="text-center text-[11px] font-medium text-amber-500">
                            ⚠ Busca al comensal por DNI para continuar
                        </p>
                        <p v-else-if="!cart.length" class="text-center text-[11px] text-zinc-400">
                            Selecciona productos del catálogo
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.cart-enter-active, .cart-leave-active { transition: all 0.25s ease; }
.cart-enter-from,   .cart-leave-to     { opacity: 0; transform: translateX(16px); }

::-webkit-scrollbar       { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #e4e4e7; border-radius: 10px; }
</style>
