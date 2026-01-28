<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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
    AlertCircle
} from 'lucide-vue-next';
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
    clothes: Array<{ id: number, name: string }>;
    colors: Array<{ id: number, name: string, hex_code?: string }>;
    cafes: Array<{ id: number, name: string }>;
    inventory: Array<{
        id: number;
        cloth_id: number;
        color_id: number;
        cafe_id: number | null;
        quantity: number;
        cloth: { name: string };
        color: { name: string, hex_code?: string };
        cafe?: { name: string };
    }>;
}>();

const searchQuery = ref('');
const selectedCafeId = ref('all');
const selectedClothId = ref('all');

// Filtrado de inventario
const filteredInventory = computed(() => {
    return props.inventory.filter(item => {
        const matchesSearch = item.cloth.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                            item.color.name.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchesCafe = selectedCafeId.value === 'all' || String(item.cafe_id) === selectedCafeId.value;
        const matchesCloth = selectedClothId.value === 'all' || String(item.cloth_id) === selectedClothId.value;
        
        return matchesSearch && matchesCafe && matchesCloth;
    });
});

// Estado para nuevos registros
const isAddStockOpen = ref(false);
const isNewColorOpen = ref(false);

const stockForm = ref({
    cloth_id: '',
    color_id: '',
    cafe_id: '',
    quantity: 0,
    action: 'add' as 'add' | 'set'
});

const colorForm = ref({
    name: '',
    hex_code: '#000000'
});

