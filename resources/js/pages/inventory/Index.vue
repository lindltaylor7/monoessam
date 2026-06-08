<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertCircle,
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    Box,
    Building2,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    ExternalLink,
    FileText,
    History,
    LayoutGrid,
    List,
    Loader2,
    Monitor,
    MoreHorizontal,
    Mountain,
    Package,
    Palette,
    Plus,
    Search,
    Settings2,
    Shirt,
    User,
    Utensils,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface StaffRef { id: number; name: string }

interface ComputerEquipment {
    id: number; name: string; brand: string | null; model: string | null;
    presentation: string | null; color: string | null; series: string | null;
    code: string | null; status: number;
    responsible: StaffRef | null;
}

interface KitchenEquipment {
    id: number; name: string; brand: string | null; model: string | null;
    size: string | null; color: string | null; series: string | null;
    code: string | null; status: number;
    responsible: StaffRef | null;
}

const props = defineProps<{
    colors: Array<{ id: number; name: string; hex_code?: string }>;
    cafes: Array<{ id: number; name: string; unit: { name: string; mine: { name: string } } }>;
    headquarters: Array<{ id: number; name: string; business_id: number; business: { id: number; name: string } }>;
    stocks: Array<{
        id: number;
        stockable_id: number;
        stockable_type: string;
        headquarter_id: number | null;
        cafe_id: number | null;
        unit_id: number | null;
        quantity: number;
        size: string | null;
        color_id: number | null;
        stockable: any;
        cafe?: { name: string };
        headquarter?: { name: string };
        unit?: { name: string; mine: { name: string } };
        color?: { id: number; name: string; hex_code: string };
    }>;
    businesses: Array<{ id: number; name: string }>;
    providers: Array<{ id: number; name: string }>;
    clothes: Array<{ id: number; name: string }>;
    epps: Array<{ id: number; name: string }>;
    units: Array<{ id: number; name: string; mine: { name: string } }>;
    transfers: Array<any>;
    computerEquipments: ComputerEquipment[];
    kitchenEquipments: KitchenEquipment[];
}>();

const activeTab = ref('clothes');
const viewMode = ref<'cards' | 'table'>('cards');
const searchQuery = ref('');
const selectedCafeId = ref('all');
const selectedHeadquarterId = ref('all');

// Filtrado de inventario (Polymorphic)
const filteredStocks = computed(() => {
    const filtered = props.stocks.filter((stock) => {
        const itemType = stock.stockable_type;

        if (activeTab.value === 'clothes' && itemType !== 'App\\Models\\Cloth') return false;
        if (activeTab.value === 'epps' && itemType !== 'App\\Models\\Epp') return false;
        if (activeTab.value === 'computer' && itemType !== 'App\\Models\\ComputerEquipment') return false;
        if (activeTab.value === 'kitchen' && itemType !== 'App\\Models\\KitchenEquipment') return false;
        if (activeTab.value === 'ingredients' && itemType !== 'App\\Models\\Ingredient') return false;

        // Search query
        const itemName = stock.stockable?.name?.toLowerCase() || '';
        const matchesSearch = itemName.includes(searchQuery.value.toLowerCase());

        // Location filters
        const matchesCafe = selectedCafeId.value === 'all' || String(stock.cafe_id) === selectedCafeId.value;
        const matchesHQ = selectedHeadquarterId.value === 'all' || String(stock.headquarter_id) === selectedHeadquarterId.value;

        return matchesSearch && matchesCafe && matchesHQ;
    });

    // Grouping logic to avoid duplicate cards per item
    const groups: Record<string, any> = {};
    filtered.forEach((stock) => {
        const key = `${stock.stockable_type}-${stock.stockable_id}`;
        if (!groups[key]) {
            groups[key] = {
                ...stock,
                key, // Unique key for expansion tracking
                total_quantity: 0,
                headquarter_names: new Set(),
                cafe_names: new Set(),
                sizes: {} as Record<string, any>,
            };
        }
        groups[key].total_quantity += Number(stock.quantity);
        if (stock.headquarter?.name) groups[key].headquarter_names.add(stock.headquarter.name);
        if (stock.cafe?.name) groups[key].cafe_names.add(stock.cafe.name);

        const sizeLabel = stock.size || 'Única';
        if (!groups[key].sizes[sizeLabel]) {
            groups[key].sizes[sizeLabel] = {
                label: sizeLabel,
                total: 0,
                colors: {} as Record<string, any>,
            };
        }
        groups[key].sizes[sizeLabel].total += Number(stock.quantity);

        const colorId = stock.color_id || 'no-color';
        if (!groups[key].sizes[sizeLabel].colors[colorId]) {
            groups[key].sizes[sizeLabel].colors[colorId] = {
                id: colorId,
                color: stock.color,
                quantity: 0,
                records: [],
            };
        }
        groups[key].sizes[sizeLabel].colors[colorId].quantity += Number(stock.quantity);
        groups[key].sizes[sizeLabel].colors[colorId].records.push(stock);
    });

    return Object.values(groups).map((g) => ({
        ...g,
        quantity: g.total_quantity,
        display_headquarter: Array.from(g.headquarter_names).join(', ') || 'N/A',
        display_cafe: Array.from(g.cafe_names).join(', ') || 'N/A',
        nestedSizes: Object.values(g.sizes).map((s: any) => ({
            ...s,
            nestedColors: Object.values(s.colors),
        })),
    }));
});

// Expanded state for nested rows
const expandedRows = ref(new Set<string>());
const toggleRow = (key: string) => {
    const newSet = new Set(expandedRows.value);
    if (newSet.has(key)) newSet.delete(key);
    else newSet.add(key);
    expandedRows.value = newSet;
};

const expandedSizeRows = ref(new Set<string>());
const toggleSizeRow = (itemKey: string, sizeLabel: string) => {
    const key = `${itemKey}-${sizeLabel}`;
    const newSet = new Set(expandedSizeRows.value);
    if (newSet.has(key)) newSet.delete(key);
    else newSet.add(key);
    expandedSizeRows.value = newSet;
};

// Estado para modales
const isAddStockOpen = ref(false);
const isNewItemOpen = ref(false);
const isNewColorOpen = ref(false);

// --- Sizes Modal Logic ---
const isSizesModalOpen = ref(false);
const isLoadingSizes = ref(false);
const selectedStockForSizes = ref<any>(null);
const stockSizes = ref<any[]>([]);
const sizeSearch = ref('');

const openSizesModal = (stock: any) => {
    selectedStockForSizes.value = stock;
    isSizesModalOpen.value = true;
    isLoadingSizes.value = true;
    stockSizes.value = [];
    sizeSearch.value = '';

    axios
        .get(route('inventory.stock.sizes', { id: stock.id }))
        .then((res) => {
            stockSizes.value = res.data;
        })
        .catch((err) => console.error(err))
        .finally(() => {
            isLoadingSizes.value = false;
        });
};

