<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    Coffee, 
    ShieldCheck, 
    Plus, 
    Search,
    UserCircle,
    CheckCircle2,
    Settings2,
    Building2
} from 'lucide-vue-next';
import { 
    Dialog, 
    DialogContent, 
    DialogDescription, 
    DialogHeader, 
    DialogTitle, 
    DialogFooter 
} from '@/components/ui/dialog';
import { 
    Table, 
    TableBody, 
    TableCell, 
    TableHead, 
    TableHeader, 
    TableRow 
} from '@/components/ui/table';

interface Props {
    cafes: any[];
    roles: any[];
}

const props = defineProps<Props>();

const searchQuery = ref('');
const roleSearchQuery = ref('');
const isModalOpen = ref(false);
const selectedCafe = ref<any>(null);
const selectedRoleIds = ref<number[]>([]);

const filteredRoles = computed(() => {
    if (!roleSearchQuery.value) return props.roles;
    return props.roles.filter(role => 
        role.name.toLowerCase().includes(roleSearchQuery.value.toLowerCase())
    );
});

const filteredCafes = computed(() => {
    if (!searchQuery.value) return props.cafes;
    return props.cafes.filter(cafe => 
        cafe.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

const openEditModal = (cafe: any) => {
    selectedCafe.value = cafe;
    selectedRoleIds.value = cafe.roles.map((r: any) => r.id);
    roleSearchQuery.value = '';
    isModalOpen.value = true;
};

const toggleRole = (roleId: number) => {
    const index = selectedRoleIds.value.indexOf(roleId);
    if (index === -1) {
        selectedRoleIds.value.push(roleId);
    } else {
        selectedRoleIds.value.splice(index, 1);
    }
};

const handleSave = () => {
    if (!selectedCafe.value) return;

    router.post(route('cafes.roles.sync', selectedCafe.value.id), {
        role_ids: selectedRoleIds.value
    }, {
        onSuccess: () => {
            isModalOpen.value = false;
        },
        preserveScroll: true
    });
};

</script>

<template>
    <Head title="Asignación de Roles por Café" />

    <AppLayout :breadcrumbs="[
        { title: 'Configuración', href: '#' },
        { title: 'Cafés', href: route('cafes.roles.index') },
        { title: 'Roles', href: route('cafes.roles.index') }
    ]">
        <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 flex items-center gap-3">
                        <div class="p-2 bg-amber-100 rounded-2xl">
                            <Coffee class="h-8 w-8 text-amber-600" />
                        </div>
                        Roles por Café
                    </h1>
                    <p class="text-slate-500 mt-2 font-medium">
                        Define qué perfiles de usuario están activos en cada punto de servicio.
                    </p>
                </div>
                
                <div class="relative w-full md:w-80">
                    <Search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                    <Input 
                        v-model="searchQuery"
                        placeholder="Buscar café..." 
                        class="pl-10 h-11 bg-white border-slate-200 shadow-sm rounded-xl focus:ring-amber-500"
                    />
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card class="bg-indigo-600 text-white border-none shadow-xl shadow-indigo-100 overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-10">
                        <Building2 class="h-32 w-32" />
                    </div>
                    <CardContent class="p-6">
                        <p class="text-indigo-100 text-xs font-black uppercase tracking-widest mb-1">Total Cafés</p>
                        <p class="text-4xl font-black">{{ cafes.length }}</p>
                    </CardContent>
                </Card>

                <Card class="bg-amber-500 text-white border-none shadow-xl shadow-amber-100 overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-10">
                        <ShieldCheck class="h-32 w-32" />
                    </div>
                    <CardContent class="p-6">
                        <p class="text-amber-100 text-xs font-black uppercase tracking-widest mb-1">Roles Disponibles</p>
                        <p class="text-4xl font-black">{{ roles.length }}</p>
                    </CardContent>
                </Card>

                <Card class="bg-slate-900 text-white border-none shadow-xl overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-10">
                        <UserCircle class="h-32 w-32" />
                    </div>
                    <CardContent class="p-6">
                        <p class="text-slate-400 text-xs font-black uppercase tracking-widest mb-1">Asignaciones Activas</p>
                        <p class="text-4xl font-black">
                            {{ cafes.reduce((acc, c) => acc + c.roles.length, 0) }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Cafes List -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <Card 
                    v-for="cafe in filteredCafes" 
                    :key="cafe.id"
                    class="group hover:shadow-2xl hover:shadow-indigo-50/50 transition-all duration-300 border-slate-100 rounded-3xl overflow-hidden bg-white/80 backdrop-blur-sm"
                >
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 p-6">
                        <div class="flex items-center gap-4">
                            <div class="h-12 w-12 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-amber-50 group-hover:text-amber-600 transition-colors">
                                <Coffee class="h-6 w-6" />
                            </div>
                            <div>
                                <CardTitle class="text-lg font-black text-slate-900 tabular-nums uppercase tracking-tight flex flex-col">
                                    <span>{{ cafe.name }}</span>
                                    <span class="text-[10px] text-slate-400 font-bold tracking-tighter">
                                        {{ cafe.unit?.mine?.name }} — {{ cafe.unit?.name }}
                                    </span>
                                </CardTitle>
                                <CardDescription class="text-xs font-bold text-slate-400 flex items-center gap-1 mt-1">
                                    <Badge variant="outline" class="text-[10px] py-0 px-1.5 border-slate-200 text-slate-400">ID: {{ cafe.id }}</Badge>
                                </CardDescription>
                            </div>
                        </div>
                        <Button 
                            variant="ghost" 
                            size="icon" 
                            @click="openEditModal(cafe)"
                            class="h-10 w-10 p-0 rounded-2xl text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-all"
                        >
                            <Settings2 class="h-5 w-5" />
                        </Button>
                    </CardHeader>
                    
                    <CardContent class="p-6 pt-0">
                        <div class="flex flex-wrap gap-2">
                            <Badge 
                                v-for="role in cafe.roles" 
                                :key="role.id"
                                class="bg-indigo-50 text-indigo-700 border-indigo-100 px-3 py-1 rounded-full text-xs font-bold"
                            >
                                {{ role.name }}
                            </Badge>
                            <div v-if="cafe.roles.length === 0" class="text-xs text-slate-400 italic">
                                Sin roles asignados aún.
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div v-if="filteredCafes.length === 0" class="col-span-full py-12 text-center bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200">
                    <Coffee class="h-12 w-12 mx-auto text-slate-200 mb-2" />
                    <p class="text-slate-400 font-bold">No se encontraron cafés con ese nombre.</p>
                </div>
            </div>

            <!-- Edit Modal -->
            <Dialog v-model:open="isModalOpen">
                <DialogContent class="sm:max-w-[500px] p-0 overflow-hidden border-none rounded-3xl shadow-2xl">
                    <DialogHeader class="p-8 bg-slate-900 text-white">
                        <DialogTitle class="text-2xl font-black flex items-center gap-3">
                            <Settings2 class="h-6 w-6 text-amber-500" />
                            Gestionar Roles
                        </DialogTitle>
                        <DialogDescription class="text-slate-400 font-medium">
                            Configura qué roles están habilitados para: 
                            <span class="text-white font-black underline decoration-amber-500 underline-offset-4">
                                {{ selectedCafe?.name }} ({{ selectedCafe?.unit?.mine?.name }})
                            </span>
                        </DialogDescription>
                    </DialogHeader>

                    <div class="p-8 pb-0">
                        <div class="relative">
                            <Search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                            <Input 
                                v-model="roleSearchQuery"
                                placeholder="Buscar rol..." 
                                class="pl-10 h-10 bg-slate-50 border-slate-100 rounded-xl focus:ring-amber-500"
                            />
                        </div>
                    </div>

                    <div class="p-8 space-y-4 max-h-[50vh] overflow-y-auto custom-scrollbar">
                        <p class="text-[10px] font-black uppercase text-slate-400 tracking-[0.2em] mb-4">Roles Disponibles en el Sistema</p>
                        
                        <div class="grid grid-cols-1 gap-2">
                            <button 
                                v-for="role in filteredRoles" 
                                :key="role.id"
                                @click="toggleRole(role.id)"
                                class="w-full text-left p-4 rounded-2xl border transition-all duration-200 flex items-center justify-between group"
                                :class="selectedRoleIds.includes(role.id) 
                                    ? 'bg-indigo-50 border-indigo-200 ring-2 ring-indigo-600/10' 
                                    : 'bg-white border-slate-100 hover:border-slate-300'"
                            >
                                <div class="flex items-center gap-4">
                                    <div class="h-10 w-10 rounded-xl flex items-center justify-center transition-colors"
                                        :class="selectedRoleIds.includes(role.id) ? 'bg-indigo-600 text-white' : 'bg-slate-50 text-slate-400 group-hover:bg-slate-100'">
                                        <UserCircle class="h-5 w-5" />
                                    </div>
                                    <span class="font-bold text-sm tracking-tight" :class="selectedRoleIds.includes(role.id) ? 'text-indigo-900' : 'text-slate-600'">
                                        {{ role.name }}
                                    </span>
                                </div>
                                
                                <CheckCircle2 
                                    class="h-5 w-5 transition-all duration-300"
                                    :class="selectedRoleIds.includes(role.id) ? 'text-indigo-600 scale-110 opacity-100' : 'text-slate-100 opacity-0 group-hover:opacity-100'"
                                />
                            </button>
                        </div>
                        
                        <div v-if="filteredRoles.length === 0" class="py-12 text-center bg-slate-50 rounded-2xl border-2 border-dashed border-slate-100">
                            <UserCircle class="h-10 w-10 mx-auto text-slate-200 mb-2" />
                            <p class="text-slate-400 text-xs font-bold">No se encontraron roles con ese nombre.</p>
                        </div>
                    </div>

                    <DialogFooter class="p-8 bg-slate-50 border-t border-slate-100 flex flex-row gap-2 sm:justify-between items-center">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            {{ selectedRoleIds.length }} seleccionados
                        </p>
                        <div class="flex gap-3">
                            <Button variant="ghost" @click="isModalOpen = false" class="rounded-xl font-bold uppercase text-[10px] tracking-widest text-slate-500 hover:bg-slate-100">
                                Cancelar
                            </Button>
                            <Button @click="handleSave" class="rounded-xl bg-slate-900 hover:bg-black text-white px-8 font-black uppercase text-[10px] tracking-widest shadow-lg shadow-slate-200">
                                Guardar Cambios
                            </Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
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
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
