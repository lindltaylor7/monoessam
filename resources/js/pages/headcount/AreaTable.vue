<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Area, Role } from '@/types';
import { router } from '@inertiajs/vue3';
import { Trash } from 'lucide-vue-next';
import AreaRolesDialog from './AreaRolesDialog.vue';

const props = defineProps<{
    areas?: Area[];
    roles?: Role[];
}>();

const deleteArea = (areaId: any) => {
    if (confirm('Estas seguro de eliminar el area?')) {
        router.delete(route('areas.destroy', areaId));
    }
};
</script>
<template>
    <Table>
        <TableCaption>Lista de Areas del Sistema.</TableCaption>
        <TableHeader>
            <TableRow>
                <TableHead class="w-[100px]">Area</TableHead>
                <TableHead class="">Sede o Cafetería</TableHead>
                <TableHead>Opciones</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <TableRow v-for="area in props.areas" :key="area.id">
                <TableCell class="font-medium">{{ area.name }}</TableCell>
                <TableCell class="font-medium">
                    {{
                        area.headquarter
                            ? 'Sede - ' + area.headquarter.name
                            : area.cafe
                              ? 'Cafe  - ' + area.cafe.name + ' - ' + area.cafe.unit.name
                              : ''
                    }}
                </TableCell>
                <TableCell>
                    <AreaRolesDialog :area="area" :roles="roles" />
                    <Button class="ms-2" @click="deleteArea(area.id)"><Trash /></Button
                ></TableCell>
            </TableRow>
        </TableBody>
    </Table>
</template>
