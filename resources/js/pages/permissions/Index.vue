<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Permission, Role, User } from '@/types';
import PermissionModal from './PermissionModal.vue';
import { Trash2 } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import { router } from '@inertiajs/vue3';

interface Props {
    users: User[];
    roles: Role[];
    permissions: Permission[];
}

defineProps<Props>();

const deletePermission = (id: number) => {
    if (confirm('¿Estás seguro de que deseas eliminar este permiso?')) {
        router.delete(route('permissions.destroy', id));
    }
};
</script>

<template>
    <AppLayout title="Permisos">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div
                class="flex h-12 w-full items-center justify-between gap-3 rounded-lg bg-gradient-to-r from-blue-50 to-purple-50 p-2 px-4 shadow-sm dark:from-gray-700 dark:to-gray-700"
            >
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Gestión de Permisos</h2>
                <div class="flex items-center gap-2">
                    <PermissionModal />
                </div>
            </div>
            
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border">
                <Card>
                    <Table>
                        <TableCaption>Lista de Permisos del Sistema.</TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Sidebar</TableHead>
                                <TableHead>Ruta</TableHead>
                                <TableHead>Icono</TableHead>
                                <TableHead class="text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="permission in permissions" :key="permission.id">
                                <TableCell class="font-medium">{{ permission.name }}</TableCell>
                                <TableCell>{{ permission.sidebar_name }}</TableCell>
                                <TableCell>{{ permission.route_name }}</TableCell>
                                <TableCell>{{ permission.icon_class }}</TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <PermissionModal :permission="permission" />
                                        <Button 
                                            variant="ghost" 
                                            size="icon" 
                                            class="text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20"
                                            @click="deletePermission(permission.id)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="permissions.length === 0">
                                <TableCell colspan="5" class="text-center py-4 text-muted-foreground">
                                    No hay permisos registrados.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
