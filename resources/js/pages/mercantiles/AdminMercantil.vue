<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    BadgeDollarSign,
    BarChart3,
    Boxes,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    ChevronRight,
    CircleDollarSign,
    Coins,
    CreditCard,
    DollarSign,
    Download,
    Eye,
    FileSpreadsheet,
    FileText,
    Filter,
    FolderTree,
    HelpCircle,
    Layers,
    LayoutGrid,
    LayoutList,
    MapPin,
    MoreVertical,
    Package,
    PackageCheck,
    PackageX,
    Pencil,
    Plus,
    Receipt,
    RefreshCw,
    Search,
    ShoppingBag,
    ShoppingCart,
    SlidersHorizontal,
    Sparkles,
    Store,
    Tag,
    ToggleLeft,
    ToggleRight,
    Trash2,
    TrendingUp,
    User,
    Wallet,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

// ── Types ──────────────────────────────────────────────────────────────────
interface Mine {
    id: number;
    name: string;
    units?: Unit[];
}

interface Unit {
    id: number;
    name: string;
    mine_id?: number;
    mine?: { id: number; name: string };
}

interface Batch {
    id: number;
    batch_code: string | null;
    quantity: number;
    expiration_date: string | null;
    received_at?: string | null;
    notes?: string | null;
    expiration_status?: 'expired' | 'expiring_soon' | 'ok' | null;
}

interface Product {
    id: number;
    mercantil_id: number;
    name: string;
    marca?: string | null;
    description?: string | null;
    sku?: string | null;
    category?: string | null;
    price: number;
    stock: number;
    is_active: boolean;
    batches?: Batch[];
}

interface Sale {
    id: number;
    mercantil_id: number;
    unit_id: number;
    user_id: number;
    user?: { id: number; name: string };
    sale_type_id?: number | null;
    saleType?: { id: number; name: string };
    dinner?: { id: number; name: string; dni: string } | null;
    payment_method?: string;
    payment_condition?: string;
    buyer_dni?: string | null;
    date: string;
    subtotal: number;
    igv: number;
    total: number;
    created_at: string;
}

interface Mercantil {
    id: number;
    unit_id: number;
    unit?: { id: number; name: string; mine_id?: number; mine?: { id: number; name: string } };
    name: string;
    address?: string | null;
    is_active: boolean;
    products_count?: number;
    active_products_count?: number;
    out_of_stock_count?: number;
    low_stock_count?: number;
    sales_count?: number;
    total_revenue?: number;
    total_stock?: number;
    inventory_valuation?: number;
    today_sales_amount?: number;
    today_sales_count?: number;
    month_sales_amount?: number;
    month_sales_count?: number;
    categories?: string[];
    expiring_batches_count?: number;
    products?: Product[];
    sales?: Sale[];
    created_at?: string;
    updated_at?: string;
}

interface GlobalStats {
    total_mercantiles: number;
    active_mercantiles: number;
    inactive_mercantiles: number;
    total_units: number;
    total_mines?: number;
    total_products: number;
    total_stock: number;
    total_inventory_value: number;
    total_revenue: number;
    today_sales_amount: number;
    today_sales_count: number;
    month_sales_amount: number;
    month_sales_count: number;
    total_low_stock_alerts: number;
    total_out_of_stock: number;
    total_expiring_batches: number;
}

const props = defineProps<{
    mercantiles: Mercantil[];
    units: Unit[];
    mines?: Mine[];
    globalStats?: GlobalStats;
}>();

// ── Mines & Units Catalog Helpers ──────────────────────────────────────────
const availableMines = computed<Mine[]>(() => {
    if (props.mines && props.mines.length > 0) {
        return props.mines;
    }
    // Fallback if mines prop was not passed: derive from units
    const map = new Map<number, Mine>();
    props.units.forEach((u) => {
        if (u.mine_id && u.mine) {
            if (!map.has(u.mine_id)) {
                map.set(u.mine_id, { id: u.mine_id, name: u.mine.name });
            }
        }
    });
    return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name));
});

// ── Toolbar State & Filters ────────────────────────────────────────────────
const search = ref('');
const mineFilter = ref('__all__');
const unitFilter = ref('__all__');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');
const alertFilter = ref<'all' | 'low_stock' | 'out_of_stock' | 'expiring' | 'with_sales_today'>('all');
const sortBy = ref<'name' | 'valuation_desc' | 'revenue_desc' | 'products_desc' | 'stock_desc' | 'today_sales_desc'>('name');
const viewMode = ref<'grid' | 'table' | 'analytics'>('grid');

// Toolbar Units dropdown dynamically filtered by selected mine in toolbar
const toolbarUnits = computed(() => {
    if (mineFilter.value === '__all__') {
        return props.units;
    }
    const mineIdNum = Number(mineFilter.value);
    return props.units.filter((u) => u.mine_id === mineIdNum);
});

const handleToolbarMineChange = (val: string) => {
    mineFilter.value = val;
    if (val !== '__all__') {
        const mineIdNum = Number(val);
        const childs = props.units.filter((u) => u.mine_id === mineIdNum);
        if (unitFilter.value !== '__all__' && !childs.some((u) => u.id === Number(unitFilter.value))) {
            unitFilter.value = '__all__';
        }
    }
};

// ── Computed Global Statistics ─────────────────────────────────────────────
const stats = computed(() => {
    if (props.globalStats) return props.globalStats;

    const list = props.mercantiles;
    const totalMercantiles = list.length;
    const activeMercantiles = list.filter((m) => m.is_active).length;
    const inactiveMercantiles = totalMercantiles - activeMercantiles;
    const totalUnits = new Set(list.map((m) => m.unit_id)).size;
    const totalProducts = list.reduce((acc, m) => acc + (m.products_count ?? m.products?.length ?? 0), 0);
    const totalStock = list.reduce((acc, m) => acc + (m.total_stock ?? 0), 0);
    const totalInventoryValue = list.reduce((acc, m) => acc + (m.inventory_valuation ?? 0), 0);
    const totalRevenue = list.reduce((acc, m) => acc + (m.total_revenue ?? 0), 0);
    const todaySalesAmount = list.reduce((acc, m) => acc + (m.today_sales_amount ?? 0), 0);
    const todaySalesCount = list.reduce((acc, m) => acc + (m.today_sales_count ?? 0), 0);
    const monthSalesAmount = list.reduce((acc, m) => acc + (m.month_sales_amount ?? 0), 0);
    const monthSalesCount = list.reduce((acc, m) => acc + (m.month_sales_count ?? 0), 0);
    const totalLowStockAlerts = list.reduce((acc, m) => acc + (m.low_stock_count ?? 0), 0);
    const totalOutOfStock = list.reduce((acc, m) => acc + (m.out_of_stock_count ?? 0), 0);
    const totalExpiringBatches = list.reduce((acc, m) => acc + (m.expiring_batches_count ?? 0), 0);

    return {
        total_mercantiles: totalMercantiles,
        active_mercantiles: activeMercantiles,
        inactive_mercantiles: inactiveMercantiles,
        total_units: totalUnits,
        total_mines: availableMines.value.length,
        total_products: totalProducts,
        total_stock: totalStock,
        total_inventory_value: totalInventoryValue,
        total_revenue: totalRevenue,
        today_sales_amount: todaySalesAmount,
        today_sales_count: todaySalesCount,
        month_sales_amount: monthSalesAmount,
        month_sales_count: monthSalesCount,
        total_low_stock_alerts: totalLowStockAlerts,
        total_out_of_stock: totalOutOfStock,
        total_expiring_batches: totalExpiringBatches,
    };
});

