<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Permission, Role, User } from '@/types';
import RoleModal from '../headcount/RoleModal.vue';
import UserPermissionModal from './UserPermissionModal.vue';
import PermissionModal from './PermissionModal.vue';

interface Props {
    users: User[];
    roles: Role[];
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
                <PermissionModal />
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                <Card>
                    <Table>
                        <TableCaption>Lista de Usuarios del Sistema.</TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[100px]">Usuario</TableHead> <TableHead>Rol</TableHead>
                                <TableHead class="text-right">Opciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="user in users" :key="user.id">
                                <TableCell class="font-medium">{{ user.name }}</TableCell>
                                <TableCell>{{ user.roles[0]?.name }}</TableCell>
                                <TableCell class="text-right">
                                    <UserPermissionModal :permissions="permissions" :user="user" />
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
