<script lang="ts" setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
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
import Icon from '@/components/Icon.vue';
import DinnerModal from './DinnerModal.vue';

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
}>();

const breadcrumbs = [
    { title: 'Ventas', href: '/sales' },
    { title: 'Comensales', href: '/sales' },
];

const isModalOpen = ref(false);
const editingDinner = ref<Dinner | null>(null);

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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Comensales - Ventas" />

        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Gestión de Comensales</h1>
                    <p class="text-muted-foreground text-sm">Administra los comensales, sus subconcesionarias y cafeterías asignadas.</p>
                </div>
                <Button @click="openCreateModal">
                    <Icon name="plus" class="mr-2 h-4 w-4" />
                    Nuevo Comensal
                </Button>
            </div>

            <div class="border bg-card overflow-hidden rounded-xl shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow class="bg-muted/30">
                            <TableHead class="w-[280px]">Nombre</TableHead>
                            <TableHead>DNI</TableHead>
                            <TableHead>Teléfono</TableHead>
                            <TableHead>Subconcesionaria</TableHead>
                            <TableHead>Cafetería</TableHead>
                            <TableHead class="text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="dinner in dinners.data" :key="dinner.id" class="hover:bg-muted/50 transition-colors">
                            <TableCell class="font-medium">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center text-primary font-semibold text-sm">
                                        {{ dinner.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-semibold">{{ dinner.name }}</span>
                                        <span class="text-[10px] text-muted-foreground uppercase tracking-wider font-medium">ID: {{ dinner.id }}</span>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">{{ dinner.dni }}</TableCell>
                            <TableCell class="text-sm text-muted-foreground">{{ dinner.phone || '-' }}</TableCell>
                            <TableCell>
                                <Badge variant="secondary" class="font-normal px-2 py-0 h-5">
                                    {{ dinner.subdealership?.name }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline" class="font-normal border-primary/20 bg-primary/5 text-primary px-2 py-0 h-5">
                                    {{ dinner.cafe?.name }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" @click="openEditModal(dinner)" title="Editar" class="h-8 w-8">
                                        <Icon name="pencil" class="h-4 w-4 text-muted-foreground" />
                                    </Button>
                                    <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10" @click="deleteDinner(dinner.id)" title="Eliminar">
                                        <Icon name="trash" class="h-4 w-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="dinners.data.length === 0">
                            <TableCell colspan="6" class="h-32 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <Icon name="users" class="h-8 w-8 opacity-20" />
                                    <p class="text-sm">No se encontraron comensales registrados.</p>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Pagination Footer -->
                <div v-if="dinners.total > dinners.per_page" class="flex items-center justify-between px-6 py-4 border-t bg-muted/20">
                    <div class="text-sm text-muted-foreground text-xs">
                        Mostrando <span class="font-medium">{{ dinners.from }}</span> a <span class="font-medium">{{ dinners.to }}</span> de <span class="font-medium">{{ dinners.total }}</span> resultados
                    </div>
                    <div class="flex items-center gap-1">
                        <template v-for="(link, k) in dinners.links" :key="k">
                            <div v-if="link.url === null" class="h-8 min-w-[32px] px-2 flex items-center justify-center text-xs text-muted-foreground/50 rounded-md border border-transparent" v-html="link.label" />
                            <Link v-else :href="link.url" class="h-8 min-w-[32px] px-2 flex items-center justify-center text-xs rounded-md border transition-all" :class="{ 'bg-primary text-primary-foreground border-primary shadow-sm font-bold': link.active, 'bg-background hover:bg-muted border-muted-foreground/20 text-muted-foreground': !link.active }" v-html="link.label" preserve-scroll />
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
