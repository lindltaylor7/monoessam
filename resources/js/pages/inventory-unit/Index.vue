<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Package, 
    Search, 
    ArrowUpRight, 
    ArrowDownRight, 
    Box, 
    Building2,
    LayoutGrid, 
    List,
    ShieldCheck,
    Truck,
    History,
    MoreHorizontal,
    Plus,
    Filter,
    Mountain,
    User
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { 
    Table, 
    TableBody, 
    TableCell, 
    TableHead, 
    TableHeader, 
    TableRow 
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Label } from '@/components/ui/label';

const props = defineProps<{
    units: Array<{ id: number, name: string, mine: { name: string } }>;
    epps: Array<{ id: number, name: string, category?: { name: string } }>;
    colors: Array<{ id: number, name: string, hex_code?: string }>;
    stocks: Array<{
        id: number;
        stockable_id: number;
        stockable_type: string;
        unit_id: number;
        quantity: number;
        size: string;
        color_id: number;
        stockable: { name: string };
        unit: { name: string, mine: { name: string } };
        color?: { name: string, hex_code: string };
    }>;
    transfers: Array<any>;
    staffHistories: Array<any>;
}>();

const selectedUnitId = ref<string>('all');
const searchQuery = ref('');
const viewMode = ref<'grid' | 'table'>('table');
const activeMainTab = ref('stock'); // 'stock', 'movements' or 'assignments'