// ── Filtered & Sorted Mercantiles ──────────────────────────────────────────
const filteredMercantiles = computed(() => {
    let list = [...props.mercantiles];

    // Search
    if (search.value.trim()) {
        const query = search.value.toLowerCase().trim();
        list = list.filter((m) => {
            const nameMatch = m.name.toLowerCase().includes(query);
            const addressMatch = (m.address ?? '').toLowerCase().includes(query);
            const unitMatch = (m.unit?.name ?? '').toLowerCase().includes(query);
            const mineMatch = (m.unit?.mine?.name ?? '').toLowerCase().includes(query);
            const categoryMatch = (m.categories ?? []).some((c) => c.toLowerCase().includes(query));
            return nameMatch || addressMatch || unitMatch || mineMatch || categoryMatch;
        });
    }

    // Mine filter in toolbar
    if (mineFilter.value !== '__all__') {
        const targetMineId = Number(mineFilter.value);
        list = list.filter((m) => {
            const unit = props.units.find((u) => u.id === m.unit_id) || m.unit;
            return (unit?.mine_id ?? (m.unit as any)?.mine_id) === targetMineId;
        });
    }

    // Unit filter in toolbar
    if (unitFilter.value !== '__all__') {
        const targetUnitId = Number(unitFilter.value);
        list = list.filter((m) => m.unit_id === targetUnitId);
    }

    // Status filter
    if (statusFilter.value === 'active') {
        list = list.filter((m) => m.is_active);
    } else if (statusFilter.value === 'inactive') {
        list = list.filter((m) => !m.is_active);
    }

    // Alert filter
    if (alertFilter.value === 'low_stock') {
        list = list.filter((m) => (m.low_stock_count ?? 0) > 0);
    } else if (alertFilter.value === 'out_of_stock') {
        list = list.filter((m) => (m.out_of_stock_count ?? 0) > 0);
    } else if (alertFilter.value === 'expiring') {
        list = list.filter((m) => (m.expiring_batches_count ?? 0) > 0);
    } else if (alertFilter.value === 'with_sales_today') {
        list = list.filter((m) => (m.today_sales_count ?? 0) > 0);
    }

    // Sorting
    list.sort((a, b) => {
        if (sortBy.value === 'valuation_desc') {
            return (b.inventory_valuation ?? 0) - (a.inventory_valuation ?? 0);
        }
        if (sortBy.value === 'revenue_desc') {
            return (b.total_revenue ?? 0) - (a.total_revenue ?? 0);
        }
        if (sortBy.value === 'products_desc') {
            return (b.products_count ?? 0) - (a.products_count ?? 0);
        }
        if (sortBy.value === 'stock_desc') {
            return (b.total_stock ?? 0) - (a.total_stock ?? 0);
        }
        if (sortBy.value === 'today_sales_desc') {
            return (b.today_sales_amount ?? 0) - (a.today_sales_amount ?? 0);
        }
        return a.name.localeCompare(b.name, 'es', { sensitivity: 'base' });
    });

    return list;
});

// ── Modals & Drawers State ─────────────────────────────────────────────────
const showCreateEditModal = ref(false);
const editingId = ref<number | null>(null);

// Modal Mine Selector
const modalSelectedMineId = ref<string>('');

// Child units strictly filtered for the selected Mine in the modal
const modalChildUnits = computed<Unit[]>(() => {
    if (!modalSelectedMineId.value) return [];
    const targetMineId = Number(modalSelectedMineId.value);
    return props.units.filter((u) => u.mine_id === targetMineId);
});

const form = useForm({
    unit_id: '' as string | number,
    name: '',
    address: '',
    is_active: true as boolean,
});

// Handle change of Mine in the modal: updates child units and resets/picks unit
const handleModalMineChange = (val: any) => {
    const stringVal = String(val ?? '');
    modalSelectedMineId.value = stringVal;
    const targetMineId = Number(stringVal);
    const childs = props.units.filter((u) => u.mine_id === targetMineId);

    const currentUnitId = Number(form.unit_id);
    const exists = childs.some((u) => u.id === currentUnitId);
    if (!exists) {
        form.unit_id = childs.length > 0 ? String(childs[0].id) : '';
    }
};

const openCreateModal = () => {
    editingId.value = null;
    form.reset();

    // Default to the first available mine or currently filtered mine
    const initialMineId = mineFilter.value !== '__all__' ? Number(mineFilter.value) : availableMines.value[0]?.id;

    if (initialMineId) {
        modalSelectedMineId.value = String(initialMineId);
        const childs = props.units.filter((u) => u.mine_id === initialMineId);
        form.unit_id = childs.length > 0 ? String(childs[0].id) : '';
    } else {
        modalSelectedMineId.value = '';
        form.unit_id = props.units[0]?.id ? String(props.units[0].id) : '';
    }

    form.is_active = true;
    showCreateEditModal.value = true;
};

const openEditModal = (m: Mercantil) => {
    editingId.value = m.id;

    // Find the unit to get its mine_id
    const unit = props.units.find((u) => u.id === m.unit_id) || m.unit;
    const mineId = unit?.mine_id ?? (m.unit as any)?.mine_id;

    if (mineId) {
        modalSelectedMineId.value = String(mineId);
    } else {
        const found = availableMines.value.find((mine) =>
            props.units.some((u) => u.mine_id === mine.id && u.id === m.unit_id),
        );
        modalSelectedMineId.value = found ? String(found.id) : (availableMines.value[0]?.id ? String(availableMines.value[0].id) : '');
    }

    form.unit_id = String(m.unit_id);
    form.name = m.name;
    form.address = m.address ?? '';
    form.is_active = m.is_active;
    showCreateEditModal.value = true;
};

const closeCreateEditModal = () => {
    showCreateEditModal.value = false;
    form.reset();
    editingId.value = null;
    modalSelectedMineId.value = '';
};

const saveMercantil = () => {
    const payload = {
        ...form.data(),
        unit_id: Number(form.unit_id),
    };

    if (editingId.value) {
        form.transform(() => payload).put(route('mercantiles.update', editingId.value!), {
            preserveScroll: true,
            onSuccess: closeCreateEditModal,
        });
    } else {
        form.transform(() => payload).post(route('mercantiles.store'), {
            preserveScroll: true,
            onSuccess: closeCreateEditModal,
        });
    }
};

// ── Toggle Active Status ───────────────────────────────────────────────────
const toggleActive = (m: Mercantil) => {
    router.put(
        route('mercantiles.update', m.id),
        {
            unit_id: m.unit_id,
            name: m.name,
            address: m.address,
            is_active: !m.is_active,
        },
        { preserveScroll: true },
    );
};

// ── Delete Confirmation ────────────────────────────────────────────────────
const showDeleteModal = ref(false);
const mercantilToDelete = ref<Mercantil | null>(null);

const confirmDelete = (m: Mercantil) => {
    mercantilToDelete.value = m;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    if (!mercantilToDelete.value) return;
    router.delete(route('mercantiles.destroy', mercantilToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            mercantilToDelete.value = null;
            if (selectedMercantil.value?.id === mercantilToDelete.value?.id) {
                selectedMercantil.value = null;
                showDetailDrawer.value = false;
            }
        },
    });
};

// ── Detail Drawer / Modal ──────────────────────────────────────────────────
const showDetailDrawer = ref(false);
const selectedMercantil = ref<Mercantil | null>(null);
const detailActiveTab = ref<'overview' | 'catalog' | 'sales' | 'analytics'>('overview');
const productSearchInDetail = ref('');
const productCategoryInDetail = ref('__all__');

const openDetailDrawer = (m: Mercantil, initialTab: 'overview' | 'catalog' | 'sales' | 'analytics' = 'overview') => {
    selectedMercantil.value = m;
    detailActiveTab.value = initialTab;
    productSearchInDetail.value = '';
    productCategoryInDetail.value = '__all__';
    showDetailDrawer.value = true;
};

// Filtered products inside selected mercantil
const filteredDetailProducts = computed(() => {
    if (!selectedMercantil.value?.products) return [];
    let prods = [...selectedMercantil.value.products];

    if (productCategoryInDetail.value !== '__all__') {
        prods = prods.filter((p) => (p.category ?? 'Sin Categoría') === productCategoryInDetail.value);
    }

    if (productSearchInDetail.value.trim()) {
        const q = productSearchInDetail.value.toLowerCase().trim();
        prods = prods.filter(
            (p) =>
                p.name.toLowerCase().includes(q) ||
                (p.sku ?? '').toLowerCase().includes(q) ||
                (p.marca ?? '').toLowerCase().includes(q) ||
                (p.category ?? '').toLowerCase().includes(q),
        );
    }

    return prods;
});

const detailCategories = computed(() => {
    if (!selectedMercantil.value?.products) return [];
    const set = new Set<string>();
    selectedMercantil.value.products.forEach((p) => {
        if (p.category) set.add(p.category);
    });
    return Array.from(set).sort();
});

// ── Helper to resolve Unit and Mine Names ───────────────────────────────────
const getMercantilLocation = (m: Mercantil) => {
    const unit = props.units.find((u) => u.id === m.unit_id) || m.unit;
    const unitName = unit?.name ?? 'Sin Unidad';
    const mine = unit?.mine ?? availableMines.value.find((mine) => mine.id === unit?.mine_id);
    const mineName = mine?.name ?? null;
    return { unitName, mineName };
};

