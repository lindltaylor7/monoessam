<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
    Package, 
    Plus, 
    Search, 
    Filter, 
    ArrowUpRight, 
    ArrowDownRight, 
    History,
    Shirt,
    Palette,
    Coffee,
    AlertCircle,
    Monitor,
    Utensils,
    Box,
    Building2,
    Settings2,
    Loader2,
    Check,
    Trash2,
    Calendar,
    FileText,
    Truck,
    Building,
    LayoutGrid,
    List,
    MoreHorizontal
} from 'lucide-vue-next';
import { 
    Table, 
    TableBody, 
    TableCell, 
    TableHead, 
    TableHeader, 
    TableRow 
} from '@/components/ui/table';
import { 
    Dialog, 
    DialogContent, 
    DialogHeader, 
    DialogTitle, 
    DialogTrigger,
    DialogFooter,
    DialogDescription 
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    colors: Array<{ id: number, name: string, hex_code?: string }>;
    cafes: Array<{ id: number, name: string, unit: { name: string, mine:{ name:string } } }>;
    headquarters: Array<{ id: number, name: string, business_id: number, business: { id: number, name: string } }>;
    stocks: Array<{
        id: number;
        stockable_id: number;
        stockable_type: string;
        headquarter_id: number | null;
        cafe_id: number | null;
        unit_id: number | null;
        quantity: number;
        stockable: any;
        cafe?: { name: string };
        headquarter?: { name: string };
        unit?: { name: string, mine: { name: string } };
    }>;
    businesses: Array<{ id: number, name: string }>;
    providers: Array<{ id: number, name: string }>;
    clothes: Array<{ id: number, name: string }>;
    epps: Array<{ id: number, name: string }>;
    units: Array<{ id: number, name: string, mine: { name: string } }>;
    transfers: Array<any>;
}>();

const activeTab = ref('clothes');
const viewMode = ref<'cards' | 'table'>('cards');
const searchQuery = ref('');
const selectedCafeId = ref('all');
const selectedHeadquarterId = ref('all');

// Filtrado de inventario (Polymorphic)
const filteredStocks = computed(() => {
    const filtered = props.stocks.filter(stock => {
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
    filtered.forEach(stock => {
        const key = `${stock.stockable_type}-${stock.stockable_id}`;
        if (!groups[key]) {
            groups[key] = {
                ...stock,
                total_quantity: 0,
                headquarter_names: new Set(),
                cafe_names: new Set(),
            };
        }
        groups[key].total_quantity += Number(stock.quantity);
        if (stock.headquarter?.name) groups[key].headquarter_names.add(stock.headquarter.name);
        if (stock.cafe?.name) groups[key].cafe_names.add(stock.cafe.name);
    });

    return Object.values(groups).map(g => ({
        ...g,
        quantity: g.total_quantity,
        display_headquarter: Array.from(g.headquarter_names).join(', ') || 'N/A',
        display_cafe: Array.from(g.cafe_names).join(', ') || 'N/A'
    }));
});

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
    
    axios.get(route('inventory.stock.sizes', { id: stock.id }))
        .then(res => {
            stockSizes.value = res.data;
        })
        .catch(err => console.error(err))
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
                items: []
            };
        }
        grouped[groupKey].items.push(item);
    });
    
    const s = sizeSearch.value.toLowerCase();
    
    return Object.values(grouped).map((group: any) => {
        // Filter items within the group
        const filteredItems = group.items.filter((item: any) => {
            if (!s) return true;
            return (item.size && item.size.toLowerCase().includes(s)) || 
                   (item.color?.name && item.color.name.toLowerCase().includes(s));
        });
        
        return {
            ...group,
            items: filteredItems
        };
    }).filter(group => group.items.length > 0);
});

const isReturnModalOpen = ref(false);
const returnForm = ref({
    unit_id: '',
    items: [] as any[]
});

