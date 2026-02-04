<script lang="ts" setup>
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import Icon from '@/components/Icon.vue';
import DinnerModal from './DinnerModal.vue';
import ExcelDialog from '../dinners/ExcelDialog.vue';

interface Dinner {
    id: number;
    name: string;
    dni: string;
    phone: string | null;
    subdealership: { id: number; name: string };
    cafe: { id: number; name: string };
    subdealership_id: number;
    cafe_id: number;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedDinners {
    data: Dinner[];
    links: PaginationLinks[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    from: number;
    to: number;
}

interface Cafe {
    id: number;
    name: string;
}

interface Subdealership {
    id: number;
    name: string;
}

const props = defineProps<{
    dinners: PaginatedDinners;
    cafes: Cafe[];
    subdealerships: Subdealership[];
    filters: {
        search?: string;
        cafe_id?: string;
        subdealership_id?: string;
    };
}>();

const breadcrumbs = [
    { title: 'Ventas', href: '/sales' },
    { title: 'Comensales', href: '/sales' },
];

const isModalOpen = ref(false);
const editingDinner = ref<Dinner | null>(null);

// Search and Filters
const search = ref(props.filters.search || '');
const cafeSelect = ref(props.filters.cafe_id || 'all');
const subdealershipSelect = ref(props.filters.subdealership_id || 'all');

const form = useForm({});

// Accessing route globally from Ziggy
declare const route: any;

const openCreateModal = () => {
    editingDinner.value = null;
    isModalOpen.value = true;
};

const openEditModal = (dinner: Dinner) => {
    editingDinner.value = dinner;
    isModalOpen.value = true;
};

const deleteDinner = (id: number) => {
    if (confirm('¿Estás seguro de que deseas eliminar este comensal?')) {
        form.delete(route('dinners.destroy', id), {
            preserveScroll: true
        });
    }
};

let searchTimeout: any = null;
const handleSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('sales.index'), {
            search: search.value,
            cafe_id: cafeSelect.value,
            subdealership_id: subdealershipSelect.value
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true
        });
    }, 400);
};

watch([search, cafeSelect, subdealershipSelect], () => {
    handleSearch();
});

