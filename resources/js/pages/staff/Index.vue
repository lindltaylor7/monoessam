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
const { filteredStaff, searchQuery, selectedUnitId } = useStaffFilter(localStaff);

const { deleteStaff } = useStaffActions((id) => {
    localStaff.value = localStaff.value.filter((s) => s.id !== id);
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
                    :staff-list="filteredStaff"
                    :cafes="props.cafes"
                    :roles="props.roles"
                    :units="props.units"
                    :businesses="props.businneses"
                    @delete-staff="deleteStaff"
                />

                <!-- Vista Mobile: Cards -->
                <div class="space-y-4 md:hidden p-4">
                    <StaffMobileCard 
                        v-for="staff in filteredStaff" 
                        :key="staff.id"
                        :staff="staff"
                        :cafes="props.cafes"
                        :roles="props.roles"
                        :units="props.units"
                        :businesses="props.businneses"
                        @delete-staff="deleteStaff"
                    />

                    <!-- Mensaje cuando no hay datos -->
                    <div v-if="!filteredStaff || filteredStaff.length === 0" class="py-12 text-center">
                        <p class="text-muted-foreground">No hay personal registrado</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
