<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    Box,
    Building2,
    Filter,
    History,
    LayoutGrid,
    List,
    MoreHorizontal,
    Mountain,
    Package,
    Plus,
    Search,
    ShieldCheck,
    Truck,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    units: Array<{ id: number; name: string; mine: { name: string } }>;
    epps: Array<{ id: number; name: string; category?: { name: string } }>;
    colors: Array<{ id: number; name: string; hex_code?: string }>;
    stocks: Array<{
        id: number;
        stockable_id: number;
        stockable_type: string;
        unit_id: number;
        quantity: number;
        size: string;
        color_id: number;
        stockable: { name: string };
        unit: { name: string; mine: { name: string } };
        color?: { name: string; hex_code: string };
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
    return (props.stocks || []).filter((stock) => {
        const matchesUnit = selectedUnitId.value === 'all' || String(stock.unit_id) === selectedUnitId.value;
        const matchesSearch =
            stock.stockable?.name?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            stock.unit?.name?.toLowerCase().includes(searchQuery.value.toLowerCase());
        return matchesUnit && matchesSearch;
    });
});

const filteredTransfers = computed(() => {
    return (props.transfers || []).filter((t) => {
        const matchesUnit = selectedUnitId.value === 'all' || String(t.unit_id) === selectedUnitId.value;
        return matchesUnit;
    });
});

const filteredStaffHistories = computed(() => {
    return (props.staffHistories || []).filter((h) => {
        if (!h.staff?.staffable || h.staff.staffable_type !== 'App\\Models\\Cafe') return false;
        const matchesUnit = selectedUnitId.value === 'all' || String(h.staff.staffable.unit_id) === selectedUnitId.value;
        const matchesSearch =
            h.staff?.names?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            (h.reason && h.reason?.toLowerCase().includes(searchQuery.value.toLowerCase()));
        return matchesUnit && matchesSearch;
    });
});

const unitStats = computed(() => {
    if (selectedUnitId.value === 'all') return null;
    const unitStocks = (props.stocks || []).filter((s) => String(s.unit_id) === selectedUnitId.value);
    return {
        totalItems: unitStocks.reduce((acc, s) => acc + s.quantity, 0),
        uniqueEpps: new Set(unitStocks.map((s) => s.stockable_id)).size,
        lowStock: unitStocks.filter((s) => s.quantity < 5).length,
    };
});

// Transfer dialog state
const isTransferOpen = ref(false);
const transferForm = ref({
    unit_id: '',
    items: [{ stockable_id: '', stockable_type: 'epp', quantity: 1, size: '' }],
});

