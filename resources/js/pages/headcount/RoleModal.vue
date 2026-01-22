<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Area } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { Shield } from 'lucide-vue-next';
import { ref } from 'vue';

const open = ref(false);

const props = defineProps<{
    areas: Area[];
}>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    area_id: null,
});

const submit = () => {
    form.post(route('roles.store'), {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger
            ><Button title="Agregar Rol" class="h-full w-auto bg-blue-400"><Shield /></Button
        ></DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Agregar Rol</DialogTitle>
                <Input v-model="form.name" type="text" placeholder="Nombre" />
                <Select v-model="form.area_id">
                    <SelectTrigger>
                        <SelectValue placeholder="Selecciona un area para el rol" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectLabel>Areas</SelectLabel>
                            <SelectItem v-for="area in areas" :value="area.id" :key="area.id">
                                {{ area.name }} -
                                {{
                                    area.headquarter
                                        ? 'Sede - ' + area.headquarter.name
                                        : area.cafe
                                          ? 'Cafe  - ' + area.cafe.name + ' - ' + area.cafe.unit.name
                                          : ''
                                }}</SelectItem
                            >
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </DialogHeader>
            <DialogFooter @click="submit"> Agregar </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