const filteredStockSizes = computed(() => {
    if (!stockSizes.value) return [];

    // Group sizes by location (Headquarter / Cafe)
    const grouped: Record<string, any> = {};

    stockSizes.value.forEach((item: any) => {
        const hqName = item.headquarter?.name || 'Sede Central / Almacén';
        const cafeName = item.cafe?.name || 'Principal';
        const groupKey = `${hqName} - ${cafeName}`;

        if (!grouped[groupKey]) {
            grouped[groupKey] = {
                title: groupKey,
                hq: hqName,
                cafe: cafeName,
                items: [],
            };
        }
        grouped[groupKey].items.push(item);
    });

    const s = sizeSearch.value.toLowerCase();

    return Object.values(grouped)
        .map((group: any) => {
            // Filter items within the group
            const filteredItems = group.items.filter((item: any) => {
                if (!s) return true;
                return (item.size && item.size.toLowerCase().includes(s)) || (item.color?.name && item.color.name.toLowerCase().includes(s));
            });

            return {
                ...group,
                items: filteredItems,
            };
        })
        .filter((group) => group.items.length > 0);
});

const isReturnModalOpen = ref(false);
const returnForm = ref({
    unit_id: '',
    items: [] as any[],
});

const openReturnModal = (transfer: any) => {
    returnForm.value = {
        unit_id: String(transfer.unit_id),
        items: transfer.items.map((i: any) => ({
            stockable_id: i.stockable_id,
            stockable_type: i.stockable_type,
            name: i.stockable?.name,
            quantity: i.quantity,
            size: i.size,
            color_id: i.color_id,
        })),
    };
    isReturnModalOpen.value = true;
};

const handleReturn = () => {
    router.post(route('inventory.transfer.return'), returnForm.value, {
        onSuccess: () => {
            isReturnModalOpen.value = false;
        },
        preserveScroll: true,
    });
};

const stockForm = ref({
    stockable_type: 'cloth',
    stockable_id: '',
    headquarter_id: 'none',
    cafe_id: '',
    quantity: 0,
    action: 'add' as 'add' | 'set',
});

// Search functionality for items in stock dialog
const itemSearchQuery = ref('');
const itemSearchResults = ref<Array<{ id: number; name: string }>>([]);
const isSearchingItems = ref(false);
const selectedItemName = ref('');

let searchTimeout: any = null;

const searchItems = async (query: string) => {
    if (!query) {
        itemSearchResults.value = [];
        return;
    }

    isSearchingItems.value = true;
    try {
        const response = await axios.get(route('inventory.items.search'), {
            params: {
                type: stockForm.value.stockable_type,
                query: query,
            },
        });
        itemSearchResults.value = response.data;
    } catch (e) {
        console.error('Error searching items', e);
    } finally {
        isSearchingItems.value = false;
    }
};

watch(itemSearchQuery, (newVal) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchItems(newVal);
    }, 400);
});

watch(
    () => stockForm.value.stockable_type,
    () => {
        itemSearchQuery.value = '';
        itemSearchResults.value = [];
        stockForm.value.stockable_id = '';
        selectedItemName.value = '';
    },
);

const selectItem = (item: { id: number; name: string }) => {
    stockForm.value.stockable_id = String(item.id);
    selectedItemName.value = item.name;
    itemSearchResults.value = [];
    itemSearchQuery.value = '';
};

const handleAddStock = () => {
    const data = { ...stockForm.value };
    if (data.headquarter_id === 'none') {
        data.headquarter_id = '';
    }

    router.post(route('inventory.update'), data, {
        onSuccess: () => {
            isAddStockOpen.value = false;
            resetStockForm();
        },

        onFinish: () => {
            // Force close dialog state if it gets stuck
            setTimeout(() => {
                isAddStockOpen.value = false;
            }, 100);
        },
        preserveScroll: true,
        preserveState: true,
    });
};

const resetStockForm = () => {
    stockForm.value = {
        stockable_type: 'cloth',
        stockable_id: '',
        headquarter_id: 'none',
        cafe_id: '',
        quantity: 0,
        action: 'add',
    };
    selectedItemName.value = '';
};

const itemForm = ref({
    type: 'computer',
    name: '',
    brand: '',
    model: '',
    description: '',
    presentation: '',
    color: '',
    size: '',
    current_type: '',
    series: '',
    manual: '',
    code: '',
    status: '',
});

const colorForm = ref({
    name: '',
    hex_code: '#000000',
});

const handleCreateItem = () => {
    router.post(route('inventory.items.store'), itemForm.value, {
        onSuccess: () => {
            isNewItemOpen.value = false;
            itemForm.value = {
                type: itemForm.value.type,
                name: '',
                brand: '',
                model: '',
                description: '',
                presentation: '',
                color: '',
                size: '',
                current_type: '',
                series: '',
                manual: '',
                code: '',
                status: '',
            };
        },
    });
};

const handleCreateColor = () => {
    router.post(route('inventory.colors.store'), colorForm.value, {
        onSuccess: () => {
            isNewColorOpen.value = false;
            colorForm.value = { name: '', hex_code: '#000000' };
        },
    });
};

const getStockStatus = (quantity: number) => {
    if (quantity <= 0) return { label: 'Sin Stock', variant: 'destructive' as const };
    if (quantity < 5) return { label: 'Crítico', variant: 'destructive' as const };
    if (quantity < 15) return { label: 'Bajo Stock', variant: 'secondary' as const };
    return { label: 'En Stock', variant: 'outline' as const };
};

const getItemIcon = (type: string) => {
    switch (type) {
        case 'clothes':
            return Shirt;
        case 'epps':
            return Box;
        case 'computer':
            return Monitor;
        case 'kitchen':
            return Utensils;
        case 'ingredients':
            return Package;
        default:
            return Package;
    }
};

// ── Equipment tab helpers ───────────────────────────────────────────────────
const EQUIPMENT_STATUSES = [
    { value: 0, label: 'Nuevo',   cls: 'bg-blue-100 text-blue-700 border-blue-200'        },
    { value: 1, label: 'Bueno',   cls: 'bg-green-100 text-green-700 border-green-200'     },
    { value: 2, label: 'Regular', cls: 'bg-yellow-100 text-yellow-700 border-yellow-200'  },
    { value: 3, label: 'Dañado',  cls: 'bg-red-100 text-red-700 border-red-200'           },
    { value: 4, label: 'Baja',    cls: 'bg-gray-100 text-gray-600 border-gray-200'        },
];

function equipmentStatusInfo(val: number) {
    return EQUIPMENT_STATUSES.find(s => s.value === val) ?? EQUIPMENT_STATUSES[0];
}

const filteredComputerEquipments = computed(() => {
    const q = searchQuery.value.toLowerCase();
    if (!q) return props.computerEquipments;
    return props.computerEquipments.filter(e =>
        [e.name, e.brand, e.model, e.code, e.series].some(f => f?.toLowerCase().includes(q))
    );
});

const filteredKitchenEquipments = computed(() => {
    const q = searchQuery.value.toLowerCase();
    if (!q) return props.kitchenEquipments;
    return props.kitchenEquipments.filter(e =>
        [e.name, e.brand, e.model, e.code, e.series].some(f => f?.toLowerCase().includes(q))
    );
});

