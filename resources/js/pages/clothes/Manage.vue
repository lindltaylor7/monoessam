<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Trash2, Plus, ArrowLeft, Shirt, Briefcase, Check, X, Coffee, Package } from 'lucide-vue-next';

import { 
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow 
} from '@/components/ui/table';
import { Label } from '@/components/ui/label';
import { 
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const props = defineProps<{
    roles: Array<{ id: number, name: string }>;
    clothes: Array<{ id: number, name: string, quantity: number, roles: Array<{ id: number, pivot: { cafe_id: number } }> }>;
    cafes: Array<{ 
        id: number, 
        name: string, 
        roles: Array<{ id: number }>,
        unit: { name: string, mine: { name: string } } 
    }>;
}>();

const newClothName = ref('');
const isCreating = ref(false);
const searchQuery = ref('');
const selectedCafeId = ref(props.cafes.length > 0 ? String(props.cafes[0].id) : '');
const clothSearchQuery = ref('');

// Filtrar prendas según búsqueda
const filteredClothes = computed(() => {
    if (!clothSearchQuery.value) return props.clothes;
    return props.clothes.filter(cloth => 
        cloth.name.toLowerCase().includes(clothSearchQuery.value.toLowerCase())
    );
});

// Filtrar roles según el café seleccionado y la búsqueda
const filteredRoles = computed(() => {
    const currentCafe = props.cafes.find(c => String(c.id) === selectedCafeId.value);
    const cafeRoleIds = currentCafe?.roles?.map(r => r.id) || [];
    
    const roles = props.roles.filter(role => cafeRoleIds.includes(role.id));

    if (!searchQuery.value) return roles;
    return roles.filter(role => 
        role.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

// Obtener prendas asignadas a un rol específico y café seleccionado
const getClothesForRole = (roleId: number) => {
    return props.clothes.filter(cloth => 
        cloth.roles.some(role => role.id === roleId && String(role.pivot.cafe_id) === selectedCafeId.value)
    );
};

// Verificar si una prenda está asignada a un rol en el café seleccionado
const hasRole = (cloth: any, roleId: number) => {
    return cloth.roles.some((r: any) => r.id === roleId && String(r.pivot.cafe_id) === selectedCafeId.value);
};

const createCloth = () => {
    if(!newClothName.value) return;
    isCreating.value = true;
    router.post(route('clothes.store'), {
        name: newClothName.value
    }, {
        onSuccess: () => newClothName.value = '',
        onFinish: () => isCreating.value = false
    });
};

const updateQuantity = (clothId: number, qty: number) => {
    if (qty < 0) return;
    router.put(route('clothes.update-quantity', clothId), {
        quantity: qty
    }, {
        preserveScroll: true
    });
};

const deleteCloth = (id: number) => {
    if(confirm('¿Seguro que deseas eliminar esta prenda y todas sus asignaciones?')) {
        router.delete(route('clothes.destroy', id), {
             preserveScroll: true
        });
    }
};

const toggleRole = (clothId: number, roleId: number, currentStatus: boolean) => {
    if (!selectedCafeId.value) {
        alert('Por favor selecciona un café primero');
        return;
    }
    router.post(route('clothes.assign-role'), {
        cloth_id: clothId,
        role_id: roleId,
        cafe_id: selectedCafeId.value,
        action: currentStatus ? 'detach' : 'attach'
    }, {
        preserveScroll: true,
        preserveState: true
    });
};

// Calcular estadísticas
const stats = computed(() => {
    const totalAssignments = props.roles.reduce((acc, role) => {
        return acc + getClothesForRole(role.id).length;
    }, 0);
    
    const averagePerRole = props.roles.length > 0 
        ? (totalAssignments / props.roles.length).toFixed(1) 
        : '0';
    
    return {
        totalAssignments,
        averagePerRole,
        rolesWithAssignments: props.roles.filter(role => getClothesForRole(role.id).length > 0).length
    };
});

const selectedCafeLabel = computed(() => {
    const cafe = props.cafes.find(c => String(c.id) === selectedCafeId.value);
    if (!cafe) return 'Seleccionar';
    return `${cafe.name} - ${cafe.unit?.name} - ${cafe.unit?.mine?.name}`;
});
</script>

<template>
    <Head title="Asignación de Prendas" />

    <AppLayout :breadcrumbs="[
        { title: 'Personal', href: route('staff.index') },
        { title: 'Ropa', href: route('clothes.index') },
        { title: 'Matriz de Asignación', href: route('clothes.manage') }
    ]">
        <div class="flex flex-col h-full w-full overflow-hidden p-4 sm:p-6 gap-6">
            
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 flex-none">
                 <div class="min-w-0 flex-1">
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight flex items-center gap-2 text-slate-900">
                        <div class="p-2 bg-indigo-100 rounded-xl">
                            <Briefcase class="h-6 w-6 text-indigo-600" />
                        </div>
                        <span>Matriz de Equipamiento por Cargo</span>
                    </h1>
                    <p class="text-muted-foreground text-xs sm:text-sm mt-1">
                        Define qué EPPs y prendas corresponden a cada cargo según el café seleccionado
                    </p>
                 </div>
                  <div class="flex items-center gap-2">
                    <Link :href="route('inventory.index')">
                        <Button variant="outline" size="sm" class="gap-2">
                            <Package class="h-4 w-4" />
                            <span class="hidden sm:inline">Inventario</span>
                        </Button>
                    </Link>
                    <Link :href="route('clothes.index')" class="flex-shrink-0">
                        <Button variant="outline" size="sm" class="gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            <span class="hidden sm:inline">Volver al listado</span>
                            <span class="sm:hidden">Volver</span>
                        </Button>
                    </Link>
                  </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 flex-none">
                <Card class="bg-gradient-to-br from-blue-50 to-white border-blue-100">
                    <CardContent class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-blue-700">Total Asignaciones</p>
                            <p class="text-2xl font-bold text-blue-900 mt-1">{{ stats.totalAssignments }}</p>
                        </div>
                        <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                            <Shirt class="h-5 w-5 text-blue-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="bg-gradient-to-br from-green-50 to-white border-green-100">
                    <CardContent class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-green-700">Promedio por Rol</p>
                            <p class="text-2xl font-bold text-green-900 mt-1">{{ stats.averagePerRole }}</p>
                        </div>
                        <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                            <Briefcase class="h-5 w-5 text-green-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="bg-gradient-to-br from-purple-50 to-white border-purple-100">
                    <CardContent class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-purple-700">Roles con Prendas</p>
                            <p class="text-2xl font-bold text-purple-900 mt-1">{{ stats.rolesWithAssignments }}/{{ roles.length }}</p>
                        </div>
                        <div class="h-10 w-10 rounded-full bg-purple-100 flex items-center justify-center">
                            <Check class="h-5 w-5 text-purple-600" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Action Section -->
            <div class="bg-white rounded-2xl border shadow-sm p-4 flex flex-col md:flex-row gap-4 items-center flex-none">
                <div class="flex-1 w-full flex flex-col md:flex-row gap-4 items-center">
                    <div class="w-full md:w-[320px] shrink-0">
                        <Label class="text-[10px] font-black uppercase text-slate-400 mb-1.5 block">Unidad / Café</Label>
                        <Select v-model="selectedCafeId">
                            <SelectTrigger class="h-10 bg-slate-50 border-none shadow-none focus:ring-1 w-full overflow-hidden">
                                <div class="truncate text-xs font-bold text-slate-700">
                                    {{ selectedCafeLabel }}
                                </div>
                            </SelectTrigger>
                            <SelectContent class="max-w-[400px]">
                                <SelectItem v-for="cafe in cafes" :key="cafe.id" :value="String(cafe.id)">
                                    <div class="flex flex-col py-1">
                                        <span class="font-bold text-slate-900">{{ cafe.name }}</span>
                                        <span class="text-[10px] text-slate-400 uppercase tracking-tighter">
                                            {{ cafe.unit?.mine?.name }} — {{ cafe.unit?.name }}
                                        </span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="w-full md:w-64">
                        <Label class="text-[10px] font-black uppercase text-slate-400 mb-1.5 block">Buscar Cargo</Label>
                        <div class="relative">
                            <Briefcase class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                            <Input 
                                v-model="searchQuery"
                                placeholder="Filtrar columnas..." 
                                class="pl-9 h-10 bg-slate-50 border-none shadow-none focus:ring-1"
                            />
                        </div>
                    </div>

                    <div class="w-full md:w-64">
                        <Label class="text-[10px] font-black uppercase text-slate-400 mb-1.5 block">Buscar Prenda</Label>
                        <div class="relative">
                            <Shirt class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                            <Input 
                                v-model="clothSearchQuery"
                                placeholder="Filtrar filas..." 
                                class="pl-9 h-10 bg-slate-50 border-none shadow-none focus:ring-1"
                            />
                        </div>
                    </div>

                    <div class="flex-1 w-full pt-5">
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <Plus class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                                <Input 
                                    v-model="newClothName" 
                                    placeholder="Nueva prenda..." 
                                    class="pl-9 h-10 bg-slate-50 border-none shadow-none focus:ring-1"
                                    @keyup.enter="createCloth" 
                                />
                            </div>
                            <Button @click="createCloth" :disabled="isCreating || !newClothName" class="h-10 bg-indigo-600 hover:bg-indigo-700 shadow-md">
                                {{ isCreating ? '...' : 'Agregar' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Matrix Table Section -->
            <Card class="flex-1 overflow-hidden flex flex-col rounded-2xl border-none shadow-xl bg-white">
                <CardContent class="p-0 flex-1 overflow-hidden flex flex-col">
                    <div class="flex-1 overflow-auto custom-scrollbar">
                        <Table class="relative w-full border-collapse">
                            <TableHeader class="sticky top-0 z-20 bg-slate-50/95 backdrop-blur-sm border-b shadow-sm">
                                <TableRow class="hover:bg-transparent">
                                    <TableHead class="w-[250px] bg-indigo-50/50 p-4 border-r sticky left-0 z-30">
                                        <div class="flex flex-col gap-1">
                                            <span class="text-[10px] font-black uppercase text-indigo-400 tracking-widest">Dimensión A</span>
                                            <span class="text-sm font-black text-indigo-900 uppercase italic">Descripción del Item</span>
                                        </div>
                                    </TableHead>
                                    <TableHead class="w-20 p-4 text-center border-r bg-indigo-50/30">
                                        <span class="text-[10px] font-black uppercase text-indigo-400 tracking-widest block mb-1">Cant.</span>
                                        <span class="text-xs font-bold text-indigo-800">Std.</span>
                                    </TableHead>
                                    
                                    <!-- Rotated Headers for Roles -->
                                    <TableHead 
                                        v-for="role in filteredRoles" 
                                        :key="role.id" 
                                        class="p-0 border-r min-w-[50px] h-[160px] relative font-medium group transition-colors hover:bg-slate-100/50"
                                    >
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <div class="transform -rotate-90 whitespace-nowrap text-[10px] font-black uppercase tracking-tighter text-slate-500 group-hover:text-indigo-600 transition-colors px-2 w-[140px] text-left">
                                                {{ role.name }}
                                            </div>
                                        </div>
                                    </TableHead>

                                    <TableHead class="w-16 p-4 text-center"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="cloth in filteredClothes" :key="cloth.id" class="group hover:bg-slate-50/50 transition-colors border-b">
                                    <TableCell class="p-4 font-bold text-slate-700 sticky left-0 z-10 bg-white group-hover:bg-slate-50 border-r shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-indigo-50 group-hover:text-indigo-500 transition-colors">
                                                <Shirt class="h-4 w-4" />
                                            </div>
                                            <span class="text-sm uppercase tracking-tight">{{ cloth.name }}</span>
                                        </div>
                                    </TableCell>
                                    
                                    <TableCell class="p-0 border-r">
                                        <input 
                                            type="number" 
                                            v-model="cloth.quantity" 
                                            @change="updateQuantity(cloth.id, Number(cloth.quantity))"
                                            class="w-full h-full bg-transparent text-center text-xs font-bold text-slate-600 focus:outline-none focus:bg-indigo-50 focus:text-indigo-600 transition-colors"
                                        />
                                    </TableCell>

                                    <!-- Matrix Intersection Points -->
                                    <TableCell 
                                        v-for="role in filteredRoles" 
                                        :key="role.id" 
                                        class="p-0 border-r text-center group/cell cursor-pointer relative"
                                        @click="toggleRole(cloth.id, role.id, hasRole(cloth, role.id))"
                                    >
                                        <div 
                                            class="absolute inset-0 transition-colors"
                                            :class="hasRole(cloth, role.id) ? 'bg-indigo-500/5' : 'group-hover/cell:bg-slate-100/50'"
                                        ></div>
                                        <div class="relative py-3">
                                            <div 
                                                v-if="hasRole(cloth, role.id)"
                                                class="mx-auto h-6 w-6 rounded-md bg-indigo-600 flex items-center justify-center transform scale-110 shadow-sm transition-transform group-hover/cell:scale-125"
                                            >
                                                <span class="text-[10px] font-black text-white">X</span>
                                            </div>
                                            <div 
                                                v-else
                                                class="mx-auto h-5 w-5 rounded-md border-2 border-slate-100 opacity-0 group-hover/cell:opacity-100 transition-opacity"
                                            ></div>
                                        </div>
                                    </TableCell>

                                    <TableCell class="p-4 text-center">
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button variant="ghost" size="sm" @click="deleteCloth(cloth.id)" class="text-slate-300 hover:text-rose-500 transition-colors h-8 w-8 p-0">
                                                        <Trash2 class="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent><p>Eliminar prenda del catálogo</p></TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </TableCell>
                                </TableRow>

                                <TableRow v-if="filteredClothes.length === 0">
                                    <TableCell :colspan="filteredRoles.length + 3" class="h-64 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="h-12 w-12 bg-slate-50 rounded-full flex items-center justify-center">
                                                <Shirt class="h-6 w-6 text-slate-200" />
                                            </div>
                                            <p class="text-slate-400 font-medium italic">No se encontraron prendas con los filtros actuales.</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <!-- Footer Info -->
            <div class="p-4 border-t bg-gray-50 text-sm text-muted-foreground flex flex-col sm:flex-row justify-between items-center gap-2 flex-none">
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1">
                        <Briefcase class="h-3 w-3" />
                        {{ roles.length }} Roles
                    </span>
                    <span class="flex items-center gap-1">
                        <Shirt class="h-3 w-3" />
                        {{ clothes.length }} Prendas
                    </span>
                </div>
                <div class="text-xs text-gray-500">
                    Haz clic en una prenda para asignarla o quitarla de un rol
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Scrollbar moderno */
.custom-scrollbar::-webkit-scrollbar {
    height: 10px;
    width: 10px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f8fafc;
    border-radius: 5px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 5px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Transiciones suaves */
.group:hover .group-hover\:scale-110 {
    transform: scale(1.1);
}
</style>