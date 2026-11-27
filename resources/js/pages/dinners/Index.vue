<script lang="ts" setup>
import EmptyState from '@/components/shared/EmptyState.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatCard from '@/components/shared/StatCard.vue';
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Building2, Mountain, Plus, Search, Users, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import ExcelDialog from '../sales/ExcelDialog.vue';
import DinnerModal from './DinnerModal.vue';

interface Dinner {
    id: number;
    name: string;
    dni: string;
    phone: string | null;
    subdealership: { id: number; name: string } | null;
    subdealership_id: number;
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

interface Subdealership {
    id: number;
    name: string;
}

interface Mine {
    id: number;
    name: string;
}

interface Cafe {
    id: number;
    name: string;
    unit?: {
        id: number;
        name: string;
    };
}

const props = defineProps<{
    dinners: PaginatedDinners;
    subdealerships: Subdealership[];
    mines?: Mine[];
    cafes?: Cafe[];
    filters: {
        search?: string;
        subdealership_id?: string;
        mine_id?: string;
    };
    mine: Subdealership | null;
}>();

const breadcrumbs = [
    { title: 'Ventas', href: '/sales' },
    { title: 'Comensales', href: '/sales' },
];

const isModalOpen = ref(false);
const editingDinner = ref<Dinner | null>(null);

const search = ref(props.filters.search || '');
const selectedSubdealership = ref(props.filters.subdealership_id || 'all');
const selectedMine = ref(props.filters.mine_id || 'all');

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
            preserveScroll: true,
        });
    }
};