// --- New Invoice Logic moved to Invoices/Index.vue ---
</script>

<template>
    <Head title="Gestión de Inventario" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Logística', href: route('logistics') },
            { title: 'Inventario', href: route('inventory.index') },
        ]"
    >
        <div class="flex h-full w-full flex-col gap-6 overflow-hidden p-4 sm:p-6">
            <!-- Header Section -->
            <div class="flex flex-none flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                <div class="min-w-0 flex-1">
                    <h1 class="flex items-center gap-3 text-2xl font-semibold tracking-tight">
                        <div class="bg-primary/10 rounded-xl p-2">
                            <Package class="text-primary h-8 w-8" />
                        </div>
                        <span>Gestión Multimodal de Inventario</span>
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">Control centralizado de suministros, equipos y materias primas</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Dialog v-model:open="isNewColorOpen">
                        <DialogTrigger as-child>
                            <Button variant="outline" size="sm" class="gap-2 shadow-sm">
                                <Palette class="h-4 w-4" />
                                Colores
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Catálogo de Colores</DialogTitle>
                                <DialogDescription>Defina colores para la clasificación de prendas</DialogDescription>
                            </DialogHeader>
                            <div class="grid gap-4 py-4">
                                <div class="grid gap-2">
                                    <Label>Nombre del Color</Label>
                                    <Input v-model="colorForm.name" placeholder="Ej: Azul Corporativo" />
                                </div>
                                <div class="grid gap-2">
                                    <Label>Selección Visual</Label>
                                    <div class="flex gap-2">
                                        <Input v-model="colorForm.hex_code" type="color" class="h-10 w-12 cursor-pointer p-1" />
                                        <Input v-model="colorForm.hex_code" placeholder="#000000" class="flex-1" />
                                    </div>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button @click="handleCreateColor" :disabled="!colorForm.name" class="w-full sm:w-auto">Guardar Color</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <Button
                        @click="router.visit(route('inventory.units.index'))"
                        size="sm"
                        variant="outline"
                        class="gap-2 border-emerald-200 bg-emerald-50 font-bold text-emerald-700 shadow-sm hover:bg-emerald-100"
                    >
                        <Mountain class="h-4 w-4" />
                        Stock por Unidades
                    </Button>

                    <Button
                        @click="router.visit(route('inventory.invoices.index'))"
                        size="sm"
                        variant="outline"
                        class="gap-2 border-indigo-200 bg-indigo-50 font-bold text-indigo-700 shadow-sm hover:bg-indigo-100"
                    >
                        <FileText class="h-4 w-4" />
                        Gestión de Facturas y Proveedores
                    </Button>

                    <Dialog v-model:open="isNewItemOpen">
                        <DialogTrigger as-child>
                            <Button variant="outline" size="sm" class="gap-2 shadow-sm">
                                <Plus class="h-4 w-4" />
                                Nuevo Equipo
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-[550px]">
                            <DialogHeader>
                                <DialogTitle>Registro de Nuevo Activo</DialogTitle>
                                <DialogDescription>Complete los datos del equipo para ingresarlo al catálogo</DialogDescription>
                            </DialogHeader>
                            <div class="grid gap-6 py-4">
                                <div class="grid gap-2">
                                    <Label>Tipo de Equipo</Label>
                                    <Select v-model="itemForm.type">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="computer">Equipo Informático</SelectItem>
                                            <SelectItem value="kitchen">Equipo de Cocina</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label>Nombre / Modelo</Label>
                                        <Input v-model="itemForm.name" placeholder="Ej: Laptop Dell G15" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>Marca</Label>
                                        <Input v-model="itemForm.brand" placeholder="Ej: Dell" />
                                    </div>
                                </div>

                                <div v-if="itemForm.type === 'computer'" class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label>Presentación</Label>
                                        <Input v-model="itemForm.presentation" placeholder="Ej: Caja" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>Color de Chasis</Label>
                                        <Input v-model="itemForm.color" placeholder="Ej: Gris Espacial" />
                                    </div>
                                </div>

                                <div v-if="itemForm.type === 'kitchen'" class="grid gap-4">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="grid gap-2">
                                            <Label>Tamaño / Capacidad</Label>
                                            <Input v-model="itemForm.size" placeholder="Ej: 50 Litros / Grande" />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label>Color</Label>
                                            <Input v-model="itemForm.color" placeholder="Ej: Acero Inox" />
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="grid gap-2">
                                            <Label>Tipo de Corriente</Label>
                                            <Input v-model="itemForm.current_type" placeholder="Ej: 220V / Trifásico" />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label>Serie</Label>
                                            <Input v-model="itemForm.series" placeholder="Ej: SN-123456" />
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="grid gap-2">
                                            <Label>Instructivo</Label>
                                            <Input v-model="itemForm.manual" placeholder="Ej: Físico / Digital" />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label>Código</Label>
                                            <Input v-model="itemForm.code" placeholder="Ej: COC-001" />
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>Estado</Label>
                                        <Select v-model="itemForm.status">
                                            <SelectTrigger><SelectValue placeholder="Seleccionar estado" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="nuevo">Nuevo</SelectItem>
                                                <SelectItem value="bueno">Bueno</SelectItem>
                                                <SelectItem value="regular">Regular</SelectItem>
                                                <SelectItem value="mantenimiento">En Mantenimiento</SelectItem>
                                                <SelectItem value="baja">De Baja</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <Label>Descripción Técnica</Label>
                                    <textarea
                                        v-model="itemForm.description"
                                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[80px] w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                        placeholder="Especificaciones adicionales..."
                                    ></textarea>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button @click="handleCreateItem" :disabled="!itemForm.name" class="w-full">Registrar en Catálogo</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <Dialog v-model:open="isAddStockOpen">
                        <DialogTrigger as-child>
                            <Button size="sm" class="shadow-primary/20 gap-2 shadow-lg">
                                <Plus class="h-4 w-4" />
                                Cargar Stock
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-[500px]">
                            <DialogHeader>
                                <DialogTitle>Actualización de Existencias</DialogTitle>
                                <DialogDescription>Incremente o fije las cantidades en inventario</DialogDescription>
                            </DialogHeader>
                            <div class="grid gap-5 py-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label>Categoría</Label>
                                        <Select v-model="stockForm.stockable_type">
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="cloth">Ropa</SelectItem>
                                                <SelectItem value="computer">Informática</SelectItem>
                                                <SelectItem value="kitchen">Cocina</SelectItem>
                                                <SelectItem value="ingredient">Ingredientes</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="relative grid gap-2">
                                        <Label>Buscar Item</Label>
                                        <div class="relative">
                                            <Search class="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                            <Input v-model="itemSearchQuery" placeholder="Escribe para buscar..." class="pl-9" />
                                            <div v-if="isSearchingItems" class="absolute top-2.5 right-2.5">
                                                <Loader2 class="text-primary h-4 w-4 animate-spin" />
                                            </div>
                                        </div>

                                        <!-- Search Results Dropdown -->
                                        <div
                                            v-if="itemSearchResults.length > 0"
                                            class="absolute top-full z-50 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border bg-white shadow-xl"
                                        >
                                            <div
                                                v-for="item in itemSearchResults"
                                                :key="item.id"
                                                @click="selectItem(item)"
                                                class="flex cursor-pointer items-center justify-between border-b px-4 py-2 text-sm transition-colors last:border-none hover:bg-slate-50"
                                            >
                                                <span>{{ item.name }}</span>
                                                <div v-if="stockForm.stockable_id === String(item.id)" class="text-primary">
                                                    <Check class="h-4 w-4" />
                                                </div>
                                            </div>
                                        </div>

                                        <div v-if="selectedItemName" class="text-primary mt-1 flex items-center gap-1.5 text-xs font-bold">
                                            <Check class="h-3.5 w-3.5" />
                                            Seleccionado: {{ selectedItemName }}
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label>Sede (Opcional)</Label>
                                        <Select v-model="stockForm.headquarter_id">
                                            <SelectTrigger><SelectValue placeholder="General" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">Ninguna (General)</SelectItem>
                                                <SelectItem v-for="hq in headquarters" :key="hq.id" :value="String(hq.id)"
                                                    >{{ hq.business.name }} - {{ hq.name }}</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label>Café / Unidad</Label>
                                        <Select v-model="stockForm.cafe_id">
                                            <SelectTrigger><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="cafe in cafes" :key="cafe.id" :value="String(cafe.id)"
                                                    >{{ cafe.name }} - {{ cafe.unit.name }}</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label>Cantidad</Label>
                                        <Input type="number" v-model="stockForm.quantity" min="0" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>Acción</Label>
                                        <Select v-model="stockForm.action">
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="add">Sumar al stock</SelectItem>
                                                <SelectItem value="set">Fijar cantidad</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button @click="handleAddStock" :disabled="!stockForm.stockable_id" class="shadow-primary/30 w-full shadow-lg">
                                    Confirmar Operación
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <!-- Dashboard Stats Summary -->
            <div class="grid flex-none grid-cols-2 gap-4 md:grid-cols-4">
                <Card class="bg-card">
                    <CardContent class="flex items-center gap-4 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50">
                            <Box class="h-5 w-5 text-indigo-600" />
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs font-semibold uppercase">Items Registrados</p>
                            <p class="text-xl font-bold">{{ stocks.length }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="bg-card">
                    <CardContent class="flex items-center gap-4 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50">
                            <AlertCircle class="h-5 w-5 text-rose-600" />
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs font-semibold uppercase">Sin Stock</p>
                            <p class="text-xl font-bold">{{ stocks.filter((s) => s.quantity <= 0).length }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="bg-card">
                    <CardContent class="flex items-center gap-4 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50">
                            <Building2 class="h-5 w-5 text-amber-600" />
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs font-semibold uppercase">Sedes Activas</p>
                            <p class="text-xl font-bold">{{ headquarters.length }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="bg-card">
                    <CardContent class="flex items-center gap-4 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50">
                            <Settings2 class="h-5 w-5 text-emerald-600" />
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs font-semibold uppercase">Existencias Totales</p>
                            <p class="text-xl font-bold">{{ stocks.reduce((acc, s) => acc + Number(s.quantity), 0) }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Tabs and Filters Section -->
            <div class="flex flex-1 flex-col gap-4 overflow-hidden">
                <div
                    class="bg-card flex flex-none flex-col items-start justify-between gap-4 rounded-2xl border p-3 shadow-sm sm:flex-row sm:items-center"
                >
                    <Tabs v-model="activeTab" class="w-full sm:w-auto">
                        <TabsList class="bg-slate-100 p-1">
                            <TabsTrigger value="clothes" class="gap-2 rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm">
                                <Shirt class="h-4 w-4" />
                                <span class="hidden sm:inline">Ropa</span>
                            </TabsTrigger>
                            <TabsTrigger value="epps" class="gap-2 rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm">
                                <Box class="h-4 w-4" />
                                <span class="hidden sm:inline">EPPs</span>
                            </TabsTrigger>
                            <TabsTrigger value="computer" class="gap-2 rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm">
                                <Monitor class="h-4 w-4" />
                                <span class="hidden sm:inline">IT</span>
                            </TabsTrigger>
                            <TabsTrigger value="kitchen" class="gap-2 rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm">
                                <Utensils class="h-4 w-4" />
                                <span class="hidden sm:inline">Cocina</span>
                            </TabsTrigger>
                            <TabsTrigger value="ingredients" class="gap-2 rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm">
                                <Box class="h-4 w-4" />
                                <span class="hidden sm:inline">Insumos</span>
                            </TabsTrigger>
                            <!--  <TabsTrigger value="units_transfers" class="gap-2 rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm bg-indigo-50/50">
                                <Truck class="h-4 w-4 text-indigo-600" />
                                <span class="hidden sm:inline text-indigo-700 font-bold">Envíos a Unidades</span>
                            </TabsTrigger> -->
                        </TabsList>
                    </Tabs>

                    <div class="flex w-full flex-wrap items-center gap-3 sm:w-auto">
                        <!-- View Toggle -->
                        <div class="mr-2 flex items-center rounded-xl bg-slate-100 p-1">
                            <button
                                @click="viewMode = 'cards'"
                                :class="[
                                    'rounded-lg p-1.5 transition-all',
                                    viewMode === 'cards' ? 'text-primary bg-white shadow-sm' : 'text-slate-400 hover:text-slate-600',
                                ]"
                                title="Vista Cuadrícula"
                            >
                                <LayoutGrid class="h-4 w-4" />
                            </button>
                            <button
                                @click="viewMode = 'table'"
                                :class="[
                                    'rounded-lg p-1.5 transition-all',
                                    viewMode === 'table' ? 'text-primary bg-white shadow-sm' : 'text-slate-400 hover:text-slate-600',
                                ]"
                                title="Vista Tabla"
                            >
                                <List class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="relative flex-1 sm:w-64">
                            <Search class="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Buscar item..."
                                class="h-10 rounded-xl border-none bg-slate-50 pl-9 focus-visible:ring-1"
                            />
                        </div>

                        <div class="flex gap-2">
                            <Select v-model="selectedHeadquarterId">
                                <SelectTrigger class="h-10 w-[140px] rounded-xl">
                                    <SelectValue placeholder="Sedes" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas las Sedes</SelectItem>
                                    <SelectItem v-for="hq in headquarters" :key="hq.id" :value="String(hq.id)"
                                        >{{ hq.name }} - {{ hq.business.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>

                            <Select v-model="selectedCafeId">
                                <SelectTrigger class="h-10 w-[140px] rounded-xl">
                                    <SelectValue placeholder="Cafés" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos los Comedores</SelectItem>
                                    <SelectItem v-for="cafe in cafes" :key="cafe.id" :value="String(cafe.id)"
                                        >{{ cafe.name }} - {{ cafe.unit.name }} - {{ cafe.unit.mine.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                <!-- Content Grid / Table -->
                <div class="custom-scrollbar flex-1 overflow-y-auto pr-2">

                    <!-- ── Equipos IT ───────────────────────────────────── -->
                    <template v-if="activeTab === 'computer'">
                        <div class="mb-3 flex items-center justify-between rounded-xl border border-blue-100 bg-blue-50 px-4 py-2.5">
                            <div class="flex items-center gap-2 text-sm font-medium text-blue-700">
                                <Monitor class="h-4 w-4" />
                                {{ filteredComputerEquipments.length }} equipos tecnológicos registrados
                            </div>
                            <a :href="route('equipments.index')" class="flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-800">
                                Gestionar equipos <ExternalLink class="h-3.5 w-3.5" />
                            </a>
                        </div>
                        <div v-if="filteredComputerEquipments.length === 0" class="bg-muted/20 flex h-48 flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed">
                            <Monitor class="h-8 w-8 text-slate-300" />
                            <p class="text-sm font-semibold text-slate-400">Sin equipos tecnológicos</p>
                        </div>
                        <div v-else class="bg-card mb-6 overflow-hidden rounded-2xl border shadow-sm">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-muted/50 hover:bg-muted/50">
                                        <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Código</TableHead>
                                        <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Equipo</TableHead>
                                        <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Marca / Modelo</TableHead>
                                        <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">N° Serie</TableHead>
                                        <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Estado</TableHead>
                                        <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Responsable</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="eq in filteredComputerEquipments" :key="eq.id" class="hover:bg-muted/30 transition-colors">
                                        <TableCell class="font-mono text-xs text-slate-400">{{ eq.code || '—' }}</TableCell>
                                        <TableCell>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-foreground">{{ eq.name }}</span>
                                                <span v-if="eq.presentation" class="text-[11px] text-slate-400">{{ eq.presentation }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-sm text-slate-600">
                                            {{ [eq.brand, eq.model].filter(Boolean).join(' · ') || '—' }}
                                        </TableCell>
                                        <TableCell class="font-mono text-xs text-slate-400">{{ eq.series || '—' }}</TableCell>
                                        <TableCell>
                                            <span :class="['inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold', equipmentStatusInfo(eq.status).cls]">
                                                {{ equipmentStatusInfo(eq.status).label }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <span v-if="eq.responsible" class="flex items-center gap-1.5 text-sm text-slate-700">
                                                <CheckCircle2 class="h-3.5 w-3.5 text-green-500" /> {{ eq.responsible.name }}
                                            </span>
                                            <span v-else class="flex items-center gap-1 text-xs text-slate-400">
                                                <AlertTriangle class="h-3.5 w-3.5 text-amber-400" /> Sin asignar
                                            </span>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </template>

                    <!-- ── Equipos Cocina ────────────────────────────────── -->
                    <template v-else-if="activeTab === 'kitchen'">
                        <div class="mb-3 flex items-center justify-between rounded-xl border border-orange-100 bg-orange-50 px-4 py-2.5">
                            <div class="flex items-center gap-2 text-sm font-medium text-orange-700">
                                <Utensils class="h-4 w-4" />
                                {{ filteredKitchenEquipments.length }} equipos de cocina registrados
                            </div>
                            <a :href="route('equipments.index')" class="flex items-center gap-1 text-xs font-semibold text-orange-600 hover:text-orange-800">
                                Gestionar equipos <ExternalLink class="h-3.5 w-3.5" />
                            </a>
                        </div>
                        <div v-if="filteredKitchenEquipments.length === 0" class="bg-muted/20 flex h-48 flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed">
                            <Utensils class="h-8 w-8 text-slate-300" />
                            <p class="text-sm font-semibold text-slate-400">Sin equipos de cocina</p>
                        </div>
                        <div v-else class="bg-card mb-6 overflow-hidden rounded-2xl border shadow-sm">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-muted/50 hover:bg-muted/50">
                                        <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Código</TableHead>
                                        <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Equipo</TableHead>
                                        <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Marca / Modelo</TableHead>
                                        <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">N° Serie</TableHead>
                                        <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Estado</TableHead>
                                        <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Responsable</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="eq in filteredKitchenEquipments" :key="eq.id" class="hover:bg-muted/30 transition-colors">
                                        <TableCell class="font-mono text-xs text-slate-400">{{ eq.code || '—' }}</TableCell>
                                        <TableCell>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-foreground">{{ eq.name }}</span>
                                                <span v-if="eq.size" class="text-[11px] text-slate-400">{{ eq.size }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-sm text-slate-600">
                                            {{ [eq.brand, eq.model].filter(Boolean).join(' · ') || '—' }}
                                        </TableCell>
                                        <TableCell class="font-mono text-xs text-slate-400">{{ eq.series || '—' }}</TableCell>
                                        <TableCell>
                                            <span :class="['inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold', equipmentStatusInfo(eq.status).cls]">
                                                {{ equipmentStatusInfo(eq.status).label }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <span v-if="eq.responsible" class="flex items-center gap-1.5 text-sm text-slate-700">
                                                <CheckCircle2 class="h-3.5 w-3.5 text-green-500" /> {{ eq.responsible.name }}
                                            </span>
                                            <span v-else class="flex items-center gap-1 text-xs text-slate-400">
                                                <AlertTriangle class="h-3.5 w-3.5 text-amber-400" /> Sin asignar
                                            </span>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </template>

                    <!-- ── Stock: Ropa / EPPs / Insumos ─────────────────── -->
                    <template v-else>
                    <div
                        v-if="filteredStocks.length === 0"
                        class="bg-muted/20 flex h-64 flex-col items-center justify-center rounded-3xl border-2 border-dashed"
                    >
                        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100">
                            <Package class="h-8 w-8 text-slate-300" />
                        </div>
                        <p class="text-lg font-semibold text-muted-foreground">Sin resultados</p>
                        <p class="mt-1 text-sm text-slate-400">No hay existencias registradas para esta categoría</p>
                    </div>

                    <template v-else>
                        <!-- Cards View -->
                        <div v-if="viewMode === 'cards'" class="grid grid-cols-1 gap-5 pb-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <Card
                                v-for="item in filteredStocks"
                                :key="item.id"
                                class="bg-card group flex flex-col overflow-hidden rounded-2xl border transition-all duration-300 hover:shadow-lg"
                            >
                                <div class="h-1.5 w-full bg-slate-100">
                                    <div v-if="item.stockable_type.includes('Cloth')" class="bg-primary/40 h-full"></div>
                                    <div v-else-if="item.stockable_type.includes('Computer')" class="h-full bg-blue-400"></div>
                                    <div v-else-if="item.stockable_type.includes('Kitchen')" class="h-full bg-orange-400"></div>
                                    <div v-else class="h-full bg-emerald-400"></div>
                                </div>

                                <CardHeader class="p-5 pb-3">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex gap-3">
                                            <div
                                                :class="[
                                                    'flex size-10 flex-shrink-0 items-center justify-center self-start rounded-xl p-2 shadow-sm transition-transform group-hover:scale-110',
                                                    activeTab === 'clothes'
                                                        ? 'bg-indigo-50 text-indigo-600'
                                                        : activeTab === 'epps'
                                                          ? 'bg-amber-50 text-amber-600'
                                                          : activeTab === 'computer'
                                                            ? 'bg-blue-50 text-blue-600'
                                                            : activeTab === 'kitchen'
                                                              ? 'bg-orange-50 text-orange-600'
                                                              : 'bg-emerald-50 text-emerald-600',
                                                ]"
                                            >
                                                <component :is="getItemIcon(activeTab)" class="h-5 w-5" />
                                            </div>
                                            <div class="min-w-0">
                                                <CardTitle
                                                    class="line-clamp-2 flex min-h-[40px] items-center text-[14px] leading-tight font-black break-words text-foreground"
                                                    >{{ item.stockable?.name }}</CardTitle
                                                >
                                                <CardDescription class="mt-0.5 flex items-center gap-1.5 text-xs whitespace-normal text-muted-foreground">
                                                    <template v-if="activeTab === 'clothes'">
                                                        <Palette class="h-3 w-3" /> Prendas de Personal
                                                    </template>
                                                    <template v-else-if="activeTab === 'epps'">
                                                        <Box class="h-3 w-3" /> Elemento de Protección
                                                    </template>
                                                    <template v-else-if="activeTab === 'computer'">
                                                        {{ item.stockable?.brand }} | {{ item.stockable?.model || 'S/M' }}
                                                    </template>
                                                    <template v-else-if="activeTab === 'kitchen'">
                                                        {{ item.stockable?.brand }} | {{ item.stockable?.size }}
                                                        <span v-if="item.stockable?.code" class="ml-1 font-mono text-[10px] text-slate-400"
                                                            >#{{ item.stockable?.code }}</span
                                                        >
                                                    </template>
                                                    <template v-else> Insumo / Ingrediente </template>
                                                </CardDescription>
                                            </div>
                                        </div>
                                        <Badge
                                            :variant="getStockStatus(item.quantity).variant"
                                            class="rounded-full border-none px-2.5 text-[10px] font-bold uppercase shadow-none"
                                        >
                                            {{ getStockStatus(item.quantity).label }}
                                        </Badge>
                                    </div>
                                </CardHeader>

                                <CardContent class="flex-1 p-5 pt-0">
                                    <div
                                        class="mt-4 flex items-center justify-between rounded-2xl border border-transparent bg-slate-50 p-4 transition-colors group-hover:border-slate-200 group-hover:bg-slate-100/50"
                                    >
                                        <div>
                                            <p class="mb-1 text-[10px] font-bold tracking-widest text-slate-400 uppercase">Stock Disponible</p>
                                            <div class="flex items-baseline gap-1.5 text-foreground">
                                                <span class="text-3xl leading-none font-black">{{ item.quantity }}</span>
                                                <span class="text-xs font-bold text-slate-400 uppercase">Unidades</span>
                                            </div>
                                        </div>
                                        <div
                                            @click="openSizesModal(item)"
                                            class="flex cursor-pointer flex-col items-end gap-1 transition-opacity hover:opacity-80"
                                        >
                                            <div
                                                v-if="item.quantity > 0"
                                                class="flex items-center gap-1 rounded-full border border-emerald-100 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-600"
                                            >
                                                <ArrowUpRight class="h-3 w-3" /> ACTIVO
                                            </div>
                                            <div
                                                v-else
                                                class="flex items-center gap-1 rounded-full border border-rose-100 bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-600"
                                            >
                                                <ArrowDownRight class="h-3 w-3" /> AGOTADO
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-5 space-y-2.5">
                                        <div class="flex items-center justify-between text-[11px]">
                                            <span class="flex items-center gap-1.5 font-medium text-slate-400"
                                                ><Building2 class="h-3 w-3" /> Gestión / Ubicación</span
                                            >
                                            <span class="max-w-[120px] truncate font-bold text-slate-700">{{ item.display_headquarter }}</span>
                                        </div>
                                    </div>
                                </CardContent>

                                <div
                                    class="flex items-center justify-between border-t px-5 py-4 transition-colors group-hover:bg-muted/30"
                                >
                                    <div class="flex -space-x-1.5 overflow-hidden">
                                        <div
                                            class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-indigo-100 text-[8px] font-bold text-indigo-600"
                                        >
                                            HQ
                                        </div>
                                        <div
                                            class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-amber-100 text-[8px] font-bold text-amber-600"
                                        >
                                            CF
                                        </div>
                                    </div>
                                    <button
                                        @click="openSizesModal(item)"
                                        class="text-primary flex items-center gap-1 text-[10px] font-black tracking-tighter uppercase transition-all hover:gap-2"
                                    >
                                        AUDITAR STOCK <ArrowUpRight class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </Card>
                        </div>

                        <!-- Table View -->
                        <div
                            v-else-if="viewMode === 'table' && activeTab !== 'units_transfers'"
                            class="bg-card mb-6 overflow-hidden rounded-2xl border shadow-sm"
                        >
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-muted/50 hover:bg-muted/50">
                                        <TableHead class="w-[300px] text-[10px] font-bold text-muted-foreground uppercase">Item / Catálogo</TableHead>
                                        <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Atributos</TableHead>
                                        <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Gestión / Ubicación</TableHead>
                                        <TableHead class="text-center text-[10px] font-bold text-muted-foreground uppercase">Disponible</TableHead>
                                        <TableHead class="text-center text-[10px] font-bold text-muted-foreground uppercase">Estado</TableHead>
                                        <TableHead class="w-[80px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <template v-for="item in filteredStocks" :key="item.key">
                                        <!-- Main EPP Row -->
                                        <TableRow class="group cursor-pointer hover:bg-muted/30 transition-colors" @click="toggleRow(item.key)">
                                            <TableCell>
                                                <div class="flex items-center gap-3">
                                                    <div class="p-1">
                                                        <ChevronDown v-if="expandedRows.has(item.key)" class="h-4 w-4 text-slate-400" />
                                                        <ChevronRight v-else class="h-4 w-4 text-slate-400" />
                                                    </div>
                                                    <div
                                                        :class="[
                                                            'rounded-lg p-2',
                                                            activeTab === 'clothes'
                                                                ? 'bg-indigo-50 text-indigo-600'
                                                                : activeTab === 'epps'
                                                                  ? 'bg-amber-50 text-amber-600'
                                                                  : activeTab === 'computer'
                                                                    ? 'bg-blue-50 text-blue-600'
                                                                    : activeTab === 'kitchen'
                                                                      ? 'bg-orange-50 text-orange-600'
                                                                      : 'bg-emerald-50 text-emerald-600',
                                                        ]"
                                                    >
                                                        <component :is="getItemIcon(activeTab)" class="h-4 w-4" />
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="font-bold text-foreground"
                                                            >{{ item.stockable?.name }} ({{ item.nestedSizes.length }} tallas)</span
                                                        >
                                                        <span class="text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                                                            {{ activeTab }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div class="flex flex-col gap-1">
                                                    <template v-if="activeTab === 'computer'">
                                                        <span class="text-xs font-semibold text-slate-700">{{ item.stockable?.brand }}</span>
                                                        <span class="text-[10px] text-muted-foreground">{{ item.stockable?.model || 'Sin Modelo' }}</span>
                                                    </template>
                                                    <template v-else-if="activeTab === 'kitchen'">
                                                        <span class="text-xs font-semibold text-slate-700">{{ item.stockable?.brand }}</span>
                                                        <span class="text-[10px] text-muted-foreground">{{ item.stockable?.size }}</span>
                                                    </template>
                                                    <template v-else>
                                                        <span class="text-xs text-muted-foreground italic">Desglosado por tallas</span>
                                                    </template>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div class="flex flex-col">
                                                    <div class="flex items-center gap-1.5 text-xs">
                                                        <Building2 class="h-3 w-3 text-slate-400" />
                                                        <span class="font-medium text-slate-700">{{ item.display_headquarter }}</span>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell class="text-center text-lg font-black text-foreground">
                                                {{ item.quantity }}
                                            </TableCell>
                                            <TableCell class="text-center">
                                                <div class="flex flex-col items-center gap-1">
                                                    <Badge
                                                        :variant="getStockStatus(item.quantity).variant"
                                                        class="rounded-full border-none px-2 text-[9px] font-black tracking-tighter uppercase shadow-none"
                                                    >
                                                        {{ getStockStatus(item.quantity).label }}
                                                    </Badge>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    @click.stop="openSizesModal(item)"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="hover:text-primary h-8 w-8 p-0 text-slate-400 transition-colors"
                                                >
                                                    <MoreHorizontal class="h-4 w-4" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>

                                        <!-- Nested Sizes Level -->
                                        <template v-if="expandedRows.has(item.key)">
                                            <template v-for="sizeRow in item.nestedSizes" :key="sizeRow.label">
                                                <TableRow
                                                    class="border-l-primary/30 cursor-pointer border-l-4 bg-slate-50/30"
                                                    @click="toggleSizeRow(item.key, sizeRow.label)"
                                                >
                                                    <TableCell class="pl-12">
                                                        <div class="flex items-center gap-2">
                                                            <div class="p-0.5">
                                                                <ChevronDown
                                                                    v-if="expandedSizeRows.has(`${item.key}-${sizeRow.label}`)"
                                                                    class="h-3 w-3 text-slate-400"
                                                                />
                                                                <ChevronRight v-else class="h-3 w-3 text-slate-400" />
                                                            </div>
                                                            <div
                                                                class="bg-primary/10 text-primary flex h-6 w-6 items-center justify-center rounded text-[10px] font-black"
                                                            >
                                                                {{ sizeRow.label.toUpperCase().slice(0, 2) }}
                                                            </div>
                                                            <span class="text-sm font-bold text-slate-700">Talla: {{ sizeRow.label }}</span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell colspan="2" class="text-[11px] text-slate-400 italic">
                                                        {{ sizeRow.nestedColors.length }} combinaciones de color
                                                    </TableCell>
                                                    <TableCell class="text-center font-bold text-slate-600">
                                                        {{ sizeRow.total }}
                                                    </TableCell>
                                                    <TableCell colspan="2"></TableCell>
                                                </TableRow>

                                                <!-- Nested Colors Level -->
                                                <template v-if="expandedSizeRows.has(`${item.key}-${sizeRow.label}`)">
                                                    <TableRow
                                                        v-for="colorData in sizeRow.nestedColors"
                                                        :key="colorData.id"
                                                        class="border-l-4 border-l-slate-200 bg-slate-100/20"
                                                    >
                                                        <TableCell class="py-2 pl-24">
                                                            <div class="flex items-center gap-3">
                                                                <div
                                                                    class="h-3 w-3 rounded-full border border-white shadow-sm"
                                                                    :style="{ backgroundColor: colorData.color?.hex_code || '#ccc' }"
                                                                ></div>
                                                                <span class="text-xs font-semibold text-slate-600">{{
                                                                    colorData.color?.name || 'Sin color'
                                                                }}</span>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell colspan="2">
                                                            <div class="flex flex-wrap gap-1">
                                                                <Badge
                                                                    v-for="rec in colorData.records"
                                                                    :key="rec.id"
                                                                    variant="outline"
                                                                    class="border bg-card px-1 py-0 text-[9px] text-slate-400"
                                                                >
                                                                    {{ rec.headquarter?.name || rec.cafe?.name || 'N/A' }}: {{ rec.quantity }}
                                                                </Badge>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell class="text-center font-black text-foreground">
                                                            {{ colorData.quantity }}
                                                        </TableCell>
                                                        <TableCell colspan="2"></TableCell>
                                                    </TableRow>
                                                </template>
                                            </template>
                                        </template>
                                    </template>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- Transfers Tab Content -->
                        <div v-else-if="activeTab === 'units_transfers'" class="space-y-6">
                            <div class="bg-card mb-6 overflow-hidden rounded-2xl border shadow-sm">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-indigo-50/30">
                                            <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Fecha Envío</TableHead>
                                            <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Destino (Unidad)</TableHead>
                                            <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Personal Asignado</TableHead>
                                            <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Items</TableHead>
                                            <TableHead class="text-center text-[10px] font-bold text-muted-foreground uppercase">Estado</TableHead>
                                            <TableHead class="w-[120px]"></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="transfer in transfers"
                                            :key="transfer.id"
                                            class="group hover:bg-muted/30 transition-colors"
                                        >
                                            <TableCell class="text-xs font-medium text-slate-600">
                                                {{ new Date(transfer.created_at).toLocaleDateString() }}
                                                <span class="block font-mono text-[10px] text-slate-400">{{
                                                    new Date(transfer.created_at).toLocaleTimeString()
                                                }}</span>
                                            </TableCell>
                                            <TableCell>
                                                <div class="flex items-center gap-2">
                                                    <div class="rounded-lg bg-indigo-100 p-1.5 text-indigo-600">
                                                        <Building2 class="h-3.5 w-3.5" />
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="leading-tight font-bold text-foreground">{{ transfer.unit?.name }}</span>
                                                        <span class="text-[10px] font-black tracking-tighter text-slate-400 uppercase">{{
                                                            transfer.unit?.mine?.name
                                                        }}</span>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div class="flex items-center gap-2">
                                                    <User class="h-3.5 w-3.5 text-slate-300" />
                                                    <span class="text-sm text-slate-600">{{ transfer.staff?.name || 'Stock de Unidad' }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div class="flex flex-wrap gap-1">
                                                    <Badge
                                                        v-for="item in transfer.items"
                                                        :key="item.id"
                                                        variant="outline"
                                                        class="border bg-card px-1.5 py-0 text-[10px] text-muted-foreground lowercase"
                                                    >
                                                        {{ item.quantity }}x {{ item.stockable?.name }} ({{ item.size || 'U' }})
                                                    </Badge>
                                                </div>
                                            </TableCell>
                                            <TableCell class="text-center">
                                                <Badge
                                                    :class="[
                                                        'rounded-full border-none px-2 text-[9px] font-black tracking-tighter uppercase shadow-none',
                                                        transfer.status === 'sent'
                                                            ? 'bg-amber-100 text-amber-700'
                                                            : 'bg-emerald-100 text-emerald-700',
                                                    ]"
                                                >
                                                    {{ transfer.status === 'sent' ? 'En Tránsito / Uso' : 'Devuelto' }}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    v-if="transfer.status === 'sent'"
                                                    @click="openReturnModal(transfer)"
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-8 gap-1 rounded-lg border bg-card text-[10px] font-black tracking-tighter uppercase transition-all hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                                >
                                                    <History class="h-3.5 w-3.5" /> DEVOLVER
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </template>
                    </template>
                </div>
            </div>
        </div>

        <!-- Footer Metrics -->
        <div
            class="bg-card text-muted-foreground flex flex-none flex-col items-center justify-between gap-4 rounded-2xl border p-4 text-xs shadow-sm sm:flex-row"
        >
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2">
                    <div class="h-2 w-2 animate-pulse rounded-full bg-rose-500"></div>
                    <span class="font-semibold text-slate-700">{{ stocks.filter((i) => i.quantity <= 0).length }} Alertas críticas</span>
                </div>
                <div class="flex items-center gap-2 border-l border-slate-200 pl-6">
                    <div class="h-2 w-2 rounded-full bg-amber-500"></div>
                    <span class="font-semibold text-slate-700"
                        >{{ stocks.filter((i) => i.quantity > 0 && i.quantity < 15).length }} Reposición necesaria</span
                    >
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-1.5 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                    <History class="h-3.5 w-3.5" />
                    ACTUALIZADO: {{ new Date().toLocaleTimeString() }}
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-primary h-8 rounded-lg text-[10px] font-black tracking-widest uppercase hover:bg-slate-100"
                >
                    DESCARGAR REPORTE
                </Button>
            </div>
        </div>

        <!-- Sizes Details Modal -->
        <Dialog v-model:open="isSizesModalOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Box class="h-5 w-5 text-indigo-600" />
                        Detalle de Tallas
                    </DialogTitle>
                    <DialogDescription v-if="selectedStockForSizes">
                        Stock histórico recibido para: {{ selectedStockForSizes.stockable?.name }}
                    </DialogDescription>
                </DialogHeader>

                <div class="mt-4 space-y-4">
                    <div class="relative">
                        <Search class="absolute top-2.5 left-3 h-4 w-4 text-slate-400" />
                        <Input
                            v-model="sizeSearch"
                            placeholder="Buscar talla..."
                            class="h-10 rounded-xl border-slate-200 pl-10 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="overflow-hidden rounded-2xl border shadow-sm">
                        <div class="flex justify-between border-b bg-slate-50 px-4 py-2 text-[10px] font-black text-slate-400 uppercase">
                            <span>Talla</span>
                            <span>Cant. Recibida</span>
                        </div>
                        <div v-if="isLoadingSizes" class="space-y-4 p-6">
                            <div
                                v-for="i in 3"
                                :key="i"
                                class="flex animate-pulse items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-lg bg-slate-200"></div>
                                    <div class="space-y-2">
                                        <div class="h-3 w-16 rounded bg-slate-200"></div>
                                        <div class="h-2 w-10 rounded bg-slate-100"></div>
                                    </div>
                                </div>
                                <div class="h-6 w-8 rounded-lg bg-slate-200"></div>
                            </div>
                            <p class="animate-pulse text-center text-[9px] font-bold tracking-widest text-slate-400 uppercase">
                                Recuperando registros...
                            </p>
                        </div>
                        <div v-else-if="filteredStockSizes.length === 0" class="p-8 text-center">
                            <p class="text-xs font-bold tracking-widest text-slate-400 uppercase">No se encontraron registros</p>
                        </div>
                        <div v-else class="custom-scrollbar max-h-[350px] divide-y divide-slate-100 overflow-y-auto">
                            <div v-for="group in filteredStockSizes" :key="group.title" class="space-y-3 px-4 py-4">
                                <div class="mb-2 flex items-center gap-2">
                                    <div class="rounded border border-slate-200 bg-slate-100 p-1 px-2 text-[9px] font-black text-muted-foreground uppercase">
                                        {{ group.hq }} - {{ group.cafe }}
                                    </div>
                                </div>
                                <div
                                    v-for="(sz, idx) in group.items"
                                    :key="idx"
                                    class="flex items-center justify-between rounded-xl border border-slate-50 bg-white p-2 shadow-sm transition-all hover:border-indigo-100"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-indigo-100 bg-indigo-50 text-[10px] font-black text-indigo-700 uppercase"
                                        >
                                            {{ sz.size ? sz.size.toUpperCase() : 'U' }}
                                        </div>
                                        <div v-if="sz.color" class="flex items-center gap-1.5">
                                            <div
                                                class="h-2.5 w-2.5 rounded-full border border-slate-200 shadow-sm"
                                                :style="{ backgroundColor: sz.color.hex_code }"
                                            ></div>
                                            <span class="text-[10px] font-bold text-muted-foreground uppercase">{{ sz.color.name }}</span>
                                        </div>
                                    </div>
                                    <Badge
                                        variant="secondary"
                                        class="bg-card rounded-lg border px-2.5 py-0.5 font-mono text-xs font-black"
                                    >
                                        {{ sz.quantity }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="mt-2 p-0">
                    <Button @click="isSizesModalOpen = false" variant="ghost" class="w-full text-[10px] font-bold tracking-widest uppercase"
                        >Cerrar</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Return Modal -->
        <Dialog v-model:open="isReturnModalOpen">
            <DialogContent class="overflow-hidden rounded-2xl border-none p-0 shadow-2xl sm:max-w-[500px]">
                <DialogHeader class="bg-rose-600 p-6 text-white">
                    <DialogTitle class="flex items-center gap-3 text-xl font-black">
                        <History class="h-6 w-6 text-rose-200" />
                        Confirmar Devolución
                    </DialogTitle>
                    <DialogDescription class="text-rose-100"> Estos items retornarán al stock general (Principal). </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 bg-white p-6">
                    <div class="space-y-3 rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <div v-for="(item, idx) in returnForm.items" :key="idx" class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2">
                                <Package class="h-4 w-4 text-slate-400" />
                                <span class="font-bold text-slate-700">{{ item.name }} ({{ item.size || 'U' }})</span>
                            </div>
                            <span class="rounded-lg border border-slate-100 bg-white px-2 py-0.5 font-black text-rose-600">{{ item.quantity }}</span>
                        </div>
                    </div>

                    <p class="text-center text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                        ¿Estás seguro de que este stock regresó al almacén?
                    </p>
                </div>

                <DialogFooter class="flex gap-3 border-t bg-slate-50 p-6 sm:justify-center">
                    <Button variant="ghost" @click="isReturnModalOpen = false" class="text-[10px] font-bold text-muted-foreground uppercase">
                        Cancelar
                    </Button>
                    <Button
                        @click="handleReturn"
                        class="bg-rose-600 px-8 text-[10px] font-black tracking-widest text-white uppercase shadow-lg hover:bg-rose-700"
                    >
                        Sí, Devolver a Principal
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 20px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
