<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { Coffee, LayoutGrid, LayoutList, Moon, Package, Pencil, ServerOff, Sunrise, Trash2, Utensils } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import ServiceModal from './ServiceModal.vue';
import ServiceSyncModal from './ServiceSyncModal.vue';

// Definición de tipos
interface Service {
    id: number;
    code: string;
    name: string;
    description: string;
    type: string;
    created_at: string;
    updated_at: string;
}

// Estado del componente
const services = ref<Service[]>([]);
const showModal = ref(false);
const editingService = ref<Service | null>(null);
const viewMode = ref<'table' | 'cards'>('table');

// Formulario para crear/editar servicios
const form = useForm({
    code: '',
    name: '',
    description: '',
    type: '',
});

// Tipos de servicio disponibles
const serviceTypes = [
    { value: '1', label: 'Desayuno' },
    { value: '2', label: 'Almuerzo' },
    { value: '3', label: 'Cena' },
    { value: '4', label: 'Refrigerio' },
    { value: '5', label: 'Descartables' },
];

const typeConfig: Record<string, {
    label: string; icon: unknown; classes: string;
    gradient: string; emoji: string; emoji2: string;
}> = {
    '1': {
        label: 'Desayuno',     icon: Sunrise,  classes: 'bg-amber-100  text-amber-700  dark:bg-amber-900/30  dark:text-amber-400',
        gradient: 'from-amber-400 via-orange-400 to-amber-500', emoji: '🍳', emoji2: '☕',
    },
    '2': {
        label: 'Almuerzo',     icon: Utensils, classes: 'bg-green-100  text-green-700  dark:bg-green-900/30  dark:text-green-400',
        gradient: 'from-emerald-400 via-green-400 to-teal-500',  emoji: '🍽️', emoji2: '🥗',
    },
    '3': {
        label: 'Cena',         icon: Moon,     classes: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
        gradient: 'from-indigo-500 via-violet-500 to-purple-600', emoji: '🍷', emoji2: '🌙',
    },
    '4': {
        label: 'Refrigerio',   icon: Coffee,   classes: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        gradient: 'from-yellow-300 via-amber-400 to-orange-400',  emoji: '☕', emoji2: '🥐',
    },
    '5': {
        label: 'Descartables', icon: Package,  classes: 'bg-gray-100   text-gray-600   dark:bg-gray-700      dark:text-gray-300',
        gradient: 'from-slate-400 via-gray-500 to-zinc-600',      emoji: '📦', emoji2: '🥡',
    },
};

// Cargar servicios al montar el componente
onMounted(() => {
    fetchServices();
});

// Obtener todos los servicios
const fetchServices = async () => {
    try {
        const response = await axios.get('services/list');
        services.value = response.data;
    } catch (error) {
        console.error('Error fetching services:', error);
    }
};

// Abrir modal para crear/editar servicio
const openServiceModal = (service: Service | null = null) => {
    editingService.value = service;

    if (service) {
        form.code = service.code;
        form.name = service.name;
        form.description = service.description;
        form.type = service.type;
    } else {
        form.reset();
    }

    showModal.value = true;
};

// Guardar servicio (crear o actualizar)
const saveService = async () => {
    try {
        if (editingService.value) {
            await axios.put(`/api/services/${editingService.value.id}`, form);
        } else {
            await axios.post('/api/services', form);
        }

        showModal.value = false;
        form.reset();
        await fetchServices();
    } catch (error) {
        console.error('Error saving service:', error);
    }
};

// Confirmar eliminación de servicio
const confirmDelete = (id: number) => {
    if (confirm('¿Estás seguro de que deseas eliminar este servicio?')) {
        axios
            .delete('/services/' + id)
            .then((result) => {
                fetchServices();
            })
            .catch((err) => {
                console.error(err);
            });
    }
};
</script>