const applyFilters = () => {
    const params: Record<string, string> = {};
    if (search.value.trim()) params.search = search.value.trim();
    if (selectedSubdealership.value && selectedSubdealership.value !== 'all') params.subdealership_id = selectedSubdealership.value;
    if (selectedMine.value && selectedMine.value !== 'all') params.mine_id = selectedMine.value;

    router.get(route('dinners.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

let searchTimeout: any = null;
watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});

watch([selectedSubdealership, selectedMine], () => {
    applyFilters();
});

const clearFilters = () => {
    search.value = '';
    selectedSubdealership.value = 'all';
    selectedMine.value = 'all';
    applyFilters();
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Comensales - Ventas" />

        <div class="space-y-6 p-4 sm:p-6 lg:p-8">
            <!-- Encabezado Unificado -->
            <PageHeader
                title="Gestión de Comensales"
                description="Administración del padrón de usuarios, subconcesionarias y asignaciones mineras."
                :icon="Users"
                iconColor="from-red-600 to-rose-700 text-white"
            >
                <template #actions>
                    <div class="min-w-[200px]">
                        <ExcelDialog :subdealerships="subdealerships" :cafes="cafes" />
                    </div>
                    <Button
                        @click="openCreateModal"
                        class="h-10 gap-2 rounded-xl bg-red-600 hover:bg-red-700 text-white shadow-sm shadow-red-600/20 px-4 text-xs font-semibold"
                    >
                        <Plus class="h-4 w-4" />
                        Nuevo Comensal
                    </Button>
                </template>
            </PageHeader>

            <!-- Métricas Rápidas -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    label="Padrón Total de Comensales"
                    :value="dinners.total"
                    subtext="Registros en base de datos"
                    :icon="Users"
                    variant="primary"
                />
                <StatCard
                    label="Subconcesionarias"
                    :value="subdealerships.length"
                    subtext="Empresas y concesionarias"
                    :icon="Building2"
                    variant="info"
                />
                <StatCard
                    label="Minas Vinculadas"
                    :value="mines ? mines.length : 0"
                    subtext="Unidades operativas"
                    :icon="Mountain"
                    variant="success"
                />
            </div>

            <!-- Sección de Filtros Unificada -->
            <div class="rounded-2xl border border-border/70 bg-card p-4 shadow-2xs">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="relative">
                        <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="search"
                            placeholder="Buscar por nombre, DNI..."
                            class="h-10 rounded-xl border-border bg-muted/30 pl-9 text-sm transition-all focus:bg-background focus:ring-2 focus:ring-red-600/20"
                        />
                    </div>

                    <div>
                        <Select v-model="selectedSubdealership">
                            <SelectTrigger class="h-10 rounded-xl border-border bg-card text-xs">
                                <SelectValue placeholder="Todas las Subconcesionarias" />
                            </SelectTrigger>
                            <SelectContent class="rounded-xl shadow-lg">
                                <SelectItem value="all">Todas las Subconcesionarias</SelectItem>
                                <SelectItem v-for="sd in subdealerships" :key="sd.id" :value="sd.id.toString()">
                                    {{ sd.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div>
                        <Select v-model="selectedMine">
                            <SelectTrigger class="h-10 rounded-xl border-border bg-card text-xs">
                                <SelectValue placeholder="Todas las Minas" />
                            </SelectTrigger>
                            <SelectContent class="rounded-xl shadow-lg">
                                <SelectItem value="all">Todas las Minas</SelectItem>
                                <SelectItem v-for="m in mines" :key="m.id" :value="m.id.toString()">
                                    {{ m.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <Button variant="outline" @click="clearFilters" class="h-10 rounded-xl border-border text-xs text-muted-foreground hover:text-foreground">
                        <X class="mr-1.5 h-3.5 w-3.5" />
                        Limpiar Filtros
                    </Button>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="overflow-hidden rounded-2xl border border-slate-200/60 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow class="border-b-slate-200 bg-slate-50/80 hover:bg-slate-50/80">
                                <TableHead class="w-[320px] py-4 text-[11px] font-bold tracking-wider text-slate-600 uppercase">Comensal</TableHead>
                                <TableHead class="py-4 text-[11px] font-bold tracking-wider text-slate-600 uppercase">Identificación</TableHead>
                                <TableHead class="py-4 text-[11px] font-bold tracking-wider text-slate-600 uppercase">Subconcecionaria</TableHead>
                                <TableHead class="py-4 text-[11px] font-bold tracking-wider text-slate-600 uppercase">Mina</TableHead>
                                <TableHead class="py-4 text-right text-[11px] font-bold tracking-wider text-slate-600 uppercase">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="dinner in dinners.data"
                                :key="dinner.id"
                                class="group border-b-slate-100 transition-all last:border-0 hover:bg-slate-50/50"
                            >
                                <TableCell class="py-4">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="from-primary/10 to-primary/5 border-primary/20 text-primary flex h-11 w-11 items-center justify-center rounded-2xl border bg-gradient-to-br text-base font-bold shadow-sm transition-transform group-hover:scale-110"
                                        >
                                            {{ dinner.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="mb-1 text-sm leading-none font-bold text-slate-900">{{ dinner.name }}</span>
                                            <span class="text-[11px] font-medium tracking-tight text-slate-400 uppercase"
                                                >ID Sistema: #{{ dinner.id }}</span
                                            >
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="py-4">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-[13px] font-semibold text-slate-700">Doc. {{ dinner.dni }}</span>
                                        <span v-if="dinner.phone" class="flex items-center gap-1 text-[11px] text-slate-400">
                                            <Icon name="phone" size="10" /> {{ dinner.phone }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell class="py-4">
                                    <div class="flex flex-col gap-2">
                                        <Badge
                                            variant="secondary"
                                            class="w-fit rounded-lg border-none bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600"
                                        >
                                            <Icon name="building-2" size="10" class="mr-1" />
                                            {{ dinner.subdealership?.name }}
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell class="py-4">
                                    <div class="flex flex-col gap-2">
                                        <Badge
                                            variant="secondary"
                                            class="w-fit rounded-lg border-none bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600"
                                        >
                                            <Icon name="building-2" size="10" class="mr-1" />
                                            {{ dinner.mine?.name }}
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="openEditModal(dinner)"
                                            title="Editar"
                                            class="hover:bg-primary/10 hover:text-primary h-9 w-9 rounded-xl transition-colors"
                                        >
                                            <Icon name="pencil" class="h-4.5 w-4.5" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="hover:text-destructive hover:bg-destructive/10 h-9 w-9 rounded-xl text-slate-400 transition-colors"
                                            @click="deleteDinner(dinner.id)"
                                            title="Eliminar"
                                        >
                                            <Icon name="trash" class="h-4.5 w-4.5" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="dinners.data.length === 0">
                                <TableCell colspan="5" class="p-0">
                                    <EmptyState
                                        title="No se encontraron comensales"
                                        description="No hay comensales que coincidan con los criterios de búsqueda o filtros seleccionados."
                                        :icon="Users"
                                    >
                                        <Button variant="outline" size="sm" @click="clearFilters">Ver todos los comensales</Button>
                                    </EmptyState>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Custom Pagination -->
                <div v-if="dinners.total > 0" class="flex items-center justify-between border-t border-slate-100 bg-slate-50/30 px-6 py-4">
                    <div class="text-xs font-semibold text-slate-500">
                        Mostrando <span class="font-bold text-slate-900">{{ dinners.from }} - {{ dinners.to }}</span> de
                        <span class="font-bold text-slate-900">{{ dinners.total }}</span> comensales
                    </div>
                    <div class="flex items-center gap-1.5">
                        <template v-for="(link, k) in dinners.links" :key="k">
                            <div
                                v-if="link.url === null"
                                class="pointer-events-none flex h-9 min-w-[36px] items-center justify-center rounded-xl px-3 text-xs font-bold text-slate-300"
                                v-html="link.label"
                            />
                            <Link
                                v-else
                                :href="link.url"
                                class="flex h-9 min-w-[36px] items-center justify-center rounded-xl border px-3 text-xs font-bold shadow-sm transition-all"
                                :class="{
                                    'bg-primary text-primary-foreground border-primary shadow-primary/20 scale-105': link.active,
                                    'border-slate-200 bg-white text-slate-600 hover:bg-slate-50': !link.active,
                                }"
                                v-html="link.label"
                                preserve-scroll
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <DinnerModal v-model:open="isModalOpen" :dinner="editingDinner" :subdealerships="subdealerships" />
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
