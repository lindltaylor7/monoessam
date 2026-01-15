<script setup lang="ts">
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Permission, Role } from '@/types';
import RolePermissionPopover from './RolePermissionPopover.vue';

const props = defineProps<{
    roles?: Role[];
    permissions?: Permission[];
}>();
</script>

<template>
    <Table>
        <TableCaption>Lista de Roles del Sistema.</TableCaption>
        <TableHeader>
            <TableRow>
                <TableHead class="w-[100px]">Rol</TableHead>
                <TableHead>Guard</TableHead>
                <TableHead>Area</TableHead>
                <TableHead>Opciones</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <TableRow v-for="role in roles" :key="role.id">
                <TableCell class="font-medium">{{ role.name }}</TableCell>
                <TableCell>{{ role.guard_name }}</TableCell>
                <TableCell>{{ role.areas[0]?.name }}</TableCell>
                <TableCell>
                    <RolePermissionPopover :role="role" :permissions="permissions" />
                </TableCell>
            </TableRow>
        </TableBody>
    </Table>
</template>