const clearFilters = () => {
    search.value = '';
    cafeSelect.value = 'all';
    subdealershipSelect.value = 'all';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Comensales - Ventas" />

        <div class="p-4 md:p-8 space-y-6 bg-slate-50/50 min-h-screen">
            <!-- Professional Header -->
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                <div class="flex items-center gap-4">
                    <div class="h-14 w-14 rounded-2xl bg-blue-600 flex items-center justify-center text-white shadow-lg shadow-blue-200 rotate-3 transition-transform hover:rotate-0">
                        <Icon name="users" size="28" />
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 uppercase">Gestión de Comensales</h1>
                        <p class="text-slate-500 font-medium text-sm flex items-center gap-2">
                            <span class="flex h-2 w-2 rounded-full bg-blue-500 animate-pulse"></span>
                            Administración del Padrón y Asignaciones
                        </p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full lg:w-auto">
                    <div class="min-w-[200px]">
                        <ExcelDialog />
                    </div>
                    <Button 
                        @click="openCreateModal" 
                        class="h-11 bg-primary hover:bg-primary/90 text-primary-foreground shadow-lg shadow-primary/20 transition-all active:scale-95 px-8 rounded-xl font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-2"
                    >
                        <Icon name="plus" size="18" />
                        Nuevo Comensal
                    </Button>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Card class="border-none shadow-sm bg-white overflow-hidden group">
                    <CardHeader class="pb-2 flex flex-row items-center justify-between space-y-0">
                        <CardTitle class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Total Comensales</CardTitle>
                        <div class="h-10 w-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
                            <Icon name="users" class="h-5 w-5" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold">{{ dinners.total }}</div>
                        <p class="text-xs text-slate-400 mt-1">Registrados en el sistema</p>
                    </CardContent>
                </Card>

                <Card class="border-none shadow-sm bg-white overflow-hidden group">
                    <CardHeader class="pb-2 flex flex-row items-center justify-between space-y-0">
                        <CardTitle class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Cafeterías</CardTitle>
                        <div class="h-10 w-10 rounded-xl bg-orange-100 flex items-center justify-center text-orange-600 transition-colors group-hover:bg-orange-500 group-hover:text-white">
                            <Icon name="coffee" class="h-5 w-5" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold">{{ cafes.length }}</div>
                        <p class="text-xs text-slate-400 mt-1">Puntos de servicio activos</p>
                    </CardContent>
                </Card>

                <Card class="border-none shadow-sm bg-white overflow-hidden group">
                    <CardHeader class="pb-2 flex flex-row items-center justify-between space-y-0">
                        <CardTitle class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Subconcesionarias</CardTitle>
                        <div class="h-10 w-10 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 transition-colors group-hover:bg-blue-500 group-hover:text-white">
                            <Icon name="building" class="h-5 w-5" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold">{{ subdealerships.length }}</div>
                        <p class="text-xs text-slate-400 mt-1">Empresas vinculadas</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters Section -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/60 transition-all hover:shadow-md">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="relative">
                        <Icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                        <Input 
                            v-model="search" 
                            placeholder="Buscar por nombre, DNI..." 
                            class="pl-10 h-11 border-slate-200 focus:ring-primary/20 focus:border-primary transition-all rounded-xl"
                        />
                    </div>
                    
                    <Select v-model="cafeSelect">
                        <SelectTrigger class="h-11 border-slate-200 rounded-xl">
                            <div class="flex items-center gap-2">
                                <Icon name="coffee" class="h-4 w-4 text-slate-400" />
                                <SelectValue placeholder="Todas las cafeterías" />
                            </div>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todas las cafeterías</SelectItem>
                            <SelectItem v-for="cafe in cafes" :key="cafe.id" :value="cafe.id.toString()">
                                {{ cafe.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- <Select v-model="subdealershipSelect">
                        <SelectTrigger class="h-11 border-slate-200 rounded-xl">
                            <div class="flex items-center gap-2">
                                <Icon name="building-2" class="h-4 w-4 text-slate-400" />
                                <SelectValue placeholder="Todas las subconcesionarias" />
                            </div>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todas las subconcesionarias</SelectItem>
                            <SelectItem v-for="sub in subdealerships" :key="sub.id" :value="sub.id.toString()">
                                {{ sub.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select> -->

                    <Button variant="outline" @click="clearFilters" class="h-11 rounded-xl text-slate-500 hover:text-slate-900 border-slate-200">
                        <Icon name="x" class="mr-2 h-4 w-4" />
                        Limpiar Filtros
                    </Button>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/60 overflow-hidden">
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow class="bg-slate-50/80 hover:bg-slate-50/80 border-b-slate-200">
                                <TableHead class="w-[320px] py-4 text-slate-600 font-bold uppercase tracking-wider text-[11px]">Comensal</TableHead>
                                <TableHead class="py-4 text-slate-600 font-bold uppercase tracking-wider text-[11px]">Identificación</TableHead>
                                <TableHead class="py-4 text-slate-600 font-bold uppercase tracking-wider text-[11px]">Contacto</TableHead>
                                <TableHead class="py-4 text-slate-600 font-bold uppercase tracking-wider text-[11px]">Asignación</TableHead>
                                <TableHead class="text-right py-4 text-slate-600 font-bold uppercase tracking-wider text-[11px]">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="dinner in dinners.data" :key="dinner.id" class="hover:bg-slate-50/50 transition-all border-b-slate-100 last:border-0 group">
                                <TableCell class="py-4">
                                    <div class="flex items-center gap-4">
                                        <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 border border-primary/20 flex items-center justify-center text-primary font-bold text-base shadow-sm group-hover:scale-110 transition-transform">
                                            {{ dinner.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-slate-900 leading-none mb-1">{{ dinner.name }}</span>
                                            <span class="text-[11px] text-slate-400 font-medium uppercase tracking-tight">ID Sistema: #{{ dinner.id }}</span>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="py-4">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-[13px] font-semibold text-slate-700">DNI {{ dinner.dni }}</span>
                                        <span v-if="dinner.phone" class="text-[11px] text-slate-400 flex items-center gap-1">
                                            <Icon name="phone" size="10" /> {{ dinner.phone }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell class="py-4">
                                    <div class="flex flex-col gap-2">
                                        <Badge variant="secondary" class="font-bold text-[10px] w-fit rounded-lg px-2 py-0.5 bg-slate-100 text-slate-600 border-none">
                                            <Icon name="building-2" size="10" class="mr-1" />
                                            {{ dinner.subdealership?.name }}
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell class="py-4">
                                    <Badge variant="outline" class="font-bold text-[10px] w-fit rounded-lg px-2 py-0.5 border-primary/20 bg-primary/5 text-primary">
                                        <Icon name="coffee" size="10" class="mr-1" />
                                        {{ dinner.cafe?.name }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right py-4 px-6">
                                    <div class="flex justify-end gap-2">
                                        <Button variant="ghost" size="icon" @click="openEditModal(dinner)" title="Editar" class="h-9 w-9 rounded-xl hover:bg-primary/10 hover:text-primary transition-colors">
                                            <Icon name="pencil" class="h-4.5 w-4.5" />
                                        </Button>
                                        <Button variant="ghost" size="icon" class="h-9 w-9 rounded-xl text-slate-400 hover:text-destructive hover:bg-destructive/10 transition-colors" @click="deleteDinner(dinner.id)" title="Eliminar">
                                            <Icon name="trash" class="h-4.5 w-4.5" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="dinners.data.length === 0">
                                <TableCell colspan="5" class="h-[400px] text-center">
                                    <div class="flex flex-col items-center justify-center gap-4 max-w-sm mx-auto">
                                        <div class="h-24 w-24 rounded-full bg-slate-50 flex items-center justify-center text-slate-200">
                                            <Icon name="users" size="48" strokeWidth="1.5" />
                                        </div>
                                        <div class="space-y-1">
                                            <p class="text-lg font-bold text-slate-900">No se encontraron resultados</p>
                                            <p class="text-sm text-slate-500">No hay comensales que coincidan con los criterios de búsqueda actuales.</p>
                                        </div>
                                        <Button variant="outline" @click="clearFilters" class="mt-2">Ver todos los comensales</Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Custom Pagination -->
                <div v-if="dinners.total > 0" class="flex items-center justify-between px-6 py-4 border-t border-slate-100 bg-slate-50/30">
                    <div class="text-xs font-semibold text-slate-500">
                        Mostrando <span class="text-slate-900 font-bold">{{ dinners.from }} - {{ dinners.to }}</span> de <span class="text-slate-900 font-bold">{{ dinners.total }}</span> comensales
                    </div>
                    <div class="flex items-center gap-1.5">
                        <template v-for="(link, k) in dinners.links" :key="k">
                            <div v-if="link.url === null" class="h-9 min-w-[36px] px-3 flex items-center justify-center text-xs font-bold text-slate-300 rounded-xl pointer-events-none" v-html="link.label" />
                            <Link 
                                v-else 
                                :href="link.url" 
                                class="h-9 min-w-[36px] px-3 flex items-center justify-center text-xs font-bold rounded-xl transition-all border shadow-sm" 
                                :class="{ 
                                    'bg-primary text-primary-foreground border-primary shadow-primary/20 scale-105': link.active, 
                                    'bg-white hover:bg-slate-50 border-slate-200 text-slate-600': !link.active 
                                }" 
                                v-html="link.label" 
                                preserve-scroll 
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <DinnerModal 
            v-model:open="isModalOpen"
            :dinner="editingDinner"
            :cafes="cafes"
            :subdealerships="subdealerships"
        />
    </AppLayout>
</template>

<style scoped>
/* Transiciones suaves */
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 200ms;
}

/* Scrollbar personalizado si fuera necesario */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
