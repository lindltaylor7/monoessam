<script setup lang="ts">
import StaffSelectables from '../StaffSelectables.vue';
import GuardAreaDroppable from '../GuardAreaDroppable.vue';
import { Cafe, Role, User } from '@/types';

interface Props {
    guardsSelected: Cafe[];
    unassignedUsers: User[];
    assignedUsers: User[];
    roles: Role[];
}

defineProps<Props>();
const emit = defineEmits([
    'user-dropped', 
    'assign-roles', 
    'delete-guard-role', 
    'delete-guard', 
    'unassign-user'
]);
</script>

<template>
    <div v-if="guardsSelected && guardsSelected.length > 0" class="grid h-full auto-rows-fr gap-6 md:grid-cols-8">
        <StaffSelectables :users="unassignedUsers" class="md:col-span-2" />
        <div class="md:col-span-6">
            <div class="overflow-x-auto" v-for="guard in guardsSelected" :key="guard.id">
                <div class="flex h-full gap-6">
                    <GuardAreaDroppable
                        :users="assignedUsers"
                        :roles="roles"
                        :guard="guard as any"
                        @dropped="(userId) => $emit('user-dropped', userId)"
                        @asignRolesToGuard="(guardId, roles) => $emit('assign-roles', guardId, roles)"
                        @deleteGuardRole="(guardId, roleId) => $emit('delete-guard-role', guardId, roleId)"
                        @deleteGuard="(guardId) => $emit('delete-guard', guardId)"
                        @unassignUser="(userId) => $emit('unassign-user', userId)"
                        :unassignedUsers="unassignedUsers"
                    />
                </div>
            </div>
        </div>
    </div>
    <div v-else class="flex h-full items-center justify-center p-10 text-center">
        <p class="text-xl font-semibold text-gray-500">
            ⚠️ Por favor seleccione un comedor y asigne guardias para continuar.
            <br />
            (La lista de guardias aparecerá aquí una vez se asignen).
        </p>
    </div>
</template>
