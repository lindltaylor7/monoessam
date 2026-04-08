<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Input from '@/components/ui/input/Input.vue';
import { Button } from '@/components/ui/button';
import { Search } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Staff } from '@/types';
import FilesModal from '@/pages/staff/FilesModal.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { getStatusColor, getStatusLabel } from '@/composables/useStaffConstants';

interface Props {
    staff?: Staff[];
}

const props = withDefaults(defineProps<Props>(), {
    staff: () => [],
});

const searchQuery = ref('');
const startDateFilter = ref('');
const endDateFilter = ref('');

const clearDateFilter = () => {
    startDateFilter.value = '';
    endDateFilter.value = '';
};

// Helper to format date
const formatDate = (dateString?: string) => {
    if (!dateString) return 'Sin fecha';
    const date = new Date(dateString);
    // Ajustar por zona horaria de ser necesario, usamos UTC para evitar desfases
    return date.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
};

const getStartDate = (staff: any) => {
    return staff.startDate || staff.start_date || staff.created_at;
};

const filteredStaff = computed(() => {
    let filtered = props.staff || [];
    
    // Filtro por nombre o DNI
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(s => 
            s.name?.toLowerCase().includes(query) || 
            s.dni?.includes(query)
        );
    }
    
    // Filtro por fecha de ingreso (Rango)
    if (startDateFilter.value || endDateFilter.value) {
        filtered = filtered.filter(s => {
            const sDate = getStartDate(s);
            if (!sDate) return false;
            
            // Format to YYYY-MM-DD
            try {
                const dateObj = new Date(sDate);
                const isoDate = dateObj.toISOString().split('T')[0];
                
                let matchesStart = true;
                let matchesEnd = true;
                
                if (startDateFilter.value) {
                    matchesStart = isoDate >= startDateFilter.value;
                }
                if (endDateFilter.value) {
                    matchesEnd = isoDate <= endDateFilter.value;
                }
                
                return matchesStart && matchesEnd;
            } catch (e) {
                return false;
            }
        });
    }
    
    return filtered;
});

</script>

<template>
    <Head title="Laboral - Personal" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Expedientes Laborales</h1>
                    <p class="text-sm text-muted-foreground mt-1">Gestión de expedientes digitales del personal.</p>
                </div>
            </div>

            <!-- Filtros -->
            <div class="flex items-center flex-wrap gap-4 bg-card p-4 rounded-xl border shadow-sm">
                <div class="relative w-full max-w-sm">
                    <Search class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                    <Input 
                        type="text" 
                        placeholder="Buscar por nombre o DNI..." 
                        v-model="searchQuery"
                        class="pl-9 bg-muted/50 border-zinc-200"
                    />
                </div>
                
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-muted-foreground whitespace-nowrap">Fecha de ingreso:</span>
                    <div class="flex flex-col sm:flex-row items-center bg-white border border-zinc-200 rounded-lg shadow-sm overflow-hidden focus-within:ring-2 focus-within:ring-blue-100 focus-within:border-blue-400 transition-all">
                        <div class="flex items-center justify-center pl-3 pr-2 py-2 bg-zinc-50 border-r border-zinc-100 text-zinc-500">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar-days"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
                        </div>
                        <input 
                            type="date" 
                            v-model="startDateFilter"
                            class="w-[130px] border-none bg-transparent px-3 py-2 text-sm text-zinc-700 outline-none focus:ring-0 hover:bg-zinc-50 transition-colors"
                            title="Desde"
                        />
                        <div class="hidden sm:block h-5 w-px bg-zinc-200 mx-1"></div>
                        <input 
                            type="date" 
                            v-model="endDateFilter"
                            class="w-[130px] border-none bg-transparent px-3 py-2 text-sm text-zinc-700 outline-none focus:ring-0 hover:bg-zinc-50 transition-colors"
                            title="Hasta"
                        />
                    </div>
                    <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                        <Button 
                            v-if="startDateFilter || endDateFilter" 
                            variant="ghost" 
                            size="icon" 
                            @click="clearDateFilter" 
                            title="Limpiar fechas"
                            class="h-9 w-9 text-zinc-400 hover:text-red-600 hover:bg-red-50 rounded-full"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </Button>
                    </Transition>
                </div>
            </div>

            <!-- Tabla -->
            <div class="bg-card rounded-xl border shadow-sm overflow-hidden flex-1">
                <div class="overflow-x-auto h-full">
                    <table class="w-full table-auto border-collapse">
                        <thead class="bg-muted/50 sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th class="p-4 text-left text-sm font-bold text-zinc-700">Nombre del Staff</th>
                                <th class="p-4 text-left text-sm font-bold text-zinc-700">DNI</th>
                                <th class="p-4 text-left text-sm font-bold text-zinc-700">Fecha de Inicio</th>
                                <th class="p-4 text-left text-sm font-bold text-zinc-700">Estado</th>
                                <th class="p-4 text-center text-sm font-bold text-zinc-700">Expediente Digital</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="staff in filteredStaff" :key="staff.id" class="hover:bg-muted/30 border-t transition-colors group">
                                <td class="p-4">
                                    <div class="font-semibold text-zinc-900">{{ staff.name }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="text-sm text-zinc-600">{{ staff.dni }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                        {{ formatDate(getStartDate(staff)) }}
                                    </div>
                                </td>
                                <td class="p-4">
                                    <StatusBadge 
                                        :label="getStatusLabel(staff.status)" 
                                        :color-class="getStatusColor(staff.status)" 
                                    />
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <!-- Botón de expediente digital utilizando FilesModal de Staff -->
                                        <FilesModal :staff="(staff as any)" />
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredStaff.length === 0">
                                <td colspan="5" class="p-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-zinc-500 space-y-2">
                                        <Search class="h-8 w-8 text-zinc-300" />
                                        <p class="font-medium text-lg">No se encontraron registros</p>
                                        <p class="text-sm text-zinc-400">Intenta cambiar los filtros de búsqueda</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Resumen -->
            <div class="text-sm text-muted-foreground font-medium px-1">
                Mostrando {{ filteredStaff.length }} {{ filteredStaff.length === 1 ? 'registro' : 'registros' }}
                <span v-if="filteredStaff.length !== (props.staff?.length || 0)">
                    (de {{ props.staff?.length || 0 }} en total)
                </span>
            </div>
        </div>
    </AppLayout>
</template>