// ── Formatters ─────────────────────────────────────────────────────────────
const formatMoney = (val?: number | null) => {
    const num = Number(val ?? 0);
    return `S/ ${num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const formatNumber = (val?: number | null) => {
    const num = Number(val ?? 0);
    return num.toLocaleString('es-PE');
};

const formatDate = (dateStr?: string | null) => {
    if (!dateStr) return '—';
    try {
        const parts = dateStr.split('T')[0].split('-');
        if (parts.length === 3) {
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
        }
        return dateStr;
    } catch {
        return dateStr;
    }
};

const formatDateTime = (dateStr?: string | null) => {
    if (!dateStr) return '—';
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return formatDate(dateStr);
        return d.toLocaleDateString('es-PE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dateStr;
    }
};

const paymentMethodLabel = (method?: string | null) => {
    const map: Record<string, { label: string; bg: string; text: string; icon: any }> = {
        efectivo: { label: 'Efectivo', bg: 'bg-emerald-50 border-emerald-200 dark:bg-emerald-950/40 dark:border-emerald-800', text: 'text-emerald-700 dark:text-emerald-300', icon: Coins },
        yape: { label: 'Yape', bg: 'bg-purple-50 border-purple-200 dark:bg-purple-950/40 dark:border-purple-800', text: 'text-purple-700 dark:text-purple-300', icon: CircleDollarSign },
        plin: { label: 'Plin', bg: 'bg-cyan-50 border-cyan-200 dark:bg-cyan-950/40 dark:border-cyan-800', text: 'text-cyan-700 dark:text-cyan-300', icon: CircleDollarSign },
        tarjeta: { label: 'Tarjeta', bg: 'bg-blue-50 border-blue-200 dark:bg-blue-950/40 dark:border-blue-800', text: 'text-blue-700 dark:text-blue-300', icon: CreditCard },
        transferencia: { label: 'Transferencia', bg: 'bg-amber-50 border-amber-200 dark:bg-amber-950/40 dark:border-amber-800', text: 'text-amber-700 dark:text-amber-300', icon: Building2 },
        valorizado: { label: 'Valorizado (Crédito)', bg: 'bg-indigo-50 border-indigo-200 dark:bg-indigo-950/40 dark:border-indigo-800', text: 'text-indigo-700 dark:text-indigo-300', icon: Receipt },
    };
    return map[method?.toLowerCase() ?? ''] ?? {
        label: method || 'Otro',
        bg: 'bg-zinc-100 border-zinc-200 dark:bg-zinc-800 dark:border-zinc-700',
        text: 'text-zinc-700 dark:text-zinc-300',
        icon: Wallet,
    };
};

const getStockStatus = (stock: number) => {
    if (stock <= 0) {
        return { label: 'Agotado', color: 'bg-rose-500/10 text-rose-600 border-rose-200 dark:border-rose-900/60 dark:text-rose-400' };
    }
    if (stock <= 5) {
        return { label: 'Stock Bajo', color: 'bg-amber-500/10 text-amber-600 border-amber-200 dark:border-amber-900/60 dark:text-amber-400' };
    }
    return { label: 'Normal', color: 'bg-emerald-500/10 text-emerald-600 border-emerald-200 dark:border-emerald-900/60 dark:text-emerald-400' };
};

// Export helpers
const exportMercantilInventory = (mercantilId?: number) => {
    const url = route('pos.export-inventory', mercantilId ? { mercantil_id: mercantilId } : {});
    window.open(url, '_blank');
};

const exportMercantilSales = (mercantilId?: number) => {
    const today = new Date().toISOString().split('T')[0];
    const firstDayOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    const url = route('pos.export-report', {
        from: firstDayOfMonth,
        to: today,
        mercantil_id: mercantilId ?? 'all',
    });
    window.open(url, '_blank');
};
</script>

<template>
    <Head title="Administración General de Mercantiles" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-4 md:p-6 lg:p-8 max-w-[1600px] mx-auto w-full">

            <!-- ── 1. HEADER SECTION ──────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border/40 pb-5">
                <div>
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 border border-indigo-500/30 text-indigo-600 dark:text-indigo-400 shadow-sm">
                            <Store class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                                    Administración de Mercantiles
                                </h1>
                                <Badge variant="outline" class="font-mono text-[11px] bg-indigo-50/50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/50 dark:text-indigo-300 dark:border-indigo-800">
                                    {{ stats.total_mercantiles }} puntos de venta
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground mt-0.5">
                                Supervisión general de puntos de venta, control de inventario valorizado y recaudación por mina y unidad.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center flex-wrap gap-2.5">
                    <Button
                        variant="outline"
                        as-child
                        class="gap-1.5 h-9 text-xs font-medium border-border/80 hover:bg-accent"
                    >
                        <Link :href="route('pos.index')">
                            <ShoppingCart class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                            Ir al POS
                        </Link>
                    </Button>

                    <Button
                        variant="outline"
                        as-child
                        class="gap-1.5 h-9 text-xs font-medium border-border/80 hover:bg-accent"
                    >
                        <Link :href="route('products.index')">
                            <Package class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                            Catálogo de Productos
                        </Link>
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" class="gap-1.5 h-9 text-xs font-medium">
                                <Download class="h-3.5 w-3.5" />
                                Exportar
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuLabel class="text-xs">Reportes Globales</DropdownMenuLabel>
                            <DropdownMenuItem @click="exportMercantilInventory()" class="text-xs cursor-pointer">
                                <FileSpreadsheet class="mr-2 h-3.5 w-3.5 text-emerald-600" />
                                Exportar Inventario Completo
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="exportMercantilSales()" class="text-xs cursor-pointer">
                                <FileText class="mr-2 h-3.5 w-3.5 text-indigo-600" />
                                Reporte Ventas Mes Actual
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Button
                        @click="openCreateModal"
                        class="gap-2 h-9 bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-sm transition-all active:scale-95"
                    >
                        <Plus class="h-4 w-4" />
                        Nuevo Mercantil
                    </Button>
                </div>
            </div>

            <!-- ── 2. EXECUTIVE KPI CARDS ─────────────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- KPI 1: Mercantiles Activos -->
                <Card class="relative overflow-hidden border border-border/60 bg-gradient-to-br from-card to-card/60 shadow-xs hover:border-indigo-500/30 transition-all">
                    <CardHeader class="p-4.5 pb-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Puntos de Venta</span>
                            <div class="h-8 w-8 rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                                <Store class="h-4 w-4" />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-4.5 pt-0">
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-bold tracking-tight text-foreground font-mono">
                                {{ stats.active_mercantiles }}
                            </span>
                            <span class="text-xs text-muted-foreground">de {{ stats.total_mercantiles }} activos</span>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-xs pt-2 border-t border-border/40">
                            <span class="text-muted-foreground flex items-center gap-1">
                                <Building2 class="h-3 w-3" /> {{ availableMines.length }} Minas • {{ props.units.length }} Unidades
                            </span>
                            <span class="font-medium text-emerald-600 dark:text-emerald-400">
                                {{ stats.total_mercantiles > 0 ? Math.round((stats.active_mercantiles / stats.total_mercantiles) * 100) : 0 }}% operatividad
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- KPI 2: Valorización de Inventario -->
                <Card class="relative overflow-hidden border border-border/60 bg-gradient-to-br from-card to-card/60 shadow-xs hover:border-blue-500/30 transition-all">
                    <CardHeader class="p-4.5 pb-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Inventario Valorizado</span>
                            <div class="h-8 w-8 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <BadgeDollarSign class="h-4 w-4" />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-4.5 pt-0">
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-bold tracking-tight text-foreground font-mono">
                                {{ formatMoney(stats.total_inventory_value) }}
                            </span>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-xs pt-2 border-t border-border/40">
                            <span class="text-muted-foreground flex items-center gap-1">
                                <Package class="h-3 w-3" /> {{ formatNumber(stats.total_products) }} productos SKU
                            </span>
                            <span class="font-medium text-foreground font-mono">
                                {{ formatNumber(stats.total_stock) }} unidades
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- KPI 3: Recaudación & Ventas -->
                <Card class="relative overflow-hidden border border-border/60 bg-gradient-to-br from-card to-card/60 shadow-xs hover:border-emerald-500/30 transition-all">
                    <CardHeader class="p-4.5 pb-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Ventas Totales</span>
                            <div class="h-8 w-8 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                <TrendingUp class="h-4 w-4" />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-4.5 pt-0">
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-bold tracking-tight text-foreground font-mono">
                                {{ formatMoney(stats.total_revenue) }}
                            </span>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-xs pt-2 border-t border-border/40">
                            <span class="text-muted-foreground flex items-center gap-1">
                                <Calendar class="h-3 w-3 text-emerald-600" /> Hoy: {{ formatMoney(stats.today_sales_amount) }}
                            </span>
                            <Badge variant="outline" class="text-[10px] px-1.5 py-0 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-200">
                                {{ stats.today_sales_count }} tickets hoy
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <!-- KPI 4: Alertas & Salud de Stock -->
                <Card class="relative overflow-hidden border border-border/60 bg-gradient-to-br from-card to-card/60 shadow-xs hover:border-amber-500/30 transition-all">
                    <CardHeader class="p-4.5 pb-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Alertas de Inventario</span>
                            <div class="h-8 w-8 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                <AlertTriangle class="h-4 w-4" />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-4.5 pt-0">
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-bold tracking-tight text-foreground font-mono" :class="(stats.total_low_stock_alerts + stats.total_out_of_stock) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600'">
                                {{ stats.total_low_stock_alerts + stats.total_out_of_stock }}
                            </span>
                            <span class="text-xs text-muted-foreground">ítems requieren atención</span>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-xs pt-2 border-t border-border/40">
                            <span class="text-rose-600 dark:text-rose-400 font-medium flex items-center gap-1">
                                <PackageX class="h-3 w-3" /> {{ stats.total_out_of_stock }} agotados
                            </span>
                            <span class="text-amber-600 dark:text-amber-400 font-medium flex items-center gap-1">
                                <AlertCircle class="h-3 w-3" /> {{ stats.total_low_stock_alerts }} stock bajo
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- ── 3. TOOLBAR & CONTROLS ───────────────────────────────────── -->
            <div class="bg-card/70 backdrop-blur-sm border border-border/60 rounded-xl p-4 shadow-xs space-y-3">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">

                    <!-- Search Input -->
                    <div class="relative flex-1 min-w-[240px] max-w-md">
                        <Search class="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                        <Input
                            v-model="search"
                            placeholder="Buscar por mercantil, mina, unidad o categoría…"
                            class="pl-9 h-9 text-xs bg-background/50 focus:bg-background"
                        />
                        <button
                            v-if="search"
                            @click="search = ''"
                            class="absolute right-2.5 top-2.5 text-muted-foreground hover:text-foreground"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Cascading Filters: Mine & Unit -->
                    <div class="flex items-center flex-wrap gap-2.5">
                        <!-- Mine Filter (Cascading parent) -->
                        <div class="w-44">
                            <Select
                                :model-value="mineFilter"
                                @update:model-value="handleToolbarMineChange"
                            >
                                <SelectTrigger class="h-9 text-xs">
                                    <SelectValue placeholder="Todas las minas" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all__">Todas las Minas ({{ availableMines.length }})</SelectItem>
                                    <SelectItem v-for="m in availableMines" :key="m.id" :value="String(m.id)">
                                        {{ m.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Unit Filter (Cascading child) -->
                        <div class="w-48">
                            <Select v-model="unitFilter">
                                <SelectTrigger class="h-9 text-xs">
                                    <SelectValue placeholder="Todas las unidades" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all__">Todas las Unidades ({{ toolbarUnits.length }})</SelectItem>
                                    <SelectItem v-for="u in toolbarUnits" :key="u.id" :value="String(u.id)">
                                        {{ u.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Sort By Select -->
                        <div class="w-44">
                            <Select v-model="sortBy">
                                <SelectTrigger class="h-9 text-xs">
                                    <SelectValue placeholder="Ordenar por..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="name">Nombre (A - Z)</SelectItem>
                                    <SelectItem value="valuation_desc">Mayor Valorización (S/.)</SelectItem>
                                    <SelectItem value="revenue_desc">Mayor Recaudación (S/.)</SelectItem>
                                    <SelectItem value="today_sales_desc">Mayores Ventas Hoy</SelectItem>
                                    <SelectItem value="products_desc">Más Productos SKU</SelectItem>
                                    <SelectItem value="stock_desc">Mayor Stock Total</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- View Mode Switcher -->
                        <div class="flex items-center border border-border/80 rounded-lg p-0.5 bg-muted/40">
                            <button
                                @click="viewMode = 'grid'"
                                :class="viewMode === 'grid' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'"
                                class="p-1.5 rounded-md transition-all text-xs flex items-center gap-1 font-medium"
                                title="Vista Cuadrícula"
                            >
                                <LayoutGrid class="h-3.5 w-3.5" />
                                <span class="hidden sm:inline">Tarjetas</span>
                            </button>
                            <button
                                @click="viewMode = 'table'"
                                :class="viewMode === 'table' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'"
                                class="p-1.5 rounded-md transition-all text-xs flex items-center gap-1 font-medium"
                                title="Vista Tabla"
                            >
                                <LayoutList class="h-3.5 w-3.5" />
                                <span class="hidden sm:inline">Tabla</span>
                            </button>
                            <button
                                @click="viewMode = 'analytics'"
                                :class="viewMode === 'analytics' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'"
                                class="p-1.5 rounded-md transition-all text-xs flex items-center gap-1 font-medium"
                                title="Vista Rendimiento & Comparativa"
                            >
                                <BarChart3 class="h-3.5 w-3.5" />
                                <span class="hidden sm:inline">Métricas</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Filter Tags & Badges -->
                <div class="flex items-center flex-wrap gap-1.5 pt-2 border-t border-border/30 text-xs">
                    <span class="text-muted-foreground mr-1 flex items-center gap-1 text-[11px] uppercase tracking-wider font-semibold">
                        <Filter class="h-3 w-3" /> Filtros rápidos:
                    </span>

                    <button
                        @click="statusFilter = 'all'; alertFilter = 'all'"
                        :class="statusFilter === 'all' && alertFilter === 'all' ? 'bg-indigo-600 text-white font-semibold' : 'bg-muted/60 text-muted-foreground hover:bg-muted'"
                        class="px-2.5 py-1 rounded-full text-xs transition-all flex items-center gap-1"
                    >
                        Todos ({{ props.mercantiles.length }})
                    </button>

                    <button
                        @click="statusFilter = 'active'; alertFilter = 'all'"
                        :class="statusFilter === 'active' && alertFilter === 'all' ? 'bg-emerald-600 text-white font-semibold' : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-500/20'"
                        class="px-2.5 py-1 rounded-full text-xs transition-all flex items-center gap-1"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Solo Activos ({{ stats.active_mercantiles }})
                    </button>

                    <button
                        @click="statusFilter = 'inactive'; alertFilter = 'all'"
                        :class="statusFilter === 'inactive' && alertFilter === 'all' ? 'bg-zinc-700 text-white font-semibold' : 'bg-zinc-500/10 text-zinc-700 dark:text-zinc-400 hover:bg-zinc-500/20'"
                        class="px-2.5 py-1 rounded-full text-xs transition-all flex items-center gap-1"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-zinc-400"></span>
                        Inactivos ({{ stats.inactive_mercantiles }})
                    </button>

                    <button
                        @click="alertFilter = 'low_stock'; statusFilter = 'all'"
                        :class="alertFilter === 'low_stock' ? 'bg-amber-600 text-white font-semibold' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400 hover:bg-amber-500/20'"
                        class="px-2.5 py-1 rounded-full text-xs transition-all flex items-center gap-1"
                    >
                        <AlertTriangle class="h-3 w-3" />
                        Stock Bajo ({{ props.mercantiles.filter(m => (m.low_stock_count ?? 0) > 0).length }})
                    </button>

                    <button
                        @click="alertFilter = 'out_of_stock'; statusFilter = 'all'"
                        :class="alertFilter === 'out_of_stock' ? 'bg-rose-600 text-white font-semibold' : 'bg-rose-500/10 text-rose-700 dark:text-rose-400 hover:bg-rose-500/20'"
                        class="px-2.5 py-1 rounded-full text-xs transition-all flex items-center gap-1"
                    >
                        <PackageX class="h-3 w-3" />
                        Agotados ({{ props.mercantiles.filter(m => (m.out_of_stock_count ?? 0) > 0).length }})
                    </button>

                    <button
                        @click="alertFilter = 'with_sales_today'; statusFilter = 'all'"
                        :class="alertFilter === 'with_sales_today' ? 'bg-indigo-600 text-white font-semibold' : 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 hover:bg-indigo-500/20'"
                        class="px-2.5 py-1 rounded-full text-xs transition-all flex items-center gap-1"
                    >
                        <TrendingUp class="h-3 w-3" />
                        Con Ventas Hoy ({{ props.mercantiles.filter(m => (m.today_sales_count ?? 0) > 0).length }})
                    </button>

                    <span class="ml-auto text-xs text-muted-foreground">
                        Mostrando <strong>{{ filteredMercantiles.length }}</strong> de {{ props.mercantiles.length }}
                    </span>
                </div>
            </div>

            <!-- ── 4. VIEW MODE 1: GRID CARDS ─────────────────────────────── -->
            <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <Card
                    v-for="m in filteredMercantiles"
                    :key="m.id"
                    class="group relative flex flex-col justify-between overflow-hidden border border-border/60 bg-card hover:border-indigo-500/40 hover:shadow-md transition-all duration-200"
                >
                    <!-- Card Top Banner -->
                    <div class="p-5 pb-4 space-y-4">
                        <!-- Top Header: Mine & Unit Badges + Status & Actions -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <Badge v-if="getMercantilLocation(m).mineName" variant="outline" class="font-semibold text-[11px] bg-indigo-50/50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800">
                                    {{ getMercantilLocation(m).mineName }}
                                </Badge>

                                <Badge variant="outline" class="font-normal text-xs bg-muted/60 text-foreground flex items-center gap-1">
                                    <Building2 class="h-3 w-3 text-muted-foreground" />
                                    {{ getMercantilLocation(m).unitName }}
                                </Badge>

                                <button
                                    @click="toggleActive(m)"
                                    class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2 py-0.5 rounded-full border transition-all"
                                    :class="m.is_active
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800'
                                        : 'bg-zinc-100 text-zinc-600 border-zinc-200 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700'"
                                    :title="m.is_active ? 'Click para desactivar' : 'Click para activar'"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="m.is_active ? 'bg-emerald-500' : 'bg-zinc-400'"></span>
                                    {{ m.is_active ? 'Activo' : 'Inactivo' }}
                                </button>
                            </div>

                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="h-7 w-7 text-muted-foreground hover:text-foreground">
                                        <MoreVertical class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-48">
                                    <DropdownMenuItem @click="openDetailDrawer(m, 'overview')" class="text-xs cursor-pointer">
                                        <Eye class="mr-2 h-3.5 w-3.5 text-indigo-600" /> Ver Detalle Completo
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="openDetailDrawer(m, 'catalog')" class="text-xs cursor-pointer">
                                        <Package class="mr-2 h-3.5 w-3.5 text-blue-600" /> Ver Catálogo Local
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="openDetailDrawer(m, 'sales')" class="text-xs cursor-pointer">
                                        <Receipt class="mr-2 h-3.5 w-3.5 text-emerald-600" /> Ver Historial Ventas
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem @click="exportMercantilInventory(m.id)" class="text-xs cursor-pointer">
                                        <FileSpreadsheet class="mr-2 h-3.5 w-3.5 text-emerald-600" /> Exportar Inventario Excel
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="exportMercantilSales(m.id)" class="text-xs cursor-pointer">
                                        <FileText class="mr-2 h-3.5 w-3.5 text-indigo-600" /> Exportar Ventas Mes
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem @click="openEditModal(m)" class="text-xs cursor-pointer">
                                        <Pencil class="mr-2 h-3.5 w-3.5 text-amber-600" /> Editar Mercantil
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="confirmDelete(m)" class="text-xs text-red-600 focus:text-red-600 cursor-pointer">
                                        <Trash2 class="mr-2 h-3.5 w-3.5" /> Eliminar
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        <!-- Name & Address -->
                        <div>
                            <h2
                                @click="openDetailDrawer(m, 'overview')"
                                class="text-lg font-bold text-foreground hover:text-indigo-600 dark:hover:text-indigo-400 cursor-pointer transition-colors line-clamp-1"
                            >
                                {{ m.name }}
                            </h2>
                            <div class="flex items-center gap-1.5 text-xs text-muted-foreground mt-1">
                                <MapPin class="h-3.5 w-3.5 shrink-0 text-muted-foreground/80" />
                                <span class="line-clamp-1">{{ m.address || 'Sin dirección registrada' }}</span>
                            </div>
                        </div>

                        <!-- Metrics Grid -->
                        <div class="grid grid-cols-3 gap-2 rounded-xl bg-muted/40 p-3 border border-border/40">
                            <!-- Valuación -->
                            <div class="space-y-0.5">
                                <span class="text-[10px] font-semibold uppercase text-muted-foreground block">Valorización</span>
                                <span class="text-xs font-bold text-foreground font-mono block truncate" :title="formatMoney(m.inventory_valuation)">
                                    {{ formatMoney(m.inventory_valuation) }}
                                </span>
                            </div>

                            <!-- Catálogo SKU -->
                            <div class="space-y-0.5 border-l border-border/40 pl-2">
                                <span class="text-[10px] font-semibold uppercase text-muted-foreground block">Catálogo</span>
                                <span class="text-xs font-bold text-foreground font-mono block">
                                    {{ m.products_count ?? 0 }} <span class="text-[10px] font-normal text-muted-foreground">SKUs</span>
                                </span>
                            </div>

                            <!-- Ventas Totales -->
                            <div class="space-y-0.5 border-l border-border/40 pl-2">
                                <span class="text-[10px] font-semibold uppercase text-muted-foreground block">Ventas</span>
                                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 font-mono block truncate" :title="formatMoney(m.total_revenue)">
                                    {{ formatMoney(m.total_revenue) }}
                                </span>
                            </div>
                        </div>

                        <!-- Today Sales Highlight & Stock Alerts -->
                        <div class="space-y-2">
                            <!-- Today ribbon -->
                            <div class="flex items-center justify-between text-xs px-2.5 py-1.5 rounded-lg bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-300">
                                <span class="flex items-center gap-1.5 text-[11px] font-medium">
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                    </span>
                                    Hoy: <strong>{{ formatMoney(m.today_sales_amount) }}</strong>
                                </span>
                                <span class="text-[11px] text-emerald-700 dark:text-emerald-400">
                                    {{ m.today_sales_count ?? 0 }} transacciones
                                </span>
                            </div>

                            <!-- Health alerts pill list -->
                            <div v-if="(m.low_stock_count ?? 0) > 0 || (m.out_of_stock_count ?? 0) > 0 || (m.expiring_batches_count ?? 0) > 0" class="flex items-center flex-wrap gap-1.5">
                                <Badge v-if="(m.out_of_stock_count ?? 0) > 0" variant="outline" class="text-[10px] bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800">
                                    <PackageX class="h-3 w-3 mr-1 text-rose-600" /> {{ m.out_of_stock_count }} agotados
                                </Badge>
                                <Badge v-if="(m.low_stock_count ?? 0) > 0" variant="outline" class="text-[10px] bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800">
                                    <AlertTriangle class="h-3 w-3 mr-1 text-amber-600" /> {{ m.low_stock_count }} stock bajo
                                </Badge>
                                <Badge v-if="(m.expiring_batches_count ?? 0) > 0" variant="outline" class="text-[10px] bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800">
                                    <Calendar class="h-3 w-3 mr-1 text-purple-600" /> {{ m.expiring_batches_count }} por vencer
                                </Badge>
                            </div>

                            <!-- Categories chip tags -->
                            <div v-if="m.categories && m.categories.length > 0" class="flex items-center flex-wrap gap-1">
                                <span class="text-[10px] text-muted-foreground mr-1">Rubros:</span>
                                <span
                                    v-for="cat in m.categories.slice(0, 3)"
                                    :key="cat"
                                    class="text-[10px] px-1.5 py-0.5 rounded bg-muted text-muted-foreground border border-border/40"
                                >
                                    {{ cat }}
                                </span>
                                <span v-if="m.categories.length > 3" class="text-[10px] text-muted-foreground">
                                    +{{ m.categories.length - 3 }} más
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer Actions -->
                    <div class="flex items-center justify-between gap-2 p-3 bg-muted/20 border-t border-border/40">
                        <Button
                            variant="default"
                            size="sm"
                            @click="openDetailDrawer(m, 'overview')"
                            class="flex-1 h-8 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white"
                        >
                            <Eye class="h-3.5 w-3.5 mr-1" /> Ver Detalle
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            as-child
                            class="h-8 text-xs font-medium border-border/80 hover:bg-accent"
                        >
                            <Link :href="route('pos.index')">
                                <ShoppingCart class="h-3.5 w-3.5 text-emerald-600" />
                                POS
                            </Link>
                        </Button>

                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 text-muted-foreground hover:text-foreground"
                            @click="openEditModal(m)"
                            title="Editar Mercantil"
                        >
                            <Pencil class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </Card>
            </div>

            <!-- ── 5. VIEW MODE 2: HIGH-DENSITY TABLE ─────────────────────── -->
            <div v-else-if="viewMode === 'table'" class="bg-card rounded-xl border border-border/60 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-muted/60 border-b border-border/60 text-muted-foreground uppercase text-[11px] font-semibold tracking-wider">
                            <tr>
                                <th class="p-3.5 pl-4">Mercantil</th>
                                <th class="p-3.5">Mina / Unidad</th>
                                <th class="p-3.5">Dirección</th>
                                <th class="p-3.5 text-center">Catálogo (SKUs)</th>
                                <th class="p-3.5 text-center">Stock Total</th>
                                <th class="p-3.5 text-right">Valorización</th>
                                <th class="p-3.5 text-right">Recaudación</th>
                                <th class="p-3.5 text-right">Ventas Hoy</th>
                                <th class="p-3.5 text-center">Estado</th>
                                <th class="p-3.5 pr-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr
                                v-for="m in filteredMercantiles"
                                :key="m.id"
                                class="hover:bg-muted/30 transition-colors group cursor-pointer"
                                @click="openDetailDrawer(m, 'overview')"
                            >
                                <td class="p-3.5 pl-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                                            <Store class="h-4 w-4" />
                                        </div>
                                        <div>
                                            <div class="font-bold text-foreground text-sm group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                                {{ m.name }}
                                            </div>
                                            <div v-if="(m.low_stock_count ?? 0) > 0 || (m.out_of_stock_count ?? 0) > 0" class="flex items-center gap-1 mt-0.5">
                                                <span v-if="(m.out_of_stock_count ?? 0) > 0" class="text-[10px] text-rose-600 font-medium">
                                                    {{ m.out_of_stock_count }} agotados
                                                </span>
                                                <span v-if="(m.out_of_stock_count ?? 0) > 0 && (m.low_stock_count ?? 0) > 0" class="text-zinc-300">•</span>
                                                <span v-if="(m.low_stock_count ?? 0) > 0" class="text-[10px] text-amber-600 font-medium">
                                                    {{ m.low_stock_count }} bajos
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-3.5">
                                    <div class="flex flex-col gap-0.5">
                                        <span v-if="getMercantilLocation(m).mineName" class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400">
                                            {{ getMercantilLocation(m).mineName }}
                                        </span>
                                        <span class="flex items-center gap-1 text-foreground font-medium">
                                            <Building2 class="h-3.5 w-3.5 text-muted-foreground" />
                                            {{ getMercantilLocation(m).unitName }}
                                        </span>
                                    </div>
                                </td>

                                <td class="p-3.5 max-w-[200px] truncate text-muted-foreground">
                                    {{ m.address || '—' }}
                                </td>

                                <td class="p-3.5 text-center font-mono font-medium">
                                    {{ m.products_count ?? 0 }}
                                </td>

                                <td class="p-3.5 text-center font-mono font-medium">
                                    {{ formatNumber(m.total_stock) }}
                                </td>

                                <td class="p-3.5 text-right font-mono font-semibold text-foreground">
                                    {{ formatMoney(m.inventory_valuation) }}
                                </td>

                                <td class="p-3.5 text-right font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ formatMoney(m.total_revenue) }}
                                </td>

                                <td class="p-3.5 text-right font-mono text-xs">
                                    <span v-if="(m.today_sales_amount ?? 0) > 0" class="font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ formatMoney(m.today_sales_amount) }}
                                    </span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>

                                <td class="p-3.5 text-center" @click.stop>
                                    <button
                                        @click="toggleActive(m)"
                                        class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full border transition-all"
                                        :class="m.is_active
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800'
                                            : 'bg-zinc-100 text-zinc-600 border-zinc-200 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700'"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="m.is_active ? 'bg-emerald-500' : 'bg-zinc-400'"></span>
                                        {{ m.is_active ? 'Activo' : 'Inactivo' }}
                                    </button>
                                </td>

                                <td class="p-3.5 pr-4 text-right" @click.stop>
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-7 w-7 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50"
                                            @click="openDetailDrawer(m, 'overview')"
                                            title="Ver Detalle"
                                        >
                                            <Eye class="h-3.5 w-3.5" />
                                        </Button>

                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-7 w-7 text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100"
                                            @click="openEditModal(m)"
                                            title="Editar"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                        </Button>

                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-7 w-7 text-red-500 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/50"
                                            @click="confirmDelete(m)"
                                            title="Eliminar"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── 6. VIEW MODE 3: COMPARATIVE ANALYTICS ──────────────────── -->
            <div v-else-if="viewMode === 'analytics'" class="space-y-6">
                <!-- Comparative Chart / Bars Matrix -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Ranking por Recaudación de Ventas -->
                    <Card class="border border-border/60">
                        <CardHeader class="p-4 pb-2">
                            <CardTitle class="text-sm font-bold flex items-center justify-between">
                                <span>Ranking por Recaudación Total</span>
                                <Badge variant="outline" class="font-mono text-xs text-emerald-600">
                                    {{ formatMoney(stats.total_revenue) }} total
                                </Badge>
                            </CardTitle>
                            <CardDescription class="text-xs">
                                Comparativa de ingresos brutos generados por punto de venta
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-4 pt-2 space-y-3">
                            <div
                                v-for="m in [...props.mercantiles].sort((a,b) => (b.total_revenue ?? 0) - (a.total_revenue ?? 0))"
                                :key="m.id"
                                class="space-y-1"
                            >
                                <div class="flex items-center justify-between text-xs font-medium">
                                    <span class="text-foreground truncate flex items-center gap-1.5">
                                        <Store class="h-3 w-3 text-indigo-500" />
                                        {{ m.name }}
                                        <span class="text-[10px] text-muted-foreground">({{ getMercantilLocation(m).unitName }})</span>
                                    </span>
                                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ formatMoney(m.total_revenue) }}
                                    </span>
                                </div>
                                <div class="h-2 w-full rounded-full bg-muted overflow-hidden">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-400 transition-all duration-500"
                                        :style="{ width: `${stats.total_revenue > 0 ? ((m.total_revenue ?? 0) / stats.total_revenue) * 100 : 0}%` }"
                                    ></div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Ranking por Valorización de Inventario -->
                    <Card class="border border-border/60">
                        <CardHeader class="p-4 pb-2">
                            <CardTitle class="text-sm font-bold flex items-center justify-between">
                                <span>Ranking por Capital en Inventario</span>
                                <Badge variant="outline" class="font-mono text-xs text-blue-600">
                                    {{ formatMoney(stats.total_inventory_value) }} total
                                </Badge>
                            </CardTitle>
                            <CardDescription class="text-xs">
                                Capital inmovilizado en existencias valorizadas al precio de venta
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-4 pt-2 space-y-3">
                            <div
                                v-for="m in [...props.mercantiles].sort((a,b) => (b.inventory_valuation ?? 0) - (a.inventory_valuation ?? 0))"
                                :key="m.id"
                                class="space-y-1"
                            >
                                <div class="flex items-center justify-between text-xs font-medium">
                                    <span class="text-foreground truncate flex items-center gap-1.5">
                                        <Package class="h-3 w-3 text-blue-500" />
                                        {{ m.name }}
                                        <span class="text-[10px] text-muted-foreground">({{ m.products_count }} SKUs)</span>
                                    </span>
                                    <span class="font-mono font-bold text-blue-600 dark:text-blue-400">
                                        {{ formatMoney(m.inventory_valuation) }}
                                    </span>
                                </div>
                                <div class="h-2 w-full rounded-full bg-muted overflow-hidden">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 transition-all duration-500"
                                        :style="{ width: `${stats.total_inventory_value > 0 ? ((m.inventory_valuation ?? 0) / stats.total_inventory_value) * 100 : 0}%` }"
                                    ></div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- ── EMPTY STATE ────────────────────────────────────────────── -->
            <div v-if="filteredMercantiles.length === 0" class="border border-dashed border-border/80 rounded-xl p-12 text-center bg-card/40">
                <div class="flex flex-col items-center justify-center gap-3 text-muted-foreground max-w-sm mx-auto">
                    <div class="h-12 w-12 rounded-xl bg-muted flex items-center justify-center">
                        <Store class="h-6 w-6 opacity-60" />
                    </div>
                    <h3 class="text-base font-semibold text-foreground">No se encontraron mercantiles</h3>
                    <p class="text-xs text-muted-foreground">
                        No hay puntos de venta que coincidan con los filtros o la búsqueda actual.
                    </p>
                    <div class="flex gap-2 mt-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="search = ''; mineFilter = '__all__'; unitFilter = '__all__'; statusFilter = 'all'; alertFilter = 'all'"
                            class="text-xs"
                        >
                            Limpiar Filtros
                        </Button>
                        <Button
                            size="sm"
                            @click="openCreateModal"
                            class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white"
                        >
                            <Plus class="h-3.5 w-3.5 mr-1" /> Crear Mercantil
                        </Button>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── 7. MODAL: CREATE / EDIT MERCANTIL (WITH MINE & CHILD UNITS) ── -->
        <Dialog v-model:open="showCreateEditModal">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-lg">
                        <Store class="h-5 w-5 text-indigo-600" />
                        {{ editingId ? 'Editar Mercantil' : 'Registrar Nuevo Mercantil' }}
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Selecciona la mina y su respectiva unidad para asociar este punto de venta.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="saveMercantil" class="space-y-4 py-2">
                    <!-- 1. Mina Selector (Parent) -->
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <Building2 class="h-3.5 w-3.5 text-indigo-600" />
                                Mina <span class="text-red-500">*</span>
                            </span>
                            <span v-if="modalSelectedMineId" class="text-[11px] font-normal text-muted-foreground">
                                {{ modalChildUnits.length }} {{ modalChildUnits.length === 1 ? 'unidad disponible' : 'unidades disponibles' }}
                            </span>
                        </Label>
                        <Select
                            :model-value="String(modalSelectedMineId)"
                            @update:model-value="handleModalMineChange"
                        >
                            <SelectTrigger class="h-9 text-xs">
                                <SelectValue placeholder="Seleccionar mina" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="m in availableMines" :key="m.id" :value="String(m.id)">
                                    {{ m.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- 2. Unidad Minera (Child Units strictly belonging to selected Mine) -->
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold flex items-center justify-between">
                            <span>Unidad Minera <span class="text-red-500">*</span></span>
                            <span v-if="!modalSelectedMineId" class="text-[10px] text-amber-600 dark:text-amber-400 font-normal">
                                Primero elige una mina
                            </span>
                        </Label>
                        <Select
                            v-model="form.unit_id"
                            :disabled="!modalSelectedMineId || modalChildUnits.length === 0"
                        >
                            <SelectTrigger class="h-9 text-xs">
                                <SelectValue :placeholder="!modalSelectedMineId ? 'Primero selecciona una mina' : modalChildUnits.length === 0 ? 'Sin unidades disponibles en esta mina' : 'Seleccionar unidad minera'" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="u in modalChildUnits" :key="u.id" :value="String(u.id)">
                                    {{ u.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="modalSelectedMineId && modalChildUnits.length === 0" class="text-[11px] text-amber-600 dark:text-amber-400">
                            Esta mina no tiene unidades registradas. Crea una unidad primero o selecciona otra mina.
                        </p>
                        <p v-if="form.errors.unit_id" class="text-xs text-red-500">{{ form.errors.unit_id }}</p>
                    </div>

                    <!-- 3. Nombre del Mercantil -->
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Nombre del Mercantil <span class="text-red-500">*</span></Label>
                        <Input
                            v-model="form.name"
                            placeholder="Ej. Mercantil Central - Campamento 1"
                            class="h-9 text-xs"
                            required
                        />
                        <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>

                    <!-- 4. Ubicación / Dirección Interna -->
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Ubicación / Dirección Interna</Label>
                        <Input
                            v-model="form.address"
                            placeholder="Ej. Frente al Comedor Principal, Módulo B"
                            class="h-9 text-xs"
                        />
                        <p v-if="form.errors.address" class="text-xs text-red-500">{{ form.errors.address }}</p>
                    </div>

                    <!-- 5. Estado Operativo Toggle -->
                    <div class="flex items-center justify-between rounded-lg border p-3 bg-muted/30">
                        <div class="space-y-0.5">
                            <Label class="text-xs font-semibold">Estado Operativo</Label>
                            <p class="text-[11px] text-muted-foreground">
                                Si está inactivo, no aparecerá disponible para ventas en el POS.
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="form.is_active = !form.is_active"
                            class="transition-opacity hover:opacity-80"
                        >
                            <ToggleRight v-if="form.is_active" class="h-7 w-7 text-emerald-500" />
                            <ToggleLeft v-else class="h-7 w-7 text-zinc-300 dark:text-zinc-600" />
                        </button>
                    </div>

                    <!-- Modal Actions -->
                    <DialogFooter class="pt-2 gap-2">
                        <Button type="button" variant="outline" size="sm" @click="closeCreateEditModal" class="text-xs">
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="form.processing || !form.unit_id"
                            class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white"
                        >
                            {{ form.processing ? 'Guardando…' : editingId ? 'Guardar Cambios' : 'Crear Mercantil' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ── 8. MODAL: CONFIRM DELETE ─────────────────────────────────── -->
        <Dialog v-model:open="showDeleteModal">
            <DialogContent class="sm:max-w-[420px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-red-600 text-base">
                        <AlertTriangle class="h-5 w-5" />
                        ¿Eliminar Mercantil?
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Esta acción eliminará el registro del mercantil <strong class="text-foreground">"{{ mercantilToDelete?.name }}"</strong>.
                        Si existen productos o ventas históricas asociadas, se recomienda únicamente desactivarlo.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="pt-3 gap-2">
                    <Button variant="outline" size="sm" @click="showDeleteModal = false" class="text-xs">
                        Cancelar
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        @click="executeDelete"
                        class="text-xs bg-red-600 hover:bg-red-700"
                    >
                        Sí, Eliminar Definitivamente
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── 9. DIALOG: COMPREHENSIVE DETAIL DRAWER / FULL VIEW ───────── -->
        <Dialog v-model:open="showDetailDrawer">
            <DialogContent class="sm:max-w-[850px] max-h-[90vh] flex flex-col p-0 overflow-hidden">
                <!-- Detail Header -->
                <div class="p-5 pb-3 border-b border-border/60 bg-muted/20">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
                                <Store class="h-5 w-5" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-xl font-bold text-foreground">{{ selectedMercantil?.name }}</h2>
                                    <Badge
                                        :variant="selectedMercantil?.is_active ? 'default' : 'secondary'"
                                        class="text-[10px] px-2 py-0"
                                        :class="selectedMercantil?.is_active ? 'bg-emerald-600' : 'bg-zinc-500'"
                                    >
                                        {{ selectedMercantil?.is_active ? 'Activo' : 'Inactivo' }}
                                    </Badge>
                                </div>
                                <div class="flex items-center gap-3 text-xs text-muted-foreground mt-0.5">
                                    <span v-if="selectedMercantil && getMercantilLocation(selectedMercantil).mineName" class="flex items-center gap-1 font-semibold text-indigo-600 dark:text-indigo-400">
                                        <Building2 class="h-3.5 w-3.5" /> Mina: {{ getMercantilLocation(selectedMercantil).mineName }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <Building2 class="h-3.5 w-3.5" /> Unidad: {{ selectedMercantil && getMercantilLocation(selectedMercantil).unitName }}
                                    </span>
                                    <span class="flex items-center gap-1" v-if="selectedMercantil?.address">
                                        <MapPin class="h-3.5 w-3.5" /> {{ selectedMercantil?.address }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="selectedMercantil && openEditModal(selectedMercantil)"
                                class="h-8 text-xs gap-1"
                            >
                                <Pencil class="h-3 w-3" /> Editar
                            </Button>
                        </div>
                    </div>

                    <!-- Detail Tabs Navigation -->
                    <div class="flex items-center gap-1 mt-4 border-b border-border/40 text-xs">
                        <button
                            @click="detailActiveTab = 'overview'"
                            :class="detailActiveTab === 'overview' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 border-b-2 font-bold' : 'text-muted-foreground hover:text-foreground'"
                            class="px-3 py-2 transition-all flex items-center gap-1.5 text-xs"
                        >
                            <Activity class="h-3.5 w-3.5" /> Resumen & KPIs
                        </button>
                        <button
                            @click="detailActiveTab = 'catalog'"
                            :class="detailActiveTab === 'catalog' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 border-b-2 font-bold' : 'text-muted-foreground hover:text-foreground'"
                            class="px-3 py-2 transition-all flex items-center gap-1.5 text-xs"
                        >
                            <Package class="h-3.5 w-3.5" /> Catálogo de Productos ({{ selectedMercantil?.products?.length ?? 0 }})
                        </button>
                        <button
                            @click="detailActiveTab = 'sales'"
                            :class="detailActiveTab === 'sales' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 border-b-2 font-bold' : 'text-muted-foreground hover:text-foreground'"
                            class="px-3 py-2 transition-all flex items-center gap-1.5 text-xs"
                        >
                            <Receipt class="h-3.5 w-3.5" /> Últimas Ventas ({{ selectedMercantil?.sales?.length ?? 0 }})
                        </button>
                        <button
                            @click="detailActiveTab = 'analytics'"
                            :class="detailActiveTab === 'analytics' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 border-b-2 font-bold' : 'text-muted-foreground hover:text-foreground'"
                            class="px-3 py-2 transition-all flex items-center gap-1.5 text-xs"
                        >
                            <FileSpreadsheet class="h-3.5 w-3.5" /> Exportaciones & Reportes
                        </button>
                    </div>
                </div>

                <!-- Detail Body (Scrollable) -->
                <div class="p-5 overflow-y-auto flex-1 max-h-[60vh] space-y-4">

                    <!-- ── TAB 1: OVERVIEW & KPIS ──────────────────────────── -->
                    <div v-if="detailActiveTab === 'overview'" class="space-y-5">
                        <!-- Key Figures Strip -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="rounded-xl border border-border/60 p-3 bg-muted/20">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground block">Valorización Inventario</span>
                                <span class="text-base font-bold font-mono text-foreground block mt-1">
                                    {{ formatMoney(selectedMercantil?.inventory_valuation) }}
                                </span>
                            </div>

                            <div class="rounded-xl border border-border/60 p-3 bg-muted/20">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground block">Recaudación Total</span>
                                <span class="text-base font-bold font-mono text-emerald-600 dark:text-emerald-400 block mt-1">
                                    {{ formatMoney(selectedMercantil?.total_revenue) }}
                                </span>
                            </div>

                            <div class="rounded-xl border border-border/60 p-3 bg-muted/20">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground block">Ventas Hoy</span>
                                <span class="text-base font-bold font-mono text-indigo-600 dark:text-indigo-400 block mt-1">
                                    {{ formatMoney(selectedMercantil?.today_sales_amount) }}
                                </span>
                                <span class="text-[10px] text-muted-foreground">{{ selectedMercantil?.today_sales_count ?? 0 }} tickets</span>
                            </div>

                            <div class="rounded-xl border border-border/60 p-3 bg-muted/20">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground block">Existencias Totales</span>
                                <span class="text-base font-bold font-mono text-foreground block mt-1">
                                    {{ formatNumber(selectedMercantil?.total_stock) }} <span class="text-xs font-normal text-muted-foreground">unids.</span>
                                </span>
                                <span class="text-[10px] text-muted-foreground">{{ selectedMercantil?.products_count }} SKUs</span>
                            </div>
                        </div>

                        <!-- Stock Health Overview -->
                        <div class="rounded-xl border border-border/60 p-4 bg-card space-y-3">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                                <span>Estado de Existencias y Alertas</span>
                                <span class="font-normal lowercase text-[11px]">
                                    {{ selectedMercantil?.products?.length ?? 0 }} productos registrados
                                </span>
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                                <div class="flex items-center gap-3 p-2.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20">
                                    <PackageCheck class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                    <div>
                                        <div class="text-sm font-bold text-foreground">
                                            {{ selectedMercantil?.active_products_count ?? 0 }}
                                        </div>
                                        <div class="text-[11px] text-muted-foreground">Productos Activos</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20">
                                    <AlertTriangle class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                                    <div>
                                        <div class="text-sm font-bold text-foreground">
                                            {{ selectedMercantil?.low_stock_count ?? 0 }}
                                        </div>
                                        <div class="text-[11px] text-muted-foreground">Stock Bajo (≤ 5 unids)</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 p-2.5 rounded-lg bg-rose-500/10 border border-rose-500/20">
                                    <PackageX class="h-5 w-5 text-rose-600 dark:text-rose-400" />
                                    <div>
                                        <div class="text-sm font-bold text-foreground">
                                            {{ selectedMercantil?.out_of_stock_count ?? 0 }}
                                        </div>
                                        <div class="text-[11px] text-muted-foreground">Agotados (0 stock)</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Categories tags in this store -->
                        <div v-if="detailCategories.length > 0" class="rounded-xl border border-border/60 p-4 bg-card space-y-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                Rubros y Categorías en este Mercantil
                            </h3>
                            <div class="flex items-center flex-wrap gap-1.5 pt-1">
                                <Badge
                                    v-for="cat in detailCategories"
                                    :key="cat"
                                    variant="outline"
                                    class="text-xs py-1 px-2.5 bg-muted/40 font-medium"
                                >
                                    <Tag class="h-3 w-3 mr-1 text-muted-foreground" />
                                    {{ cat }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <!-- ── TAB 2: LOCAL CATALOG ────────────────────────────── -->
                    <div v-else-if="detailActiveTab === 'catalog'" class="space-y-3">
                        <!-- Filters for products inside detail -->
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-2">
                            <div class="relative flex-1 w-full max-w-sm">
                                <Search class="text-muted-foreground absolute top-2.5 left-3 h-3.5 w-3.5" />
                                <Input
                                    v-model="productSearchInDetail"
                                    placeholder="Buscar producto por nombre, SKU o marca…"
                                    class="pl-8 h-8 text-xs"
                                />
                            </div>

                            <div class="w-full sm:w-48">
                                <Select v-model="productCategoryInDetail">
                                    <SelectTrigger class="h-8 text-xs">
                                        <SelectValue placeholder="Categoría" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__all__">Todas las categorías</SelectItem>
                                        <SelectItem v-for="c in detailCategories" :key="c" :value="c">
                                            {{ c }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <!-- Product Table -->
                        <div class="rounded-lg border border-border/60 overflow-hidden">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="bg-muted/60 text-muted-foreground uppercase text-[10px] font-semibold tracking-wider">
                                    <tr>
                                        <th class="p-2.5 pl-3">Producto / SKU</th>
                                        <th class="p-2.5">Categoría</th>
                                        <th class="p-2.5 text-right">Precio</th>
                                        <th class="p-2.5 text-center">Stock</th>
                                        <th class="p-2.5 text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/40">
                                    <tr v-for="prod in filteredDetailProducts" :key="prod.id" class="hover:bg-muted/30">
                                        <td class="p-2.5 pl-3">
                                            <div class="font-semibold text-foreground">{{ prod.name }}</div>
                                            <div class="text-[10px] text-muted-foreground">
                                                {{ prod.marca ? `${prod.marca} • ` : '' }}SKU: {{ prod.sku || '—' }}
                                            </div>
                                        </td>
                                        <td class="p-2.5 text-muted-foreground">
                                            {{ prod.category || 'Sin Categoría' }}
                                        </td>
                                        <td class="p-2.5 text-right font-mono font-semibold">
                                            {{ formatMoney(prod.price) }}
                                        </td>
                                        <td class="p-2.5 text-center font-mono font-bold">
                                            {{ prod.stock }}
                                        </td>
                                        <td class="p-2.5 text-center">
                                            <Badge
                                                variant="outline"
                                                class="text-[10px] px-2 py-0"
                                                :class="getStockStatus(prod.stock).color"
                                            >
                                                {{ getStockStatus(prod.stock).label }}
                                            </Badge>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredDetailProducts.length === 0">
                                        <td colspan="5" class="p-8 text-center text-muted-foreground">
                                            No se encontraron productos registrados en este mercantil.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ── TAB 3: SALES HISTORY ────────────────────────────── -->
                    <div v-if="detailActiveTab === 'sales'" class="space-y-3">
                        <div class="rounded-lg border border-border/60 overflow-hidden">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="bg-muted/60 text-muted-foreground uppercase text-[10px] font-semibold tracking-wider">
                                    <tr>
                                        <th class="p-2.5 pl-3">Fecha & Ticket</th>
                                        <th class="p-2.5">Comprador / DNI</th>
                                        <th class="p-2.5">Método de Pago</th>
                                        <th class="p-2.5">Condición</th>
                                        <th class="p-2.5 pr-3 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/40">
                                    <tr v-for="s in selectedMercantil?.sales ?? []" :key="s.id" class="hover:bg-muted/30">
                                        <td class="p-2.5 pl-3">
                                            <div class="font-semibold text-foreground font-mono">#{{ s.id }}</div>
                                            <div class="text-[10px] text-muted-foreground">{{ formatDate(s.date) }}</div>
                                        </td>
                                        <td class="p-2.5">
                                            <div class="font-medium text-foreground">
                                                {{ s.dinner?.name || (s.buyer_dni ? `DNI: ${s.buyer_dni}` : 'Público General') }}
                                            </div>
                                            <div v-if="s.dinner?.dni" class="text-[10px] text-muted-foreground">
                                                DNI: {{ s.dinner.dni }}
                                            </div>
                                        </td>
                                        <td class="p-2.5">
                                            <Badge
                                                variant="outline"
                                                class="text-[10px] px-1.5 py-0 flex items-center gap-1 w-fit"
                                                :class="paymentMethodLabel(s.payment_method).bg + ' ' + paymentMethodLabel(s.payment_method).text"
                                            >
                                                <component :is="paymentMethodLabel(s.payment_method).icon" class="h-3 w-3" />
                                                {{ paymentMethodLabel(s.payment_method).label }}
                                            </Badge>
                                        </td>
                                        <td class="p-2.5 capitalize text-muted-foreground">
                                            {{ s.payment_condition || 'contado' }}
                                        </td>
                                        <td class="p-2.5 pr-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                            {{ formatMoney(s.total) }}
                                        </td>
                                    </tr>
                                    <tr v-if="!selectedMercantil?.sales || selectedMercantil.sales.length === 0">
                                        <td colspan="5" class="p-8 text-center text-muted-foreground">
                                            No hay transacciones de venta registradas recientemente para este mercantil.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ── TAB 4: EXPORT & ACTIONS ─────────────────────────── -->
                    <div v-if="detailActiveTab === 'analytics'" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Card class="border border-border/60 p-4 space-y-3">
                                <div class="flex items-center gap-2 text-emerald-600 font-bold text-sm">
                                    <FileSpreadsheet class="h-4 w-4" />
                                    Descargar Inventario Actual
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Genera un archivo Excel (.xlsx) con el stock disponible, precios y lotes de este mercantil.
                                </p>
                                <Button
                                    size="sm"
                                    @click="exportMercantilInventory(selectedMercantil?.id)"
                                    class="w-full text-xs bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5"
                                >
                                    <Download class="h-3.5 w-3.5" /> Descargar Excel Inventario
                                </Button>
                            </Card>

                            <Card class="border border-border/60 p-4 space-y-3">
                                <div class="flex items-center gap-2 text-indigo-600 font-bold text-sm">
                                    <FileText class="h-4 w-4" />
                                    Reporte de Ventas del Mes
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Descarga el detalle consolidado de tickets emitidos durante el mes en curso.
                                </p>
                                <Button
                                    size="sm"
                                    @click="exportMercantilSales(selectedMercantil?.id)"
                                    class="w-full text-xs bg-indigo-600 hover:bg-indigo-700 text-white gap-1.5"
                                >
                                    <Download class="h-3.5 w-3.5" /> Descargar Excel Ventas
                                </Button>
                            </Card>
                        </div>
                    </div>
                </div>

                <!-- Detail Footer -->
                <div class="p-3 bg-muted/40 border-t border-border/60 flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">
                        ID Mercantil: <strong>#{{ selectedMercantil?.id }}</strong>
                    </span>
                    <Button variant="outline" size="sm" @click="showDetailDrawer = false" class="text-xs">
                        Cerrar
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
