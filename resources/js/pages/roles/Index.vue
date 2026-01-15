<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Area, Permission, Role, User } from '@/types';
import RoleModal from '../headcount/RoleModal.vue';
import RolePermissionsModal from './RolePermissionsModal.vue';

interface Props {
    users: User[];
    roles: Role[];
    areas: Area[];
    permissions: Permission[];
}

defineProps<Props>();
</script>
<template>
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div
                class="flex h-12 w-full items-center justify-start gap-3 rounded-lg bg-gradient-to-r from-blue-50 to-purple-50 p-2 shadow-sm dark:from-gray-700 dark:to-gray-700"
            >
                <RoleModal :areas="areas" />
            </div>
            <div class="grid w-full grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card
                    v-for="role in roles"
                    :key="role.id"
                    class="border-sidebar-border/50 dark:border-sidebar-border/70 rounded-2xl border shadow-sm transition hover:shadow-md"
                >
                    <div class="flex flex-col gap-3 p-4">
                        <h3 class="text-foreground text-lg font-semibold">
                            {{ role.name }}
                        </h3>

                        <RolePermissionsModal :role="role" :permissions="permissions" />

                        <div class="text-muted-foreground flex items-center justify-between text-sm">
                            <span class="font-medium">Estado:</span>
                            <span class="text-green-600 dark:text-green-400">Activo</span>
                        </div>

                        <div class="text-muted-foreground flex items-center justify-between text-sm">
                            <span class="font-medium">Área:</span>
                            <span>{{ role.area ?? '—' }}</span>
                        </div>
                    </div>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