const openReturnModal = (transfer: any) => {
    returnForm.value = {
        unit_id: String(transfer.unit_id),
        items: transfer.items.map((i: any) => ({
            stockable_id: i.stockable_id,
            stockable_type: i.stockable_type,
            name: i.stockable?.name,
            quantity: i.quantity,
            size: i.size
        }))
    };
    isReturnModalOpen.value = true;
};

const handleReturn = () => {
    router.post(route('inventory.transfer.return'), returnForm.value, {
        onSuccess: () => {
            isReturnModalOpen.value = false;
        },
        preserveScroll: true
    });
};


const stockForm = ref({
    stockable_type: 'cloth',
    stockable_id: '',
    headquarter_id: 'none',
    cafe_id: '',
    quantity: 0,
    action: 'add' as 'add' | 'set'
});


// Search functionality for items in stock dialog
const itemSearchQuery = ref('');
const itemSearchResults = ref<Array<{ id: number, name: string }>>([]);
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
                query: query
            }
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

watch(() => stockForm.value.stockable_type, () => {
    itemSearchQuery.value = '';
    itemSearchResults.value = [];
    stockForm.value.stockable_id = '';
    selectedItemName.value = '';
});

const selectItem = (item: { id: number, name: string }) => {
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
        preserveState: true
    });
};

const resetStockForm = () => {
    stockForm.value = {
        stockable_type: 'cloth',
        stockable_id: '',
        headquarter_id: 'none',
        cafe_id: '',
        quantity: 0,
        action: 'add'
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
    hex_code: '#000000'
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
        }
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
        case 'clothes': return Shirt;
        case 'epps': return Box;
        case 'computer': return Monitor;
        case 'kitchen': return Utensils;
        case 'ingredients': return Package;
        default: return Package;
    }
};

// --- New Invoice Logic moved to Invoices/Index.vue ---

</script>

