<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Area, Cafe, Headquarter, Mine, Permission, Role, Unit, User } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import TableHeadcount from './TableHeadcount.vue';
import HeadcountFilters from './partials/HeadcountFilters.vue';
import HeadcountGrid from './partials/HeadcountGrid.vue';
import { useHeadcountSelection } from '@/composables/useHeadcountSelection';
import { useHeadcountData } from '@/composables/useHeadcountData';

interface Props {
    users: User[];
    roles: Role[];
    permissions: Permission[];
    areas: Area[];
    headquarters: Headquarter[];
    mines: Mine[];
    units: Unit[];
}

const props = defineProps<Props>();

// UI State
const changedView = ref(false);

const changeView = () => {
    changedView.value = !changedView.value;
};

// Composables
const { selectedOptions, selectedUnits, selectedCafes } = useHeadcountSelection(props.mines);

const { 
    guardsSelected, 
    unassignedUsers, 
    assignedUsers, 
    selectedPeriods, 
    fetchCafeData,
    assignGuards,
    deleteGuard,
    handleUserAssignment,
    unassignUser,
    asignRolesToGuard,
    deleteGuardRole
} = useHeadcountData();

// Watch selection to fetch data
watch(
    () => selectedOptions.value.cafe,
    (newCafeId) => {
        if (newCafeId) {
            fetchCafeData(Number(newCafeId));
        }
    }
);

const exportToExcel = () => {
    const cafeId = selectedOptions.value.cafe;
    if (!cafeId) {
        alert('Por favor seleccione un comedor antes de exportar.');
        return;
    }
    window.location.href = `/cafes/${cafeId}/export-headcount`;
};
</script>

<template>
    <Head title="Headcount" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex">
                <h1 class="text-2xl font-bold">Puestos</h1>
            </div>

            <!-- Filters & Controls -->
            <HeadcountFilters
                :mines="props.mines"
                :selected-units="selectedUnits"
                :selected-cafes="selectedCafes"
                :selected-options="selectedOptions"
                :changed-view="changedView"
                @toggle-view="changeView"
                @export-excel="exportToExcel"
                @assign-guards="assignGuards"
            />

            <!-- Drag & Drop Grid View -->
            <div class="h-full" :hidden="changedView">
                <HeadcountGrid
                    :guards-selected="guardsSelected"
                    :unassigned-users="unassignedUsers"
                    :assigned-users="assignedUsers"
                    :roles="roles"
                    @user-dropped="handleUserAssignment"
                    @assign-roles="asignRolesToGuard"
                    @delete-guard-role="deleteGuardRole"
                    @delete-guard="deleteGuard"
                    @unassign-user="(userId) => unassignUser(userId, Number(selectedOptions.cafe) || 0)"
                />
            </div>

            <!-- List Table View -->
            <div class="h-full" :hidden="!changedView">
                <div class="grid h-full">
                    <TableHeadcount
                        :guards="guardsSelected"
                        :users="assignedUsers"
                        :cafeId="String(selectedOptions.cafe || '')"
                        :periods="selectedPeriods"
                        @fetchCafeData="(id) => fetchCafeData(Number(id))"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