const handleTransfer = () => {
    router.post(route('inventory.transfer.store'), transferForm.value, {
        onSuccess: () => {
            isTransferOpen.value = false;
        },
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

    <AppLayout
        :breadcrumbs="[
            { title: 'Logística', href: route('logistics') },
            { title: 'Inventario', href: route('inventory.index') },
            { title: 'Stock por Unidades', href: '#' },
        ]"
    >
        <div class="flex h-full w-full flex-col gap-6 bg-slate-50/50 p-4 md:p-8">
            <!-- Header Section -->
            <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                <div class="space-y-1">
                    <h1 class="flex items-center gap-3 text-3xl font-extrabold tracking-tight text-slate-900">
                        <div class="bg-primary/10 rounded-xl p-2">
                            <ShieldCheck class="text-primary h-8 w-8" />
                        </div>
                        Control de EPPs por Unidad
                    </h1>
                    <p class="font-medium text-slate-500">Visualización y gestión detallada de equipos de protección en cada operación minera.</p>
                </div>

                <div class="flex items-center gap-3">
                    <Dialog v-model:open="isTransferOpen">
                        <DialogTrigger as-child>
                            <Button class="shadow-primary/20 bg-primary hover:bg-primary/90 text-primary-foreground gap-2 px-6 font-bold shadow-lg">
                                <Truck class="h-5 w-5" />
                                Enviar a Unidad
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="rounded-2xl shadow-2xl sm:max-w-[500px]">
                            <DialogHeader>
                                <DialogTitle class="flex items-center gap-2 text-2xl font-bold">
                                    <Truck class="text-primary h-6 w-6" />
                                    Nuevo Envío de Stock
                                </DialogTitle>
                                <DialogDescription> Asigne equipos desde el almacén principal a una unidad minera. </DialogDescription>
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
                                <div class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-600">
                                    <Package class="h-5 w-5 text-slate-400" />
                                    Para envíos múltiples masivos, utilice la sección de inventario general.
                                </div>
                            </div>
                            <DialogFooter>
                                <Button @click="handleTransfer" :disabled="!transferForm.unit_id" class="h-12 w-full text-lg font-bold"
                                    >Inicia Envío</Button
                                >
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <Card class="group overflow-hidden border-none bg-white shadow-sm transition-all hover:shadow-md">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 truncate text-sm font-medium text-slate-500">
                            <Building2 class="h-4 w-4" /> UNIDADES ACTIVAS
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-slate-900">{{ units.length }}</div>
                    </CardContent>
                    <div class="absolute bottom-0 left-0 h-1 w-full bg-indigo-500 opacity-20 transition-opacity group-hover:opacity-100"></div>
                </Card>

                <Card class="group overflow-hidden border-none bg-white shadow-sm transition-all hover:shadow-md" v-if="unitStats">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 truncate text-sm font-medium text-slate-500">
                            <Box class="h-4 w-4" /> STOCK TOTAL EN UNIDAD
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-slate-900">{{ unitStats.totalItems }}</div>
                    </CardContent>
                    <div class="absolute bottom-0 left-0 h-1 w-full bg-emerald-500 opacity-20 transition-opacity group-hover:opacity-100"></div>
                </Card>

                <Card class="group overflow-hidden border-none bg-white shadow-sm transition-all hover:shadow-md" v-if="unitStats">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 truncate text-sm font-medium text-slate-500">
                            <ShieldCheck class="h-4 w-4" /> EPPs DIFERENTES
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-slate-900">{{ unitStats.uniqueEpps }}</div>
                    </CardContent>
                    <div class="absolute bottom-0 left-0 h-1 w-full bg-amber-500 opacity-20 transition-opacity group-hover:opacity-100"></div>
                </Card>

                <Card class="group overflow-hidden border-none bg-white shadow-sm transition-all hover:shadow-md" v-if="unitStats">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 truncate text-sm font-medium text-slate-500">
                            <ArrowDownRight class="h-4 w-4" /> STOCK CRÍTICO
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-rose-600 text-slate-900">{{ unitStats.lowStock }}</div>
                    </CardContent>
                    <div class="absolute bottom-0 left-0 h-1 w-full bg-rose-500 opacity-20 transition-opacity group-hover:opacity-100"></div>
                </Card>
            </div>

            <!-- Main Content Area -->
            <Tabs v-model="activeMainTab" class="w-full">
                <div class="mb-4 flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                    <TabsList class="h-auto rounded-xl border bg-white p-1 shadow-sm">
                        <TabsTrigger
                            value="stock"
                            class="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground gap-2 rounded-lg px-6 py-2"
                        >
                            <Box class="h-4 w-4" />
                            Inventario Actual
                        </TabsTrigger>
                        <TabsTrigger
                            value="movements"
                            class="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground gap-2 rounded-lg px-6 py-2"
                        >
                            <History class="h-4 w-4" />
                            Historial de Unidades
                        </TabsTrigger>
                        <TabsTrigger
                            value="assignments"
                            class="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground gap-2 rounded-lg px-6 py-2"
                        >
                            <User class="h-4 w-4" />
                            Entregas a Personal
                        </TabsTrigger>
                    </TabsList>
                </div>

                <TabsContent value="stock" class="m-0 space-y-4">
                    <!-- Filters Bar -->
                    <div
                        class="flex flex-col items-center justify-between gap-4 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm md:flex-row"
                    >
                        <div class="flex w-full flex-wrap items-center gap-3 md:w-auto">
                            <div class="w-full md:w-72">
                                <Select v-model="selectedUnitId">
                                    <SelectTrigger class="h-10 border-slate-200 bg-slate-50/50">
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
                                <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Buscar EPP o Unidad..."
                                    class="h-10 border-slate-200 bg-slate-50/50 pl-10"
                                />
                            </div>
                        </div>

                        <div class="flex items-center rounded-xl bg-slate-100 p-1">
                            <button
                                @click="viewMode = 'grid'"
                                :class="['rounded-lg p-2 transition-all', viewMode === 'grid' ? 'text-primary bg-white shadow-sm' : 'text-slate-400']"
                            >
                                <LayoutGrid class="h-4 w-4" />
                            </button>
                            <button
                                @click="viewMode = 'table'"
                                :class="[
                                    'rounded-lg p-2 transition-all',
                                    viewMode === 'table' ? 'text-primary bg-white shadow-sm' : 'text-slate-400',
                                ]"
                            >
                                <List class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Data Display -->
                    <div v-if="filteredStocks?.length > 0">
                        <!-- Table View -->
                        <div v-if="viewMode === 'table'" class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
                            <Table>
                                <TableHeader class="bg-slate-50/50">
                                    <TableRow>
                                        <TableHead class="py-4 font-bold">Mina / Unidad</TableHead>
                                        <TableHead class="font-bold">EPP</TableHead>
                                        <TableHead class="font-bold">Color / Talla</TableHead>
                                        <TableHead class="text-center font-bold">Cantidad</TableHead>
                                        <TableHead class="font-bold">Estado</TableHead>
                                        <TableHead class="text-right font-bold">Acciones</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="stock in filteredStocks" :key="stock.id" class="group transition-colors hover:bg-slate-50/50">
                                        <TableCell>
                                            <div class="flex flex-col">
                                                <span class="text-xs font-bold tracking-wider text-slate-400 uppercase">{{
                                                    stock.unit.mine.name
                                                }}</span>
                                                <span class="font-semibold text-slate-900">{{ stock.unit.name }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                                    <Box class="h-5 w-5" />
                                                </div>
                                                <span class="font-medium text-slate-700">{{ stock.stockable.name }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <Badge variant="outline" class="border-slate-200 bg-white text-slate-600 capitalize">
                                                    <div
                                                        v-if="stock.color?.hex_code"
                                                        class="mr-2 h-2 w-2 rounded-full"
                                                        :style="{ backgroundColor: stock.color.hex_code }"
                                                    ></div>
                                                    {{ stock.color?.name || 'Varios' }}
                                                </Badge>
                                                <Badge
                                                    v-if="stock.size"
                                                    variant="outline"
                                                    class="border-transparent bg-slate-100 font-bold text-slate-600"
                                                >
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
                                            <Button variant="ghost" size="icon" class="hover:text-primary text-slate-400">
                                                <MoreHorizontal class="h-5 w-5" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- Grid View -->
                        <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-4">
                            <Card
                                v-for="stock in filteredStocks"
                                :key="stock.id"
                                class="group relative overflow-hidden border-none bg-white shadow-sm transition-all hover:shadow-md"
                            >
                                <CardHeader class="pb-2">
                                    <div class="mb-2 flex items-start justify-between">
                                        <Badge variant="outline" class="border-indigo-100 bg-indigo-50 px-2 py-0 font-bold text-indigo-700">
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
                                    <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3">
                                        <div class="flex flex-col">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase">Existencia</span>
                                            <span class="text-2xl font-black text-slate-900">{{ stock.quantity }}</span>
                                        </div>
                                        <div class="flex flex-col items-end">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase">Atributos</span>
                                            <div class="flex items-center gap-2">
                                                <div
                                                    v-if="stock.color"
                                                    class="h-3 w-3 rounded-full border border-slate-200"
                                                    :style="{ backgroundColor: stock.color.hex_code }"
                                                ></div>
                                                <span class="text-xs font-semibold">{{ stock.size ? 'T:' + stock.size : 'General' }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex gap-2">
                                        <Button variant="outline" size="sm" class="flex-1 gap-2 border-slate-200 text-xs">
                                            <History class="h-3.5 w-3.5" /> Movimientos
                                        </Button>
                                        <Button size="sm" variant="secondary" class="gap-2 text-xs font-bold"> Ver Detalles </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <!-- Empty State for Stock -->
                    <div
                        v-else
                        class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-slate-200 bg-white py-20 text-center"
                    >
                        <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-slate-50">
                            <Box class="h-10 w-10 text-slate-300" />
                        </div>
                        <h3 class="text-xl font-bold text-slate-900">No se encontraron existencias</h3>
                        <p class="mt-2 max-w-sm text-slate-500">
                            No hay registros de EPPs para los criterios seleccionados. Puede iniciar un envío para habilitar stock en esta unidad.
                        </p>
                        <Button @click="isTransferOpen = true" variant="outline" class="mt-6 gap-2 border-slate-200">
                            <Plus class="h-4 w-4" /> Enviar Stock Ahora
                        </Button>
                    </div>
                </TabsContent>

                <TabsContent value="movements" class="m-0">
                    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
                        <div v-if="filteredTransfers?.length > 0">
                            <Table>
                                <TableHeader class="bg-slate-50/50">
                                    <TableRow>
                                        <TableHead class="py-4 font-bold">Fecha</TableHead>
                                        <TableHead class="font-bold">Unidad</TableHead>
                                        <TableHead class="font-bold">Responsable</TableHead>
                                        <TableHead class="font-bold">Elementos</TableHead>
                                        <TableHead class="font-bold">Estado</TableHead>
                                        <TableHead class="text-right font-bold">Ver ODC</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="transfer in filteredTransfers" :key="transfer.id" class="hover:bg-slate-50/50">
                                        <TableCell class="font-medium">
                                            {{ new Date(transfer.created_at).toLocaleDateString() }}
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex flex-col">
                                                <span class="text-xs tracking-wider text-slate-400 uppercase">{{ transfer.unit.mine.name }}</span>
                                                <span class="font-semibold">{{ transfer.unit.name }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {{ transfer.staff?.names || 'Almacén Central' }}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline" class="border-blue-100 bg-blue-50 text-blue-700">
                                                {{ transfer.items?.length || 0 }} Items
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge :variant="transfer.status === 'sent' ? 'secondary' : 'default'">
                                                {{ transfer.status === 'sent' ? 'Enviado' : 'Recibido' }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <Dialog>
                                                <DialogTrigger as-child>
                                                    <Button variant="ghost" size="sm" class="text-primary text-xs font-bold"> Ver Detalles </Button>
                                                </DialogTrigger>
                                                <DialogContent class="rounded-2xl shadow-2xl sm:max-w-[500px]">
                                                    <DialogHeader>
                                                        <DialogTitle class="flex items-center gap-2 text-2xl font-bold">
                                                            <Truck class="text-primary h-6 w-6" />
                                                            Detalles del Envío
                                                        </DialogTitle>
                                                        <DialogDescription> Información de la transferencia a unidad minera. </DialogDescription>
                                                    </DialogHeader>
                                                    <div class="grid gap-4 py-4">
                                                        <div class="grid grid-cols-2 gap-4 rounded-xl bg-slate-50 p-4 text-sm">
                                                            <div>
                                                                <Label class="text-xs font-bold text-slate-500 uppercase">Origen / Enviado por</Label>
                                                                <p class="font-semibold text-slate-900">
                                                                    {{ transfer.user?.name || transfer.staff?.names || 'Almacén Central' }}
                                                                </p>
                                                            </div>
                                                            <div>
                                                                <Label class="text-xs font-bold text-slate-500 uppercase">Destino</Label>
                                                                <div class="flex flex-col">
                                                                    <span class="text-xs text-slate-500 uppercase">{{
                                                                        transfer.unit?.mine?.name
                                                                    }}</span>
                                                                    <span class="font-semibold text-slate-900">{{ transfer.unit?.name }}</span>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <Label class="text-xs font-bold text-slate-500 uppercase">Fecha de Envío</Label>
                                                                <p class="font-semibold text-slate-900">
                                                                    {{ new Date(transfer.created_at).toLocaleDateString() }} -
                                                                    {{ new Date(transfer.created_at).toLocaleTimeString() }}
                                                                </p>
                                                            </div>
                                                            <div>
                                                                <Label class="text-xs font-bold text-slate-500 uppercase">Estado</Label>
                                                                <div>
                                                                    <Badge :variant="transfer.status === 'sent' ? 'secondary' : 'default'">
                                                                        {{ transfer.status === 'sent' ? 'Enviado' : 'Recibido' }}
                                                                    </Badge>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="space-y-3">
                                                            <h4 class="flex items-center gap-2 text-sm font-bold text-slate-900">
                                                                <Package class="h-4 w-4 text-slate-500" />
                                                                Ítems Transferidos ({{ transfer.items?.length || 0 }})
                                                            </h4>
                                                            <div class="max-h-[250px] space-y-2 overflow-y-auto pr-2">
                                                                <div
                                                                    v-for="(item, idx) in transfer.items"
                                                                    :key="idx"
                                                                    class="flex flex-col rounded-xl border border-slate-100 bg-white p-3"
                                                                >
                                                                    <div class="flex items-start justify-between">
                                                                        <span class="text-sm font-bold text-slate-800">
                                                                            {{
                                                                                epps.find(
                                                                                    (e) => String(e.id) === String(item.stockable_id || item.epp_id),
                                                                                )?.name ||
                                                                                item.stockable?.name ||
                                                                                `Ítem #${item.stockable_id || item.epp_id}`
                                                                            }}
                                                                        </span>
                                                                        <Badge variant="secondary" class="font-bold">
                                                                            {{ item.quantity }} Unid.
                                                                        </Badge>
                                                                    </div>
                                                                    <div class="mt-2 flex gap-4 text-xs text-slate-500">
                                                                        <span class="flex items-center gap-1">
                                                                            <span class="font-medium text-slate-400">Talla:</span>
                                                                            <strong class="text-slate-700">{{ item.size || 'General' }}</strong>
                                                                        </span>
                                                                        <span class="flex items-center gap-1">
                                                                            <span class="font-medium text-slate-400">Color:</span>
                                                                            <div class="flex items-center gap-1">
                                                                                <div
                                                                                    class="h-2 w-2 rounded-full border border-slate-200"
                                                                                    :style="{
                                                                                        backgroundColor: colors.find(
                                                                                            (c) => String(c.id) === String(item.color_id),
                                                                                        )?.hex_code,
                                                                                    }"
                                                                                ></div>
                                                                                <strong class="text-slate-700">{{
                                                                                    colors.find((c) => String(c.id) === String(item.color_id))
                                                                                        ?.name || 'Varios'
                                                                                }}</strong>
                                                                            </div>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <div
                                                                    v-if="!transfer.items || transfer.items.length === 0"
                                                                    class="py-4 text-center text-sm text-slate-500"
                                                                >
                                                                    No hay detalles de ítems disponibles.
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </DialogContent>
                                            </Dialog>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-20 text-center">
                            <History class="mb-4 h-12 w-12 text-slate-200" />
                            <h3 class="text-lg font-bold text-slate-900">Sin movimientos recientes</h3>
                            <p class="text-slate-500">No se han registrado transferencias para esta selección.</p>
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="assignments" class="m-0">
                    <div v-if="filteredStaffHistories?.length > 0" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="hist in filteredStaffHistories"
                            :key="hist.id"
                            class="flex flex-col gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all hover:shadow-md"
                        >
                            <div class="flex items-start justify-between border-b border-slate-50 pb-3">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <Badge
                                            variant="secondary"
                                            class="rounded-md border-none bg-indigo-50 px-2 py-0.5 text-[10px] font-black text-indigo-600 uppercase"
                                        >
                                            {{ hist.reason }}
                                        </Badge>
                                        <span class="text-[10px] font-medium text-slate-400">
                                            {{ new Date(hist.created_at).toLocaleString() }}
                                        </span>
                                    </div>
                                    <div class="group-hover:text-primary font-bold text-slate-900 transition-colors">
                                        {{ hist.staff?.names }}
                                    </div>
                                    <div v-if="hist.staff?.staffable" class="flex items-center gap-1 text-[10px] font-medium text-slate-400">
                                        <Mountain class="h-3 w-3" />
                                        <template v-if="hist.staff.staffable_type === 'App\\Models\\Cafe'">
                                            {{ hist.staff.staffable.unit?.mine?.name }} - {{ hist.staff.staffable.unit?.name }}
                                        </template>
                                        <template v-else>
                                            {{ hist.staff.staffable.name }}
                                        </template>
                                    </div>
                                </div>
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-50 text-slate-400">
                                    <User class="h-5 w-5" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-for="(item, idx) in hist.items"
                                    :key="idx"
                                    class="flex flex-col rounded-xl border border-slate-100/50 bg-slate-50 p-3"
                                >
                                    <div class="flex items-start justify-between">
                                        <span class="text-xs font-bold text-slate-700">
                                            {{
                                                epps.find((e) => String(e.id) === String(item.epp_id))?.name || item.epp_name || `EPP #${item.epp_id}`
                                            }}
                                        </span>
                                        <Badge variant="outline" class="h-5 border-slate-200 bg-white px-1.5 text-[10px]">
                                            {{ item.quantity }} Unid.
                                        </Badge>
                                    </div>
                                    <div class="mt-1 flex gap-3 text-[10px] text-slate-500">
                                        <span class="flex items-center gap-1 font-medium">
                                            <div
                                                class="h-2 w-2 rounded-full bg-slate-200"
                                                :style="{ backgroundColor: colors.find((c) => String(c.id) === String(item.color_id))?.hex_code }"
                                            ></div>
                                            Color: {{ colors.find((c) => String(c.id) === String(item.color_id))?.name || 'Varios' }}
                                        </span>
                                        <span class="font-medium"
                                            >Talla: <strong class="text-slate-700">{{ item.size || '-' }}</strong></span
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="mt-auto flex items-center justify-between border-t border-slate-50 pt-3 text-[10px]">
                                <span class="text-slate-400"
                                    >Registrado por: <strong>{{ hist.user?.name || 'Sistema' }}</strong></span
                                >
                                <Button variant="ghost" size="sm" class="h-6 text-[10px] font-black text-indigo-600">VER DETALLES</Button>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-slate-200 bg-white py-20 text-center"
                    >
                        <User class="mb-4 h-12 w-12 text-slate-200" />
                        <h3 class="text-lg font-bold text-slate-900">Sin entregas a personal</h3>
                        <p class="max-w-sm text-slate-500">No se han registrado entregas individuales en esta unidad/búsqueda.</p>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