<template>
    <Head title="Gestión de Inventario" />

    <AppLayout :breadcrumbs="[
        { title: 'Logística', href: route('logistics') },
        { title: 'Inventario', href: route('inventory.index') }
    ]">
        <div class="flex flex-col h-full w-full overflow-hidden p-4 sm:p-6 gap-6 bg-slate-50/50">
            
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 flex-none">
                <div class="min-w-0 flex-1">
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight flex items-center gap-3 text-slate-900">
                        <div class="p-2 bg-primary/10 rounded-xl">
                            <Package class="h-8 w-8 text-primary" />
                        </div>
                        <span>Gestión Multimodal de Inventario</span>
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        Control centralizado de suministros, equipos y materias primas
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Dialog v-model:open="isNewColorOpen">
                        <DialogTrigger as-child>
                            <Button variant="outline" size="sm" class="gap-2 bg-white shadow-sm border-slate-200">
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
                                        <Input v-model="colorForm.hex_code" type="color" class="w-12 h-10 p-1 cursor-pointer" />
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
                        @click="router.visit(route('inventory.invoices.index'))" 
                        size="sm" 
                        variant="outline" 
                        class="gap-2 bg-indigo-50 border-indigo-200 text-indigo-700 hover:bg-indigo-100 shadow-sm font-bold"
                    >
                        <FileText class="h-4 w-4" />
                        Gestión de Facturas y Proveedores
                    </Button>

                    <Dialog v-model:open="isNewItemOpen">
                        <DialogTrigger as-child>
                            <Button variant="outline" size="sm" class="gap-2 bg-white shadow-sm border-slate-200">
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
                                        class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
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
                            <Button size="sm" class="gap-2 shadow-lg shadow-primary/20">
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
                                    <div class="grid gap-2 relative">
                                        <Label>Buscar Item</Label>
                                        <div class="relative">
                                            <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                            <Input 
                                                v-model="itemSearchQuery" 
                                                placeholder="Escribe para buscar..." 
                                                class="pl-9"
                                            />
                                            <div v-if="isSearchingItems" class="absolute right-2.5 top-2.5">
                                                <Loader2 class="h-4 w-4 animate-spin text-primary" />
                                            </div>
                                        </div>
                                        
                                        <!-- Search Results Dropdown -->
                                        <div v-if="itemSearchResults.length > 0" class="absolute z-50 w-full mt-1 top-full bg-white border rounded-lg shadow-xl max-h-48 overflow-y-auto">
                                            <div 
                                                v-for="item in itemSearchResults" 
                                                :key="item.id"
                                                @click="selectItem(item)"
                                                class="px-4 py-2 hover:bg-slate-50 cursor-pointer flex items-center justify-between text-sm transition-colors border-b last:border-none"
                                            >
                                                <span>{{ item.name }}</span>
                                                <div v-if="stockForm.stockable_id === String(item.id)" class="text-primary">
                                                    <Check class="h-4 w-4" />
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div v-if="selectedItemName" class="mt-1 flex items-center gap-1.5 text-xs text-primary font-bold">
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
                                                <SelectItem v-for="hq in headquarters" :key="hq.id" :value="String(hq.id)">{{ hq.business.name }} - {{ hq.name }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label>Café / Unidad</Label>
                                        <Select v-model="stockForm.cafe_id">
                                            <SelectTrigger><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="cafe in cafes" :key="cafe.id" :value="String(cafe.id)">{{ cafe.name }} - {{ cafe.unit.name }}</SelectItem>
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
                                <Button @click="handleAddStock" :disabled="!stockForm.stockable_id" class="w-full shadow-lg shadow-primary/30">
                                    Confirmar Operación
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <!-- Dashboard Stats Summary -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 flex-none">
                <Card class="bg-white border-slate-200">
                    <CardContent class="p-4 flex items-center gap-4">
                        <div class="h-10 w-10 rounded-xl bg-indigo-50 flex items-center justify-center">
                            <Box class="h-5 w-5 text-indigo-600" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase">Items Registrados</p>
                            <p class="text-xl font-bold text-slate-900">{{ stocks.length }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="bg-white border-slate-200">
                    <CardContent class="p-4 flex items-center gap-4">
                        <div class="h-10 w-10 rounded-xl bg-rose-50 flex items-center justify-center">
                            <AlertCircle class="h-5 w-5 text-rose-600" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase">Sin Stock</p>
                            <p class="text-xl font-bold text-slate-900">{{ stocks.filter(s => s.quantity <= 0).length }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="bg-white border-slate-200">
                    <CardContent class="p-4 flex items-center gap-4">
                        <div class="h-10 w-10 rounded-xl bg-amber-50 flex items-center justify-center">
                            <Building2 class="h-5 w-5 text-amber-600" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase">Sedes Activas</p>
                            <p class="text-xl font-bold text-slate-900">{{ headquarters.length }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="bg-white border-slate-200">
                    <CardContent class="p-4 flex items-center gap-4">
                        <div class="h-10 w-10 rounded-xl bg-emerald-50 flex items-center justify-center">
                            <Settings2 class="h-5 w-5 text-emerald-600" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase">Existencias Totales</p>
                            <p class="text-xl font-bold text-slate-900">{{ stocks.reduce((acc, s) => acc + Number(s.quantity), 0) }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Tabs and Filters Section -->
            <div class="flex flex-col gap-4 flex-1 overflow-hidden">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-3 rounded-2xl border shadow-sm flex-none">
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
                            <TabsTrigger value="units_transfers" class="gap-2 rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm bg-indigo-50/50">
                                <Truck class="h-4 w-4 text-indigo-600" />
                                <span class="hidden sm:inline text-indigo-700 font-bold">Envíos a Unidades</span>
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>

                    <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                        <!-- View Toggle -->
                        <div class="flex items-center bg-slate-100 p-1 rounded-xl mr-2">
                            <button 
                                @click="viewMode = 'cards'"
                                :class="[
                                    'p-1.5 rounded-lg transition-all',
                                    viewMode === 'cards' ? 'bg-white shadow-sm text-primary' : 'text-slate-400 hover:text-slate-600'
                                ]"
                                title="Vista Cuadrícula"
                            >
                                <LayoutGrid class="h-4 w-4" />
                            </button>
                            <button 
                                @click="viewMode = 'table'"
                                :class="[
                                    'p-1.5 rounded-lg transition-all',
                                    viewMode === 'table' ? 'bg-white shadow-sm text-primary' : 'text-slate-400 hover:text-slate-600'
                                ]"
                                title="Vista Tabla"
                            >
                                <List class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="relative flex-1 sm:w-64">
                            <Search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input v-model="searchQuery" placeholder="Buscar item..." class="pl-9 h-10 rounded-xl bg-slate-50 border-none focus-visible:ring-1" />
                        </div>
                        
                        <div class="flex gap-2">
                            <Select v-model="selectedHeadquarterId">
                                <SelectTrigger class="w-[140px] h-10 rounded-xl bg-white">
                                    <SelectValue placeholder="Sedes" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas las Sedes</SelectItem>
                                    <SelectItem v-for="hq in headquarters" :key="hq.id" :value="String(hq.id)">{{ hq.name }} - {{ hq.business.name }}</SelectItem>
                                </SelectContent>
                            </Select>

                            <Select v-model="selectedCafeId">
                                <SelectTrigger class="w-[140px] h-10 rounded-xl bg-white">
                                    <SelectValue placeholder="Cafés" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos los Comedores</SelectItem>
                                    <SelectItem v-for="cafe in cafes" :key="cafe.id" :value="String(cafe.id)">{{ cafe.name }} - {{ cafe.unit.name }} - {{ cafe.unit.mine.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                <!-- Content Grid / Table -->
                <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar">
                    <div v-if="filteredStocks.length === 0" class="flex flex-col items-center justify-center h-64 border-2 border-dashed rounded-3xl bg-white/50 border-slate-200">
                        <div class="h-16 w-16 bg-slate-100 rounded-full flex items-center justify-center mb-4">
                            <Package class="h-8 w-8 text-slate-300" />
                        </div>
                        <p class="text-slate-500 font-semibold text-lg">Sin resultados</p>
                        <p class="text-slate-400 text-sm mt-1">No hay existencias registradas para esta categoría</p>
                    </div>

                    <template v-else>
                        <!-- Cards View -->
                        <div v-if="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 pb-6">
                            <Card 
                                v-for="item in filteredStocks" 
                                :key="item.id"
                                class="group hover:shadow-xl hover:shadow-slate-200/50 transition-all duration-500 overflow-hidden border-slate-200 rounded-2xl bg-white flex flex-col"
                            >
                                <div class="h-1.5 w-full bg-slate-100">
                                    <div v-if="item.stockable_type.includes('Cloth')" class="h-full bg-primary/40"></div>
                                    <div v-else-if="item.stockable_type.includes('Computer')" class="h-full bg-blue-400"></div>
                                    <div v-else-if="item.stockable_type.includes('Kitchen')" class="h-full bg-orange-400"></div>
                                    <div v-else class="h-full bg-emerald-400"></div>
                                </div>

                                <CardHeader class="p-5 pb-3">
                                    <div class="flex justify-between items-start gap-4">
                                        <div class="flex gap-3">
                                            <div :class="[
                                                'p-2 rounded-xl shadow-sm transition-transform group-hover:scale-110 flex-shrink-0 flex items-center justify-center size-10 self-start',
                                                activeTab === 'clothes' ? 'bg-indigo-50 text-indigo-600' :
                                                activeTab === 'epps' ? 'bg-amber-50 text-amber-600' :
                                                activeTab === 'computer' ? 'bg-blue-50 text-blue-600' :
                                                activeTab === 'kitchen' ? 'bg-orange-50 text-orange-600' :
                                                'bg-emerald-50 text-emerald-600'
                                            ]">
                                                <component :is="getItemIcon(activeTab)" class="h-5 w-5" />
                                            </div>
                                            <div class="min-w-0">
                                                <CardTitle class="text-[14px] leading-tight font-black text-slate-900 line-clamp-2 min-h-[40px] flex items-center break-words">{{ item.stockable?.name }}</CardTitle>
                                                <CardDescription class="text-xs mt-0.5 flex items-center gap-1.5 whitespace-normal text-slate-500">
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
                                                        <span v-if="item.stockable?.code" class="ml-1 text-[10px] text-slate-400 font-mono">#{{ item.stockable?.code }}</span>
                                                    </template>
                                                    <template v-else>
                                                        Insumo / Ingrediente
                                                    </template>
                                                </CardDescription>
                                            </div>
                                        </div>
                                        <Badge :variant="getStockStatus(item.quantity).variant" class="rounded-full px-2.5 shadow-none border-none text-[10px] uppercase font-bold">
                                            {{ getStockStatus(item.quantity).label }}
                                        </Badge>
                                    </div>
                                </CardHeader>

                                <CardContent class="p-5 pt-0 flex-1">
                                    <div class="mt-4 p-4 rounded-2xl bg-slate-50 flex items-center justify-between group-hover:bg-slate-100/50 transition-colors border border-transparent group-hover:border-slate-200">
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Stock Disponible</p>
                                            <div class="flex items-baseline gap-1.5 text-slate-900">
                                                <span class="text-3xl font-black leading-none">{{ item.quantity }}</span>
                                                <span class="text-xs font-bold text-slate-400 uppercase">Unidades</span>
                                            </div>
                                        </div>
                                        <div @click="openSizesModal(item)" class="flex flex-col items-end gap-1 cursor-pointer hover:opacity-80 transition-opacity">
                                            <div v-if="item.quantity > 0" class="flex items-center gap-1 text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">
                                                <ArrowUpRight class="h-3 w-3" /> ACTIVO
                                            </div>
                                            <div v-else class="flex items-center gap-1 text-[10px] font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full border border-rose-100">
                                                <ArrowDownRight class="h-3 w-3" /> AGOTADO
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-5 space-y-2.5">
                                        <div class="flex items-center justify-between text-[11px]">
                                            <span class="text-slate-400 font-medium flex items-center gap-1.5"><Building2 class="h-3 w-3" /> Gestión / Ubicación</span>
                                            <span class="text-slate-700 font-bold truncate max-w-[120px]">{{ item.display_headquarter }}</span>
                                        </div>
                                        
                                    </div>
                                </CardContent>

                                <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/10 group-hover:bg-slate-50/50 transition-colors">
                                    <div class="flex -space-x-1.5 overflow-hidden">
                                       <div class="h-6 w-6 rounded-full border-2 border-white bg-indigo-100 flex items-center justify-center text-[8px] font-bold text-indigo-600">HQ</div>
                                       <div class="h-6 w-6 rounded-full border-2 border-white bg-amber-100 flex items-center justify-center text-[8px] font-bold text-amber-600">CF</div>
                                    </div>
                                    <button @click="openSizesModal(item)" class="text-[10px] font-black text-primary flex items-center gap-1 hover:gap-2 transition-all tracking-tighter uppercase">
                                        AUDITAR STOCK <ArrowUpRight class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </Card>
                        </div>

                        <!-- Table View -->
                        <div v-else-if="viewMode === 'table' && activeTab !== 'units_transfers'" class="bg-white rounded-2xl border shadow-sm overflow-hidden mb-6">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-slate-50/50 hover:bg-slate-50/50">
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px] w-[300px]">Item / Catálogo</TableHead>
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Atributos</TableHead>
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Gestión / Ubicación</TableHead>
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px] text-center">Disponible</TableHead>
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px] text-center">Estado</TableHead>
                                        <TableHead class="w-[80px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="item in filteredStocks" :key="item.id" class="group transition-colors hover:bg-slate-50/50">
                                        <TableCell>
                                            <div class="flex items-center gap-3">
                                                <div :class="[
                                                    'p-2 rounded-lg',
                                                    activeTab === 'clothes' ? 'bg-indigo-50 text-indigo-600' :
                                                    activeTab === 'epps' ? 'bg-amber-50 text-amber-600' :
                                                    activeTab === 'computer' ? 'bg-blue-50 text-blue-600' :
                                                    activeTab === 'kitchen' ? 'bg-orange-50 text-orange-600' :
                                                    'bg-emerald-50 text-emerald-600'
                                                ]">
                                                    <component :is="getItemIcon(activeTab)" class="h-4 w-4" />
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-slate-900">{{ item.stockable?.name }}</span>
                                                    <span class="text-[10px] font-bold text-slate-400 tracking-wider uppercase">
                                                        {{ activeTab }}
                                                    </span>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex flex-col gap-1">
                                                <template v-if="activeTab === 'computer'">
                                                    <span class="text-xs font-semibold text-slate-700">{{ item.stockable?.brand }}</span>
                                                    <span class="text-[10px] text-slate-500">{{ item.stockable?.model || 'Sin Modelo' }}</span>
                                                </template>
                                                <template v-else-if="activeTab === 'kitchen'">
                                                    <span class="text-xs font-semibold text-slate-700">{{ item.stockable?.brand }}</span>
                                                    <span class="text-[10px] text-slate-500">{{ item.stockable?.size }}</span>
                                                </template>
                                                <template v-else>
                                                    <span class="text-xs text-slate-500 italic">Datos en detalle</span>
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
                                        <TableCell class="text-center font-black text-slate-900 text-lg">
                                            {{ item.quantity }}
                                        </TableCell>
                                        <TableCell class="text-center">
                                            <div class="flex flex-col items-center gap-1">
                                                <Badge :variant="getStockStatus(item.quantity).variant" class="rounded-full px-2 shadow-none border-none text-[9px] uppercase font-black tracking-tighter">
                                                    {{ getStockStatus(item.quantity).label }}
                                                </Badge>
                                                <Button 
                                                    v-if="item.unit_id && item.quantity > 0"
                                                    @click="openReturnModal({ 
                                                        unit_id: item.unit_id, 
                                                        items: [{ 
                                                            stockable_id: item.stockable_id, 
                                                            stockable_type: item.stockable_type, 
                                                            stockable: item.stockable,
                                                            quantity: item.quantity, 
                                                            size: '' 
                                                        }] 
                                                    })"
                                                    variant="link" 
                                                    size="sm" 
                                                    class="h-6 text-[9px] text-rose-500 font-bold p-0"
                                                >
                                                    Devolver a Principal
                                                </Button>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Button @click="openSizesModal(item)" variant="ghost" size="sm" class="h-8 w-8 p-0 text-slate-400 hover:text-primary transition-colors">
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- Transfers Tab Content -->
                        <div v-else-if="activeTab === 'units_transfers'" class="space-y-6">
                            <div class="bg-white rounded-2xl border shadow-sm overflow-hidden mb-6">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-indigo-50/30">
                                            <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Fecha Envío</TableHead>
                                            <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Destino (Unidad)</TableHead>
                                            <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Personal Asignado</TableHead>
                                            <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Items</TableHead>
                                            <TableHead class="font-bold text-slate-500 uppercase text-[10px] text-center">Estado</TableHead>
                                            <TableHead class="w-[120px]"></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="transfer in transfers" :key="transfer.id" class="group transition-colors hover:bg-slate-50/50">
                                            <TableCell class="text-xs font-medium text-slate-600">
                                                {{ new Date(transfer.created_at).toLocaleDateString() }}
                                                <span class="block text-[10px] text-slate-400 font-mono">{{ new Date(transfer.created_at).toLocaleTimeString() }}</span>
                                            </TableCell>
                                            <TableCell>
                                                <div class="flex items-center gap-2">
                                                    <div class="p-1.5 bg-indigo-100 rounded-lg text-indigo-600">
                                                        <Building2 class="h-3.5 w-3.5" />
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="font-bold text-slate-900 leading-tight">{{ transfer.unit?.name }}</span>
                                                        <span class="text-[10px] text-slate-400 uppercase font-black tracking-tighter">{{ transfer.unit?.mine?.name }}</span>
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
                                                    <Badge v-for="item in transfer.items" :key="item.id" variant="outline" class="text-[10px] px-1.5 py-0 bg-white border-slate-200 text-slate-500 lowercase">
                                                        {{ item.quantity }}x {{ item.stockable?.name }} ({{ item.size || 'U' }})
                                                    </Badge>
                                                </div>
                                            </TableCell>
                                            <TableCell class="text-center">
                                                <Badge :class="[
                                                    'rounded-full px-2 shadow-none border-none text-[9px] uppercase font-black tracking-tighter',
                                                    transfer.status === 'sent' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'
                                                ]">
                                                    {{ transfer.status === 'sent' ? 'En Tránsito / Uso' : 'Devuelto' }}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Button 
                                                    v-if="transfer.status === 'sent'"
                                                    @click="openReturnModal(transfer)" 
                                                    variant="outline" 
                                                    size="sm" 
                                                    class="h-8 text-[10px] font-black tracking-tighter uppercase gap-1 bg-white hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 border-slate-200 transition-all rounded-lg"
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
                </div>
            </div>
            </div>

            <!-- Footer Metrics -->
            <div class="p-4 border shadow-sm bg-white rounded-2xl text-xs text-slate-500 flex flex-col sm:flex-row justify-between items-center gap-4 flex-none border-slate-200">
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-2">
                        <div class="h-2 w-2 rounded-full bg-rose-500 animate-pulse"></div>
                        <span class="font-semibold text-slate-700">{{ stocks.filter(i => i.quantity <= 0).length }} Alertas críticas</span>
                    </div>
                    <div class="flex items-center gap-2 border-l border-slate-200 pl-6">
                        <div class="h-2 w-2 rounded-full bg-amber-500"></div>
                        <span class="font-semibold text-slate-700">{{ stocks.filter(i => i.quantity > 0 && i.quantity < 15).length }} Reposición necesaria</span>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-1.5 font-bold uppercase tracking-widest text-slate-400 text-[10px]">
                        <History class="h-3.5 w-3.5" />
                        ACTUALIZADO: {{ new Date().toLocaleTimeString() }}
                    </div>
                    <Button variant="ghost" size="sm" class="h-8 text-[10px] font-black tracking-widest text-primary hover:bg-slate-100 rounded-lg uppercase">
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
                            <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                            <Input 
                                v-model="sizeSearch"
                                placeholder="Buscar talla..." 
                                class="pl-10 h-10 border-slate-200 focus:ring-indigo-500 rounded-xl"
                            />
                        </div>

                        <div class="border rounded-2xl overflow-hidden shadow-sm">
                            <div class="bg-slate-50 border-b px-4 py-2 flex justify-between text-[10px] font-black uppercase text-slate-400">
                                <span>Talla</span>
                                <span>Cant. Recibida</span>
                            </div>
                                <div v-if="isLoadingSizes" class="p-6 space-y-4">
                                    <div v-for="i in 3" :key="i" class="flex justify-between items-center bg-slate-50/50 p-3 rounded-xl border border-slate-100 animate-pulse">
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 rounded-lg bg-slate-200"></div>
                                            <div class="space-y-2">
                                                <div class="h-3 w-16 bg-slate-200 rounded"></div>
                                                <div class="h-2 w-10 bg-slate-100 rounded"></div>
                                            </div>
                                        </div>
                                        <div class="h-6 w-8 bg-slate-200 rounded-lg"></div>
                                    </div>
                                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest text-center animate-pulse">Recuperando registros...</p>
                                </div>
                                <div v-else-if="filteredStockSizes.length === 0" class="p-8 text-center">
                                    <p class="text-xs text-slate-400 font-bold uppercase tracking-widest">No se encontraron registros</p>
                                </div>
                                <div v-else class="max-h-[350px] overflow-y-auto custom-scrollbar divide-y divide-slate-100">
                                    <div v-for="group in filteredStockSizes" :key="group.title" class="px-4 py-4 space-y-3">
                                        <div class="flex items-center gap-2 mb-2">
                                            <div class="p-1 px-2 bg-slate-100 rounded text-[9px] font-black uppercase text-slate-500 border border-slate-200">
                                                {{ group.hq }} - {{ group.cafe }}
                                            </div>
                                        </div>
                                        <div v-for="(sz, idx) in group.items" :key="idx" class="flex justify-between items-center bg-white p-2 rounded-xl border border-slate-50 shadow-sm transition-all hover:border-indigo-100">
                                            <div class="flex items-center gap-3">
                                                <div class="h-8 w-8 rounded-lg bg-indigo-50 flex items-center justify-center text-[10px] font-black text-indigo-700 border border-indigo-100 uppercase">
                                                    {{ sz.size ? sz.size.toUpperCase() : 'U' }}
                                                </div>
                                                <div v-if="sz.color" class="flex items-center gap-1.5">
                                                    <div class="w-2.5 h-2.5 rounded-full border border-slate-200 shadow-sm" :style="{ backgroundColor: sz.color.hex_code }"></div>
                                                    <span class="text-[10px] font-bold text-slate-500 uppercase">{{ sz.color.name }}</span>
                                                </div>
                                            </div>
                                            <Badge variant="secondary" class="font-mono font-black text-xs px-2.5 py-0.5 rounded-lg bg-white border border-slate-200">
                                                {{ sz.quantity }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                        </div>
                    </div>
                    
                    <DialogFooter class="p-0 mt-2">
                        <Button @click="isSizesModalOpen = false" variant="ghost" class="w-full font-bold uppercase tracking-widest text-[10px]">Cerrar</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Return Modal -->
            <Dialog v-model:open="isReturnModalOpen">
                <DialogContent class="sm:max-w-[500px] rounded-2xl p-0 overflow-hidden border-none shadow-2xl">
                    <DialogHeader class="p-6 bg-rose-600 text-white">
                        <DialogTitle class="text-xl font-black flex items-center gap-3">
                            <History class="h-6 w-6 text-rose-200" />
                            Confirmar Devolución
                        </DialogTitle>
                        <DialogDescription class="text-rose-100">
                            Estos items retornarán al stock general (Principal).
                        </DialogDescription>
                    </DialogHeader>

                    <div class="p-6 space-y-4 bg-white">
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-100 space-y-3">
                            <div v-for="(item, idx) in returnForm.items" :key="idx" class="flex justify-between items-center text-sm">
                                <div class="flex items-center gap-2">
                                    <Package class="h-4 w-4 text-slate-400" />
                                    <span class="font-bold text-slate-700">{{ item.name }} ({{ item.size || 'U' }})</span>
                                </div>
                                <span class="font-black text-rose-600 bg-white px-2 py-0.5 rounded-lg border border-slate-100">{{ item.quantity }}</span>
                            </div>
                        </div>
                        
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">
                            ¿Estás seguro de que este stock regresó al almacén?
                        </p>
                    </div>

                    <DialogFooter class="p-6 bg-slate-50 border-t flex gap-3 sm:justify-center">
                        <Button variant="ghost" @click="isReturnModalOpen = false" class="font-bold uppercase text-[10px] text-slate-500">
                            Cancelar
                        </Button>
                        <Button @click="handleReturn" class="bg-rose-600 hover:bg-rose-700 text-white px-8 font-black uppercase text-[10px] tracking-widest shadow-lg">
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