<template>
    <Head title="Servicios" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="md:col-span-3">
                    <div class="flex items-center justify-between">
                        <h1 class="text-2xl font-semibold tracking-tight">Servicios</h1>
                        <div class="flex items-center gap-2">
                            <!-- Toggle vista -->
                            <div class="bg-muted flex items-center rounded-lg p-1">
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-7 w-7 transition-all"
                                                :class="viewMode === 'table' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                                                @click="viewMode = 'table'"
                                            >
                                                <LayoutList class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>Vista tabla</TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-7 w-7 transition-all"
                                                :class="viewMode === 'cards' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                                                @click="viewMode = 'cards'"
                                            >
                                                <LayoutGrid class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>Vista cards</TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                            <ServiceSyncModal />
                            <ServiceModal :serviceTypes="serviceTypes" @fetchServices="fetchServices" />
                        </div>
                    </div>
                </div>

                <!-- Vista tabla -->
                <div v-if="viewMode === 'table'" class="md:col-span-3">
                    <div class="bg-card rounded-xl border shadow-sm">
                        <table class="w-full table-auto border-collapse overflow-x-auto">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-sm font-semibold">Código</th>
                                    <th class="px-5 py-3 text-left text-sm font-semibold">Nombre</th>
                                    <th class="px-5 py-3 text-left text-sm font-semibold">Descripción</th>
                                    <th class="px-5 py-3 text-left text-sm font-semibold">Tipo</th>
                                    <th class="px-5 py-3 text-center text-sm font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="service in services"
                                    :key="service.id"
                                    class="hover:bg-muted/30 border-t transition"
                                >
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <span class="bg-muted rounded px-2 py-0.5 font-mono text-xs font-medium">
                                            {{ service.code }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-sm font-medium">
                                        {{ service.name }}
                                    </td>
                                    <td class="text-muted-foreground max-w-xs truncate px-5 py-3 text-sm">
                                        {{ service.description }}
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="typeConfig[service.type]?.classes ?? 'bg-gray-100 text-gray-600'"
                                        >
                                            <component :is="typeConfig[service.type]?.icon" class="h-3.5 w-3.5" />
                                            {{ typeConfig[service.type]?.label ?? service.type }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-1">
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <Button variant="ghost" size="icon" class="h-8 w-8 text-blue-600 hover:bg-blue-50 hover:text-blue-700" @click="openServiceModal(service)">
                                                            <Pencil class="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>Editar</TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <Button variant="ghost" size="icon" class="h-8 w-8 text-red-500 hover:bg-red-50 hover:text-red-600" @click="confirmDelete(service.id)">
                                                            <Trash2 class="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>Eliminar</TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="services.length === 0">
                                    <td colspan="5" class="py-16 text-center">
                                        <div class="text-muted-foreground flex flex-col items-center gap-2">
                                            <ServerOff class="h-10 w-10 opacity-40" />
                                            <span class="text-sm">No hay servicios registrados</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="services.length > 0" class="text-muted-foreground border-t px-5 py-3 text-xs">
                            {{ services.length }} {{ services.length === 1 ? 'servicio registrado' : 'servicios registrados' }}
                        </div>
                    </div>
                </div>

                <!-- Vista cards -->
                <div v-else class="md:col-span-3">
                    <div v-if="services.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <Card
                            v-for="service in services"
                            :key="service.id"
                            class="border-sidebar-border/50 dark:border-sidebar-border/70 group overflow-hidden rounded-2xl border shadow-sm transition hover:shadow-lg"
                        >
                            <!-- ── Foto / Banner ────────────────────────── -->
                            <div
                                class="relative flex h-36 items-center justify-center overflow-hidden bg-gradient-to-br"
                                :class="typeConfig[service.type]?.gradient ?? 'from-gray-400 to-slate-500'"
                            >
                                <!-- Círculos decorativos de fondo -->
                                <div class="absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/10" />
                                <div class="absolute -bottom-4 -left-4 h-20 w-20 rounded-full bg-black/10" />
                                <div class="absolute right-6 bottom-4 h-10 w-10 rounded-full bg-white/15" />

                                <!-- Emoji secundario — decorativo -->
                                <span
                                    class="absolute top-3 right-4 text-3xl opacity-50 transition-all duration-500 group-hover:opacity-70 group-hover:rotate-6 select-none"
                                >
                                    {{ typeConfig[service.type]?.emoji2 ?? '🍴' }}
                                </span>

                                <!-- Emoji principal -->
                                <span
                                    class="relative z-10 text-6xl drop-shadow transition-transform duration-500 group-hover:scale-110 select-none"
                                >
                                    {{ typeConfig[service.type]?.emoji ?? '🍽️' }}
                                </span>
                            </div>

                            <!-- ── Contenido ────────────────────────────── -->
                            <div class="flex flex-col gap-2.5 p-4">
                                <!-- Badges -->
                                <div class="flex items-center justify-between gap-2">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="typeConfig[service.type]?.classes ?? 'bg-gray-100 text-gray-600'"
                                    >
                                        <component :is="typeConfig[service.type]?.icon" class="h-3.5 w-3.5" />
                                        {{ typeConfig[service.type]?.label ?? service.type }}
                                    </span>
                                    <span class="bg-muted rounded px-2 py-0.5 font-mono text-xs font-medium">
                                        {{ service.code }}
                                    </span>
                                </div>

                                <!-- Nombre -->
                                <h3 class="text-foreground text-base font-semibold leading-tight">
                                    {{ service.name }}
                                </h3>

                                <!-- Descripción -->
                                <p class="text-muted-foreground line-clamp-2 min-h-[2.5rem] text-sm">
                                    {{ service.description || '—' }}
                                </p>

                                <!-- Acciones -->
                                <div class="flex items-center justify-end gap-1 border-t pt-2">
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button variant="ghost" size="icon" class="h-8 w-8 text-blue-600 hover:bg-blue-50 hover:text-blue-700" @click="openServiceModal(service)">
                                                    <Pencil class="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Editar</TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button variant="ghost" size="icon" class="h-8 w-8 text-red-500 hover:bg-red-50 hover:text-red-600" @click="confirmDelete(service.id)">
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Eliminar</TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                            </div>
                        </Card>
                    </div>

                    <!-- Empty state cards -->
                    <div v-else class="bg-card flex flex-col items-center justify-center gap-2 rounded-xl border py-16 shadow-sm">
                        <ServerOff class="text-muted-foreground h-10 w-10 opacity-40" />
                        <span class="text-muted-foreground text-sm">No hay servicios registrados</span>
                    </div>

                    <div v-if="services.length > 0" class="text-muted-foreground mt-2 text-xs">
                        {{ services.length }} {{ services.length === 1 ? 'servicio registrado' : 'servicios registrados' }}
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
