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

const props = defineProps<{
    roles: Array<{ id: number, name: string }>;
    clothes: Array<{ id: number, name: string, roles: Array<{ id: number, pivot: { cafe_id: number } }> }>;
    cafes: Array<{ id: number, name: string }>;
}>();

const newClothName = ref('');
const isCreating = ref(false);
const searchQuery = ref('');
const selectedCafeId = ref(props.cafes.length > 0 ? String(props.cafes[0].id) : '');

// Filtrar roles según búsqueda
const filteredRoles = computed(() => {
    if (!searchQuery.value) return props.roles;
    return props.roles.filter(role => 
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
</script>

<template>
    <Head title="Gestión de Ropa por Rol" />

    <AppLayout :breadcrumbs="[
        { title: 'Personal', href: route('staff.index') },
        { title: 'Ropa', href: route('clothes.index') },
        { title: 'Matriz de Asignación', href: route('clothes.manage') }
    ]">
        <div class="flex flex-col h-full w-full overflow-hidden p-4 sm:p-6 gap-6">
            
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 flex-none">
                 <div class="min-w-0 flex-1">
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight flex items-center gap-2">
                        <Briefcase class="h-5 w-5 sm:h-6 sm:w-6 text-primary" />
                        <span>Asignación de Prendas por Rol</span>
                    </h1>
                    <p class="text-muted-foreground text-xs sm:text-sm mt-1">
                        Selecciona las prendas asignadas a cada rol/cargo
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
            <div class="bg-card text-card-foreground rounded-xl border shadow-sm p-4 bg-white flex flex-col sm:flex-row gap-4 items-center flex-none">
                <div class="flex-1 w-full flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <div class="relative max-w-md">
                            <Coffee class="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                            <Select v-model="selectedCafeId">
                                <SelectTrigger class="pl-9 w-full">
                                    <SelectValue placeholder="Seleccionar Café" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="cafe in cafes" :key="cafe.id" :value="String(cafe.id)">
                                        {{ cafe.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="relative max-w-md">
                            <Briefcase class="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                            <Input 
                                v-model="searchQuery"
                                placeholder="Buscar rol..." 
                                class="pl-9 w-full"
                            />
                        </div>
                    </div>
                    <div class="flex-1 flex gap-4">
                        <div class="relative flex-1 max-w-md">
                            <Shirt class="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                            <Input 
                                v-model="newClothName" 
                                placeholder="Nueva prenda..." 
                                class="pl-9 w-full"
                                @keyup.enter="createCloth" 
                            />
                        </div>
                        <Button @click="createCloth" :disabled="isCreating || !newClothName" class="sm:w-auto">
                            <Plus v-if="!isCreating" class="h-4 w-4 mr-2" />
                            {{ isCreating ? 'Guardando...' : 'Agregar' }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- All Clothes Tags Section -->
            <Card class="flex-none">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base flex items-center gap-2">
                        <Shirt class="h-4 w-4" />
                        Todas las Prendas Disponibles
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-2">
                        <Badge 
                            v-for="cloth in clothes" 
                            :key="cloth.id"
                            variant="outline"
                            class="cursor-pointer group relative px-3 py-1.5"
                            @click="deleteCloth(cloth.id)"
                        >
                            <span>{{ cloth.name }}</span>
                            <X class="h-3 w-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity text-red-500" />
                            <span class="text-xs text-muted-foreground ml-1">
                                ({{ cloth.roles.length }})
                            </span>
                        </Badge>
                        <div v-if="clothes.length === 0" class="text-center py-4 text-muted-foreground">
                            No hay prendas registradas. ¡Agrega la primera!
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Roles Cards Grid -->
            <div class="flex-1 overflow-auto custom-scrollbar">
                <div v-if="filteredRoles.length === 0" class="text-center py-12">
                    <Briefcase class="h-12 w-12 mx-auto text-gray-300 mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No se encontraron roles</h3>
                    <p class="text-gray-500">Intenta con otros términos de búsqueda</p>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pb-4">
                    <Card 
                        v-for="role in filteredRoles" 
                        :key="role.id"
                        class="group hover:shadow-md transition-shadow"
                        :class="{
                            'border-blue-200 bg-blue-50/50': getClothesForRole(role.id).length > 0,
                            'border-gray-200': getClothesForRole(role.id).length === 0
                        }"
                    >
                        <CardHeader class="pb-3">
                            <div class="flex justify-between items-start">
                                <CardTitle class="text-base flex items-center gap-2">
                                    <Briefcase class="h-4 w-4 text-primary" />
                                    <span class="truncate" :title="role.name">{{ role.name }}</span>
                                </CardTitle>
                                <Badge 
                                    variant="secondary"
                                    class="flex-shrink-0 ml-2"
                                    :class="{
                                        'bg-blue-100 text-blue-800': getClothesForRole(role.id).length > 0,
                                        'bg-gray-100 text-gray-600': getClothesForRole(role.id).length === 0
                                    }"
                                >
                                    {{ getClothesForRole(role.id).length }}
                                </Badge>
                            </div>
                        </CardHeader>
                        
                        <CardContent>
                            <!-- Prendas asignadas a este rol -->
                            <div class="mb-4">
                                <p class="text-xs font-medium text-gray-500 mb-2">PRENDAS ASIGNADAS:</p>
                                <div class="flex flex-wrap gap-2 min-h-[32px]">
                                    <Badge 
                                        v-for="cloth in getClothesForRole(role.id)" 
                                        :key="cloth.id"
                                        variant="default"
                                        class="cursor-pointer group"
                                        @click="toggleRole(cloth.id, role.id, true)"
                                    >
                                        <span>{{ cloth.name }}</span>
                                        <X class="h-3 w-3 ml-1 group-hover:scale-110 transition-transform" />
                                    </Badge>
                                    <div 
                                        v-if="getClothesForRole(role.id).length === 0"
                                        class="text-xs text-gray-400 italic py-1"
                                    >
                                        Sin prendas asignadas
                                    </div>
                                </div>
                            </div>

                            <!-- Separador -->
                            <div class="relative my-4">
                                <div class="absolute inset-0 flex items-center">
                                    <div class="w-full border-t border-gray-200"></div>
                                </div>
                                <div class="relative flex justify-center text-xs">
                                    <span class="px-2 bg-white text-gray-500">ASIGNAR NUEVAS PRENDAS</span>
                                </div>
                            </div>

                            <!-- Prendas no asignadas -->
                            <div class="space-y-2">
                                <div 
                                    v-for="cloth in clothes.filter(c => !hasRole(c, role.id))" 
                                    :key="cloth.id"
                                    class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer group/item"
                                    @click="toggleRole(cloth.id, role.id, false)"
                                >
                                    <div class="flex items-center gap-2">
                                        <div class="h-6 w-6 rounded-full border border-gray-200 flex items-center justify-center group-hover/item:border-blue-300">
                                            <Plus class="h-3 w-3 text-gray-400 group-hover/item:text-blue-500" />
                                        </div>
                                        <span class="text-sm">{{ cloth.name }}</span>
                                    </div>
                                    <Badge variant="outline" class="text-xs">
                                        {{ cloth.roles.length }} roles
                                    </Badge>
                                </div>
                                
                                <div 
                                    v-if="clothes.filter(c => !hasRole(c, role.id)).length === 0"
                                    class="text-center py-4 text-gray-400 text-sm"
                                >
                                    Todas las prendas están asignadas a este rol
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

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