const handleAddStock = () => {
    router.post(route('inventory.update'), stockForm.value, {
        onSuccess: () => {
            isAddStockOpen.value = false;
            stockForm.value = {
                cloth_id: '',
                color_id: '',
                cafe_id: '',
                quantity: 0,
                action: 'add'
            };
        }
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
    if (quantity < 10) return { label: 'Bajo Stock', variant: 'secondary' as const };
    return { label: 'En Stock', variant: 'outline' as const };
};

</script>

<template>
    <Head title="Inventario de Ropa" />

    <AppLayout :breadcrumbs="[
        { title: 'Personal', href: route('staff.index') },
        { title: 'Ropa', href: route('clothes.index') },
        { title: 'Inventario', href: route('inventory.index') }
    ]">
        <div class="flex flex-col h-full w-full overflow-hidden p-4 sm:p-6 gap-6">
            
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 flex-none">
                <div class="min-w-0 flex-1">
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight flex items-center gap-3">
                        <Package class="h-8 w-8 text-primary" />
                        <span>Inventario de Prendas</span>
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        Control de existencias por prenda, color y café
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Dialog v-model:open="isNewColorOpen">
                        <DialogTrigger as-child>
                            <Button variant="outline" class="gap-2">
                                <Palette class="h-4 w-4" />
                                Nuevo Color
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Crear Nuevo Color</DialogTitle>
                                <DialogDescription>Agregue un color para clasificar las prendas</DialogDescription>
                            </DialogHeader>
                            <div class="grid gap-4 py-4">
                                <div class="grid gap-2">
                                    <Label for="colorName">Nombre del Color</Label>
                                    <Input id="colorName" v-model="colorForm.name" placeholder="Ej: Azul Marino" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="colorHex">Código Hex (Opcional)</Label>
                                    <div class="flex gap-2">
                                        <Input id="colorHex" v-model="colorForm.hex_code" type="color" class="w-12 h-10 p-1" />
                                        <Input v-model="colorForm.hex_code" placeholder="#000000" class="flex-1" />
                                    </div>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button @click="handleCreateColor" :disabled="!colorForm.name">Guardar Color</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <Dialog v-model:open="isAddStockOpen">
                        <DialogTrigger as-child>
                            <Button class="gap-2">
                                <Plus class="h-4 w-4" />
                                Carga de Stock
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-[500px]">
                            <DialogHeader>
                                <DialogTitle>Cargar Stock al Inventario</DialogTitle>
                                <DialogDescription>Actualice las cantidades disponibles</DialogDescription>
                            </DialogHeader>
                            <div class="grid gap-4 py-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label>Prenda</Label>
                                        <Select v-model="stockForm.cloth_id">
                                            <SelectTrigger><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="cloth in clothes" :key="cloth.id" :value="String(cloth.id)">{{ cloth.name }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>Color</Label>
                                        <Select v-model="stockForm.color_id">
                                            <SelectTrigger><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="color in colors" :key="color.id" :value="String(color.id)">{{ color.name }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div class="grid gap-2">
                                    <Label>Café (Destino del Stock)</Label>
                                    <Select v-model="stockForm.cafe_id">
                                        <SelectTrigger><SelectValue placeholder="Seleccionar Café" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="cafe in cafes" :key="cafe.id" :value="String(cafe.id)">{{ cafe.name }}</SelectItem>
                                        </SelectContent>
                                    </Select>
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
                                                <SelectItem value="set">Fijar cantidad exacta</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button @click="handleAddStock" :disabled="!stockForm.cloth_id || !stockForm.color_id || !stockForm.cafe_id">
                                    Confirmar Stock
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 flex-none">
                <Card class="bg-gradient-to-br from-indigo-50 to-white border-indigo-100">
                    <CardContent class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-indigo-700 uppercase tracking-wider">Total Unidades</p>
                            <p class="text-2xl font-bold text-indigo-900 mt-1">
                                {{ inventory.reduce((acc, item) => acc + item.quantity, 0) }}
                            </p>
                        </div>
                        <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                            <Package class="h-5 w-5 text-indigo-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="bg-gradient-to-br from-red-50 to-white border-red-100">
                    <CardContent class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-red-700 uppercase tracking-wider">Sin Existencias</p>
                            <p class="text-2xl font-bold text-red-900 mt-1">
                                {{ inventory.filter(i => i.quantity <= 0).length }}
                            </p>
                        </div>
                        <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center">
                            <AlertCircle class="h-5 w-5 text-red-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="bg-gradient-to-br from-amber-50 to-white border-amber-100">
                    <CardContent class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-amber-700 uppercase tracking-wider">Prendas Únicas</p>
                            <p class="text-2xl font-bold text-amber-900 mt-1">{{ clothes.length }}</p>
                        </div>
                        <div class="h-10 w-10 rounded-full bg-amber-100 flex items-center justify-center">
                            <Shirt class="h-5 w-5 text-amber-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="bg-gradient-to-br from-emerald-50 to-white border-emerald-100">
                    <CardContent class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-emerald-700 uppercase tracking-wider">Colores Activos</p>
                            <p class="text-2xl font-bold text-emerald-900 mt-1">{{ colors.length }}</p>
                        </div>
                        <div class="h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center">
                            <Palette class="h-5 w-5 text-emerald-600" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <div class="bg-card rounded-xl border shadow-sm p-4 bg-white flex flex-col lg:flex-row gap-4 flex-none">
                <div class="flex-1 relative">
                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input v-model="searchQuery" placeholder="Buscar por prenda o color..." class="pl-9" />
                </div>
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex items-center gap-2">
                        <Coffee class="h-4 w-4 text-muted-foreground" />
                        <Select v-model="selectedCafeId">
                            <SelectTrigger class="w-[180px]">
                                <SelectValue placeholder="Todos los Cafés" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos los Cafés</SelectItem>
                                <SelectItem v-for="cafe in cafes" :key="cafe.id" :value="String(cafe.id)">{{ cafe.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex items-center gap-2">
                        <Shirt class="h-4 w-4 text-muted-foreground" />
                        <Select v-model="selectedClothId">
                            <SelectTrigger class="w-[180px]">
                                <SelectValue placeholder="Todas las Prendas" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todas las Prendas</SelectItem>
                                <SelectItem v-for="cloth in clothes" :key="cloth.id" :value="String(cloth.id)">{{ cloth.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>

            <!-- Inventory Grid -->
            <div class="flex-1 overflow-auto">
                <div v-if="filteredInventory.length === 0" class="flex flex-col items-center justify-center h-64 border-2 border-dashed rounded-xl bg-gray-50/50">
                    <Package class="h-12 w-12 text-gray-300 mb-4" />
                    <p class="text-gray-500 font-medium">No se encontraron items en el inventario</p>
                    <p class="text-gray-400 text-sm mt-1">Ajusta los filtros o agrega nuevo stock</p>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 pb-4">
                    <Card 
                        v-for="item in filteredInventory" 
                        :key="item.id"
                        class="group hover:shadow-md transition-all duration-300 overflow-hidden border-zinc-200"
                    >
                        <div class="h-2 w-full" :style="{ backgroundColor: item.color.hex_code || '#cbd5e1' }"></div>
                        <CardHeader class="pb-2">
                            <div class="flex justify-between items-start">
                                <div class="flex items-center gap-2">
                                    <div class="p-2 rounded-lg bg-zinc-100 group-hover:bg-primary/10 transition-colors">
                                        <Shirt class="h-4 w-4 text-zinc-600 group-hover:text-primary" />
                                    </div>
                                    <div>
                                        <CardTitle class="text-lg">{{ item.cloth.name }}</CardTitle>
                                        <CardDescription class="flex items-center gap-1">
                                            <Palette class="h-3 w-3" />
                                            {{ item.color.name }}
                                        </CardDescription>
                                    </div>
                                </div>
                                <Badge :variant="getStockStatus(item.quantity).variant" class="shadow-none">
                                    {{ getStockStatus(item.quantity).label }}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent class="pb-4">
                            <div class="mt-2 p-4 bg-zinc-50 rounded-xl border border-zinc-100">
                                <p class="text-xs text-zinc-500 uppercase font-semibold tracking-wider mb-1">Stock Disponible</p>
                                <div class="flex items-end justify-between">
                                    <span class="text-4xl font-black text-zinc-900 leading-none">{{ item.quantity }}</span>
                                    <span class="text-xs font-medium text-zinc-500 bg-white px-2 py-1 rounded-md border border-zinc-200">unidades</span>
                                </div>
                            </div>
                            
                            <div class="mt-4 flex items-center justify-between text-xs pt-4 border-t border-zinc-100">
                                <div class="flex items-center gap-1.5 text-zinc-600">
                                    <Coffee class="h-3 w-3" />
                                    <span class="font-medium">{{ item.cafe?.name || 'Stock General' }}</span>
                                </div>
                                <button class="text-primary hover:underline font-medium flex items-center gap-1 transition-all">
                                    Historial
                                    <ArrowUpRight class="h-3 w-3" />
                                </button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Footer Info -->
            <div class="p-4 border-t bg-gray-50 text-sm text-muted-foreground flex flex-col sm:flex-row justify-between items-center gap-4 flex-none rounded-b-xl">
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-2">
                        <div class="h-2 w-2 rounded-full bg-red-500 animate-pulse"></div>
                        <span>{{ inventory.filter(i => i.quantity <= 0).length }} items agotados</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-2 w-2 rounded-full bg-amber-500"></div>
                        <span>{{ inventory.filter(i => i.quantity > 0 && i.quantity < 10).length }} stock bajo</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs font-medium">
                    <History class="h-4 w-4" />
                    Última actualización: {{ new Date().toLocaleDateString() }}
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
    height: 8px;
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
