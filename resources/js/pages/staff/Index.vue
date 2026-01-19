<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { Business, Cafe, Role, Staff, Unit } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import StaffRegistrationDialog from './StaffRegistrationDialog.vue';
import StaffTable from './partials/StaffTable.vue';
import StaffMobileCard from './partials/StaffMobileCard.vue';
import { useStaffFilter } from '@/composables/useStaffFilter';
import { useStaffActions } from '@/composables/useStaffActions';


interface Props {
    cafes: Cafe[];
    staff: Staff[];
    roles: Role[];
    units: Unit[];
    businneses: Business[];
}

const props = defineProps<Props>();

// Gestión local del estado para permitir actualizaciones sin recarga completa
const localStaff = ref(props.staff);

watch(
    () => props.staff,
    (newVal) => {
        localStaff.value = newVal;
    }
);

// Composables
// Composables
const { filteredStaff, searchQuery, selectedUnitId } = useStaffFilter(localStaff);

const { deleteStaff } = useStaffActions((id) => {
    localStaff.value = localStaff.value.filter((s) => s.id !== id);
});

// Paginación
import { computed } from 'vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const currentPage = ref(1);
const itemsPerPage = 10;

const totalPages = computed(() => Math.ceil(filteredStaff.value.length / itemsPerPage));

const paginatedStaff = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredStaff.value.slice(start, end);
});

const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
};

const prevPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--;
    }
};

// Reset page when filter changes
watch(filteredStaff, () => {
    currentPage.value = 1;
});

</script>

<template>
    <Head title="Personal" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold tracking-tight">Personal</h1>
                <StaffRegistrationDialog 
                    :cafes="props.cafes" 
                    :roles="props.roles" 
                    :units="props.units" 
                    :businneses="props.businneses" 
                />
            </div>

            <div class="flex items-center">
                <Input 
                    type="text" 
                    placeholder="Buscar personal por dni o nombre" 
                    v-model="searchQuery"
                />
                <Select v-model="selectedUnitId">
                    <SelectTrigger class="h-10 border-zinc-200 bg-white hover:bg-zinc-50 ml-2 w-[200px]">
                        <SelectValue placeholder="Seleccionar unidad" />
                    </SelectTrigger>
                    <SelectContent class="border-zinc-200 bg-white shadow-lg">
                        <SelectItem value="0" class="hover:bg-zinc-50"> Ninguna </SelectItem>
                        <SelectItem :value="String(unit.id)" class="hover:bg-zinc-50" v-for="unit in units" :key="unit.id">
                            {{ unit.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="bg-card rounded-xl border shadow-sm">
                <!-- Vista Desktop -->
                <StaffTable 
                    :staff-list="paginatedStaff"
                    :cafes="props.cafes"
                    :roles="props.roles"
                    :units="props.units"
                    :businesses="props.businneses"
                    @delete-staff="deleteStaff"
                />

                <!-- Vista Mobile: Cards -->
                <div class="space-y-4 md:hidden p-4">
                    <StaffMobileCard 
                        v-for="staff in paginatedStaff" 
                        :key="staff.id"
                        :staff="staff"
                        :cafes="props.cafes"
                        :roles="props.roles"
                        :units="props.units"
                        :businesses="props.businneses"
                        @delete-staff="deleteStaff"
                    />

                    <!-- Mensaje cuando no hay datos -->
                    <div v-if="!paginatedStaff || paginatedStaff.length === 0" class="py-12 text-center">
                        <p class="text-muted-foreground">No hay personal registrado</p>
                    </div>
                </div>

                <!-- Controles de Paginación -->
                <div class="flex items-center justify-between border-t p-4" v-if="totalPages > 1">
                    <div class="text-sm text-muted-foreground">
                        Mostrando {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, filteredStaff.length) }} de {{ filteredStaff.length }} registros
                    </div>
                    <div class="flex items-center gap-2">
                        <Button 
                            variant="outline" 
                            size="sm" 
                            :disabled="currentPage === 1" 
                            @click="prevPage"
                        >
                            <ChevronLeft class="h-4 w-4" />
                            Anterior
                        </Button>
                        <div class="text-sm font-medium">
                            Página {{ currentPage }} de {{ totalPages }}
                        </div>
                        <Button 
                            variant="outline" 
                            size="sm" 
                            :disabled="currentPage === totalPages" 
                            @click="nextPage"
                        >
                            Siguiente
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
