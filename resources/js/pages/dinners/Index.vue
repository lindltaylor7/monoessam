<script lang="ts" setup>
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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

const props = defineProps<{
    dinners: PaginatedDinners;
    subdealerships: Subdealership[];
    filters: {
        search?: string;
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

let searchTimeout: any = null;
const handleSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('dinners.index'), { search: search.value }, { preserveState: true, preserveScroll: true, replace: true });
    }, 400);
};

watch(search, () => {
    handleSearch();
});

const clearFilters = () => {
    search.value = '';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Comensales - Ventas" />

        <div class="min-h-screen space-y-6 bg-slate-50/50 p-4 md:p-8">
            <!-- Professional Header -->
            <div class="flex flex-col items-start justify-between gap-6 lg:flex-row lg:items-center">
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-14 w-14 rotate-3 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-lg shadow-blue-200 transition-transform hover:rotate-0"
                    >
                        <Icon name="users" size="28" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 uppercase md:text-3xl">Gestión de Comensales</h1>
                        <p class="flex items-center gap-2 text-sm font-medium text-slate-500">
                            <span class="flex h-2 w-2 animate-pulse rounded-full bg-blue-500"></span>
                            Administración del Padrón y Asignaciones
                        </p>
                    </div>
                </div>

                <div class="flex w-full flex-col items-stretch gap-3 sm:flex-row sm:items-center lg:w-auto">
                    <div class="min-w-[200px]">
                        <ExcelDialog />
                    </div>
                    <Button
                        @click="openCreateModal"
                        class="bg-primary hover:bg-primary/90 text-primary-foreground shadow-primary/20 flex h-11 items-center justify-center gap-2 rounded-xl px-8 text-xs font-bold tracking-wider uppercase shadow-lg transition-all active:scale-95"
                    >
                        <Icon name="plus" size="18" />
                        Nuevo Comensal
                    </Button>
                </div>
            </div>

            <!-- Stats Overview -->

            <!-- Filters Section -->
            <div class="rounded-2xl border border-slate-200/60 bg-white p-4 shadow-sm transition-all hover:shadow-md">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div class="relative">
                        <Icon name="search" class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <Input
                            v-model="search"
                            placeholder="Buscar por nombre, DNI..."
                            class="focus:ring-primary/20 focus:border-primary h-11 rounded-xl border-slate-200 pl-10 transition-all"
                        />
                    </div>

                    <Button variant="outline" @click="clearFilters" class="h-11 rounded-xl border-slate-200 text-slate-500 hover:text-slate-900">
                        <Icon name="x" class="mr-2 h-4 w-4" />
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
                                        <span class="text-[13px] font-semibold text-slate-700">DNI {{ dinner.dni }}</span>
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
                                <TableCell colspan="4" class="h-[400px] text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center justify-center gap-4">
                                        <div class="flex h-24 w-24 items-center justify-center rounded-full bg-slate-50 text-slate-200">
                                            <Icon name="users" size="48" strokeWidth="1.5" />
                                        </div>
                                        <div class="space-y-1">
                                            <p class="text-lg font-bold text-slate-900">No se encontraron resultados</p>
                                            <p class="text-sm text-slate-500">
                                                No hay comensales que coincidan con los criterios de búsqueda actuales.
                                            </p>
                                        </div>
                                        <Button variant="outline" @click="clearFilters" class="mt-2">Ver todos los comensales</Button>
                                    </div>
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
