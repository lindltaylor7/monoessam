<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Area, Permission, Role, User } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { ref } from 'vue';
import RoleModal from '../headcount/RoleModal.vue';
import RolePermissionsPanel from './RolePermissionsPanel.vue';

interface RoleWithPermissions extends Role {
    permissions?: Permission[];
    area?: string;
}

interface Props {
    users: User[];
    roles: RoleWithPermissions[];
    areas: Area[];
    permissions: Permission[];
}

defineProps<Props>();

const selectedRole = ref<RoleWithPermissions | null>(null);

const selectRole = (role: RoleWithPermissions) => {
    selectedRole.value = selectedRole.value?.id === role.id ? null : role;
};
</script>

<template>
    <Head title="Roles" />
    <AppLayout>
        <div class="flex h-full flex-1 gap-4 overflow-hidden p-4">
            <!-- Left: Table -->
            <div class="flex min-w-0 flex-1 flex-col gap-4">
                <!-- Toolbar -->
                <div class="flex h-12 w-full items-center justify-between gap-3 rounded-lg bg-muted/50 p-2 shadow-sm">
                    <h1 class="px-2 text-sm font-semibold text-foreground">Roles del sistema</h1>
                    <RoleModal :areas="areas" />
                </div>

                <!-- Table -->
                <div class="flex-1 overflow-auto rounded-xl border shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 border-b bg-muted/70 backdrop-blur">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Nombre
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Área
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Permisos
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Estado
                                </th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="role in roles"
                                :key="role.id"
                                class="cursor-pointer transition-colors hover:bg-muted/30"
                                :class="selectedRole?.id === role.id ? 'bg-blue-50 dark:bg-blue-950/20' : ''"
                                @click="selectRole(role)"
                            >
                                <td class="px-4 py-3 font-medium">{{ role.name }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ role.area ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-muted-foreground">
                                        {{ role.permissions?.length ?? 0 }} permisos
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950/60 dark:text-green-400">
                                        Activo
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <ChevronRight
                                        class="ml-auto h-4 w-4 text-muted-foreground transition-transform duration-200"
                                        :class="selectedRole?.id === role.id ? 'rotate-90 text-blue-500' : ''"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right: Permissions side panel -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 translate-x-6"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 translate-x-6"
            >
                <RolePermissionsPanel
                    v-if="selectedRole"
                    :role="selectedRole"
                    :permissions="permissions"
                    @close="selectedRole = null"
                />
            </Transition>
        </div>
    </AppLayout>
</template>