// Filtering logic
const filteredStocks = computed(() => {
    return props.stocks.filter(stock => {
        const matchesUnit = selectedUnitId.value === 'all' || String(stock.unit_id) === selectedUnitId.value;
        const matchesSearch = stock.stockable.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                             (stock.unit.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
        return matchesUnit && matchesSearch;
    });
});

const filteredTransfers = computed(() => {
    return props.transfers.filter(t => {
        const matchesUnit = selectedUnitId.value === 'all' || String(t.unit_id) === selectedUnitId.value;
        return matchesUnit;
    });
});

const filteredStaffHistories = computed(() => {
    return props.staffHistories.filter(h => {
        if (!h.staff?.staffable || h.staff.staffable_type !== 'App\\Models\\Cafe') return false;
        const matchesUnit = selectedUnitId.value === 'all' || String(h.staff.staffable.unit_id) === selectedUnitId.value;
        const matchesSearch = h.staff.names.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                             (h.reason && h.reason.toLowerCase().includes(searchQuery.value.toLowerCase()));
        return matchesUnit && matchesSearch;
    });
});

const unitStats = computed(() => {
    if (selectedUnitId.value === 'all') return null;
    const unitStocks = props.stocks.filter(s => String(s.unit_id) === selectedUnitId.value);
    return {
        totalItems: unitStocks.reduce((acc, s) => acc + s.quantity, 0),
        uniqueEpps: new Set(unitStocks.map(s => s.stockable_id)).size,
        lowStock: unitStocks.filter(s => s.quantity < 5).length
    };
});

// Transfer dialog state
const isTransferOpen = ref(false);
const transferForm = ref({
    unit_id: '',
    items: [{ stockable_id: '', stockable_type: 'epp', quantity: 1, size: '' }]
});

const handleTransfer = () => {
    router.post(route('inventory.transfer.store'), transferForm.value, {
        onSuccess: () => {
            isTransferOpen.value = false;
        }
    });
};

const getStockStatus = (quantity: number) => {
    if (quantity === 0) return { label: 'Agotado', variant: 'destructive' as const };
    if (quantity < 5) return { label: 'Bajo', variant: 'secondary' as const };
    return { label: 'Disponible', variant: 'outline' as const };
};

</script>

<template>
    <Head title="Stock de EEPs por Unidad" />

    <AppLayout :breadcrumbs="[
        { title: 'Logística', href: route('logistics') },
        { title: 'Inventario', href: route('inventory.index') },
        { title: 'Stock por Unidades', href: '#' }
    ]">
        <div class="flex flex-col h-full w-full p-4 md:p-8 gap-6 bg-slate-50/50">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="space-y-1">
                    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 flex items-center gap-3">
                        <div class="p-2 bg-primary/10 rounded-xl">
                            <ShieldCheck class="h-8 w-8 text-primary" />
                        </div>
                        Control de EPPs por Unidad
                    </h1>
                    <p class="text-slate-500 font-medium">
                        Visualización y gestión detallada de equipos de protección en cada operación minera.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Dialog v-model:open="isTransferOpen">
                        <DialogTrigger as-child>
                            <Button class="gap-2 shadow-lg shadow-primary/20 bg-primary hover:bg-primary/90 text-primary-foreground font-bold px-6">
                                <Truck class="h-5 w-5" />
                                Enviar a Unidad
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-[500px] rounded-2xl shadow-2xl">
                            <DialogHeader>
                                <DialogTitle class="text-2xl font-bold flex items-center gap-2">
                                    <Truck class="h-6 w-6 text-primary" />
                                    Nuevo Envío de Stock
                                </DialogTitle>
                                <DialogDescription>
                                    Asigne equipos desde el almacén principal a una unidad minera.
                                </DialogDescription>
                            </DialogHeader>
                            <div class="grid gap-6 py-4">
                                <div class="grid gap-2">
                                    <Label class="text-sm font-bold text-slate-700">Seleccionar Unidad Destino</Label>
                                    <Select v-model="transferForm.unit_id">
                                        <SelectTrigger class="h-12 border-slate-200">
                                            <SelectValue placeholder="Elija una unidad..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="unit in units" :key="unit.id" :value="String(unit.id)">
                                                <div class="flex items-center gap-2">
                                                    <Mountain class="h-4 w-4 text-slate-400" />
                                                    <span>{{ unit.mine.name }} - {{ unit.name }}</span>
                                                </div>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <!-- dynamic items list could go here, for now keeping it simple as per InventoryController::storeTransfer -->
                                <div class="p-4 bg-slate-50 rounded-xl border border-slate-100 flex items-center gap-3 text-sm text-slate-600">
                                    <Package class="h-5 w-5 text-slate-400" />
                                    Para envíos múltiples masivos, utilice la sección de inventario general.
                                </div>
                            </div>
                            <DialogFooter>
                                <Button @click="handleTransfer" :disabled="!transferForm.unit_id" class="w-full h-12 text-lg font-bold">Inicia Envío</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card class="border-none shadow-sm bg-white overflow-hidden group hover:shadow-md transition-all">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-slate-500 flex items-center gap-2 truncate">
                            <Building2 class="h-4 w-4" /> UNIDADES ACTIVAS
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-slate-900">{{ units.length }}</div>
                    </CardContent>
                    <div class="absolute bottom-0 left-0 w-full h-1 bg-indigo-500 opacity-20 group-hover:opacity-100 transition-opacity"></div>
                </Card>

                <Card class="border-none shadow-sm bg-white overflow-hidden group hover:shadow-md transition-all" v-if="unitStats">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-slate-500 flex items-center gap-2 truncate">
                            <Box class="h-4 w-4" /> STOCK TOTAL EN UNIDAD
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-slate-900">{{ unitStats.totalItems }}</div>
                    </CardContent>
                    <div class="absolute bottom-0 left-0 w-full h-1 bg-emerald-500 opacity-20 group-hover:opacity-100 transition-opacity"></div>
                </Card>

                <Card class="border-none shadow-sm bg-white overflow-hidden group hover:shadow-md transition-all" v-if="unitStats">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-slate-500 flex items-center gap-2 truncate">
                            <ShieldCheck class="h-4 w-4" /> EPPs DIFERENTES
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-slate-900">{{ unitStats.uniqueEpps }}</div>
                    </CardContent>
                    <div class="absolute bottom-0 left-0 w-full h-1 bg-amber-500 opacity-20 group-hover:opacity-100 transition-opacity"></div>
                </Card>

                <Card class="border-none shadow-sm bg-white overflow-hidden group hover:shadow-md transition-all" v-if="unitStats">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-slate-500 flex items-center gap-2 truncate">
                            <ArrowDownRight class="h-4 w-4" /> STOCK CRÍTICO
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-slate-900 text-rose-600">{{ unitStats.lowStock }}</div>
                    </CardContent>
                    <div class="absolute bottom-0 left-0 w-full h-1 bg-rose-500 opacity-20 group-hover:opacity-100 transition-opacity"></div>
                </Card>
            </div>

            <!-- Main Content Area -->
            <Tabs v-model="activeMainTab" class="w-full">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
                    <TabsList class="bg-white p-1 border shadow-sm rounded-xl h-auto">
                        <TabsTrigger value="stock" class="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground rounded-lg py-2 px-6 gap-2">
                            <Box class="h-4 w-4" />
                            Inventario Actual
                        </TabsTrigger>
                        <TabsTrigger value="movements" class="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground rounded-lg py-2 px-6 gap-2">
                            <History class="h-4 w-4" />
                            Historial de Unidades
                        </TabsTrigger>
                        <TabsTrigger value="assignments" class="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground rounded-lg py-2 px-6 gap-2">
                            <User class="h-4 w-4" />
                            Entregas a Personal
                        </TabsTrigger>
                    </TabsList>
                </div>

                <TabsContent value="stock" class="m-0 space-y-4">
                    <!-- Filters Bar -->
                    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4">
                        <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                            <div class="w-full md:w-72">
                                <Select v-model="selectedUnitId">
                                    <SelectTrigger class="h-10 bg-slate-50/50 border-slate-200">
                                        <div class="flex items-center gap-2">
                                            <Filter class="h-4 w-4 text-slate-400" />
                                            <SelectValue placeholder="Filtrar por Unidad" />
                                        </div>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Todas las Unidades</SelectItem>
                                        <SelectItem v-for="unit in units" :key="unit.id" :value="String(unit.id)">
                                            {{ unit.mine.name }} - {{ unit.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="relative w-full md:w-80">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                                <Input 
                                    v-model="searchQuery" 
                                    placeholder="Buscar EPP o Unidad..." 
                                    class="pl-10 h-10 bg-slate-50/50 border-slate-200"
                                />
                            </div>
                        </div>

                        <div class="flex items-center bg-slate-100 p-1 rounded-xl">
                            <button 
                                @click="viewMode = 'grid'"
                                :class="[
                                    'p-2 rounded-lg transition-all',
                                    viewMode === 'grid' ? 'bg-white shadow-sm text-primary' : 'text-slate-400'
                                ]"
                            >
                                <LayoutGrid class="h-4 w-4" />
                            </button>
                            <button 
                                @click="viewMode = 'table'"
                                :class="[
                                    'p-2 rounded-lg transition-all',
                                    viewMode === 'table' ? 'bg-white shadow-sm text-primary' : 'text-slate-400'
                                ]"
                            >
                                <List class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Data Display -->
                    <div v-if="filteredStocks.length > 0">
                        <!-- Table View -->
                        <div v-if="viewMode === 'table'" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                            <Table>
                                <TableHeader class="bg-slate-50/50">
                                    <TableRow>
                                        <TableHead class="font-bold py-4">Mina / Unidad</TableHead>
                                        <TableHead class="font-bold">EPP</TableHead>
                                        <TableHead class="font-bold">Color / Talla</TableHead>
                                        <TableHead class="font-bold text-center">Cantidad</TableHead>
                                        <TableHead class="font-bold">Estado</TableHead>
                                        <TableHead class="font-bold text-right">Acciones</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="stock in filteredStocks" :key="stock.id" class="group hover:bg-slate-50/50 transition-colors">
                                        <TableCell>
                                            <div class="flex flex-col">
                                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ stock.unit.mine.name }}</span>
                                                <span class="font-semibold text-slate-900">{{ stock.unit.name }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex items-center gap-3">
                                                <div class="h-10 w-10 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
                                                    <Box class="h-5 w-5" />
                                                </div>
                                                <span class="font-medium text-slate-700">{{ stock.stockable.name }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex flex-wrap gap-2 items-center">
                                                <Badge variant="outline" class="bg-white border-slate-200 text-slate-600 capitalize">
                                                    <div 
                                                        v-if="stock.color?.hex_code" 
                                                        class="h-2 w-2 rounded-full mr-2" 
                                                        :style="{ backgroundColor: stock.color.hex_code }"
                                                    ></div>
                                                    {{ stock.color?.name || 'Varios' }}
                                                </Badge>
                                                <Badge v-if="stock.size" variant="outline" class="bg-slate-100 border-transparent text-slate-600 font-bold">
                                                    Talla {{ stock.size }}
                                                </Badge>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-center">
                                            <span class="text-xl font-bold text-slate-900">{{ stock.quantity }}</span>
                                        </TableCell>
                                        <TableCell>
                                            <Badge :variant="getStockStatus(stock.quantity).variant">
                                                {{ getStockStatus(stock.quantity).label }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <Button variant="ghost" size="icon" class="text-slate-400 hover:text-primary">
                                                <MoreHorizontal class="h-5 w-5" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- Grid View -->
                        <div v-else class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-4">
                            <Card v-for="stock in filteredStocks" :key="stock.id" class="border-none shadow-sm hover:shadow-md transition-all group relative overflow-hidden bg-white">
                                <CardHeader class="pb-2">
                                    <div class="flex justify-between items-start mb-2">
                                        <Badge variant="outline" class="bg-indigo-50 text-indigo-700 border-indigo-100 font-bold px-2 py-0">
                                            {{ stock.unit.name }}
                                        </Badge>
                                        <Badge :variant="getStockStatus(stock.quantity).variant" class="rounded-full">
                                            {{ getStockStatus(stock.quantity).label }}
                                        </Badge>
                                    </div>
                                    <CardTitle class="text-lg font-bold text-slate-900">{{ stock.stockable.name }}</CardTitle>
                                    <CardDescription>{{ stock.unit.mine.name }}</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                                        <div class="flex flex-col">
                                            <span class="text-[10px] uppercase font-bold text-slate-400">Existencia</span>
                                            <span class="text-2xl font-black text-slate-900">{{ stock.quantity }}</span>
                                        </div>
                                        <div class="flex flex-col items-end">
                                            <span class="text-[10px] uppercase font-bold text-slate-400">Atributos</span>
                                            <div class="flex items-center gap-2">
                                                <div v-if="stock.color" class="h-3 w-3 rounded-full border border-slate-200" :style="{ backgroundColor: stock.color.hex_code }"></div>
                                                <span class="text-xs font-semibold">{{ stock.size ? 'T:' + stock.size : 'General' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex gap-2">
                                        <Button variant="outline" size="sm" class="flex-1 gap-2 text-xs border-slate-200">
                                            <History class="h-3.5 w-3.5" /> Movimientos
                                        </Button>
                                        <Button size="sm" variant="secondary" class="gap-2 text-xs font-bold">
                                            Ver Detalles
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <!-- Empty State for Stock -->
                    <div v-else class="flex flex-col items-center justify-center py-20 bg-white rounded-3xl border border-dashed border-slate-200 text-center">
                        <div class="h-20 w-20 rounded-full bg-slate-50 flex items-center justify-center mb-4">
                            <Box class="h-10 w-10 text-slate-300" />
                        </div>
                        <h3 class="text-xl font-bold text-slate-900">No se encontraron existencias</h3>
                        <p class="text-slate-500 max-w-sm mt-2">
                            No hay registros de EPPs para los criterios seleccionados. Puede iniciar un envío para habilitar stock en esta unidad.
                        </p>
                        <Button @click="isTransferOpen = true" variant="outline" class="mt-6 gap-2 border-slate-200">
                            <Plus class="h-4 w-4" /> Enviar Stock Ahora
                        </Button>
                    </div>
                </TabsContent>

                <TabsContent value="movements" class="m-0">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div v-if="filteredTransfers.length > 0">
                            <Table>
                                <TableHeader class="bg-slate-50/50">
                                    <TableRow>
                                        <TableHead class="font-bold py-4">Fecha</TableHead>
                                        <TableHead class="font-bold">Unidad</TableHead>
                                        <TableHead class="font-bold">Responsable</TableHead>
                                        <TableHead class="font-bold">Elementos</TableHead>
                                        <TableHead class="font-bold">Estado</TableHead>
                                        <TableHead class="font-bold text-right">Ver ODC</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="transfer in filteredTransfers" :key="transfer.id" class="hover:bg-slate-50/50">
                                        <TableCell class="font-medium">
                                            {{ new Date(transfer.created_at).toLocaleDateString() }}
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex flex-col">
                                                <span class="text-xs text-slate-400 uppercase tracking-wider">{{ transfer.unit.mine.name }}</span>
                                                <span class="font-semibold">{{ transfer.unit.name }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {{ transfer.staff?.names || 'Almacén Central' }}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-100">
                                                {{ transfer.items.length }} Items
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge :variant="transfer.status === 'sent' ? 'secondary' : 'default'">
                                                {{ transfer.status === 'sent' ? 'Enviado' : 'Recibido' }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <Button variant="ghost" size="sm" class="text-xs text-primary font-bold">
                                                Ver Detalles
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-20 text-center">
                            <History class="h-12 w-12 text-slate-200 mb-4" />
                            <h3 class="text-lg font-bold text-slate-900">Sin movimientos recientes</h3>
                            <p class="text-slate-500">No se han registrado transferencias para esta selección.</p>
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="assignments" class="m-0">
                    <div v-if="filteredStaffHistories.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="hist in filteredStaffHistories" :key="hist.id" class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col gap-4 hover:shadow-md transition-all">
                            <div class="flex justify-between items-start border-b border-slate-50 pb-3">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <Badge variant="secondary" class="text-[10px] font-black uppercase bg-indigo-50 text-indigo-600 border-none px-2 py-0.5 rounded-md">
                                            {{ hist.reason }}
                                        </Badge>
                                        <span class="text-[10px] font-medium text-slate-400">
                                            {{ new Date(hist.created_at).toLocaleString() }}
                                        </span>
                                    </div>
                                    <div class="font-bold text-slate-900 group-hover:text-primary transition-colors">
                                        {{ hist.staff?.names }}
                                    </div>
                                    <div v-if="hist.staff?.staffable" class="text-[10px] font-medium text-slate-400 flex items-center gap-1">
                                        <Mountain class="h-3 w-3" />
                                        <template v-if="hist.staff.staffable_type === 'App\\Models\\Cafe'">
                                            {{ hist.staff.staffable.unit?.mine?.name }} - {{ hist.staff.staffable.unit?.name }}
                                        </template>
                                        <template v-else>
                                            {{ hist.staff.staffable.name }}
                                        </template>
                                    </div>
                                </div>
                                <div class="h-10 w-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400">
                                    <User class="h-5 w-5" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div v-for="(item, idx) in hist.items" :key="idx" class="flex flex-col p-3 bg-slate-50 rounded-xl border border-slate-100/50">
                                    <div class="flex justify-between items-start">
                                        <span class="text-xs font-bold text-slate-700">
                                            {{ epps.find(e => String(e.id) === String(item.epp_id))?.name || item.epp_name || `EPP #${item.epp_id}` }}
                                        </span>
                                        <Badge variant="outline" class="bg-white text-[10px] px-1.5 h-5 border-slate-200">
                                            {{ item.quantity }} Unid.
                                        </Badge>
                                    </div>
                                    <div class="flex gap-3 text-[10px] text-slate-500 mt-1">
                                        <span class="font-medium flex items-center gap-1">
                                            <div class="h-2 w-2 rounded-full bg-slate-200" :style="{ backgroundColor: colors.find(c => String(c.id) === String(item.color_id))?.hex_code }"></div>
                                            Color: {{ colors.find(c => String(c.id) === String(item.color_id))?.name || 'Varios' }}
                                        </span>
                                        <span class="font-medium">Talla: <strong class="text-slate-700">{{ item.size || '-' }}</strong></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-auto pt-3 border-t border-slate-50 flex justify-between items-center text-[10px]">
                                <span class="text-slate-400">Registrado por: <strong>{{ hist.user?.name || 'Sistema' }}</strong></span>
                                <Button variant="ghost" size="sm" class="h-6 text-[10px] font-black text-indigo-600">VER DETALLES</Button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-20 bg-white rounded-3xl border border-dashed border-slate-200 text-center">
                        <User class="h-12 w-12 text-slate-200 mb-4" />
                        <h3 class="text-lg font-bold text-slate-900">Sin entregas a personal</h3>
                        <p class="text-slate-500 max-w-sm">No se han registrado entregas individuales en esta unidad/búsqueda.</p>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
