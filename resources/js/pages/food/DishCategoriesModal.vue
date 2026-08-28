<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Cafe, Headquarter } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    cafes: {
        type: Array as () => Cafe[],
        required: true,
    },
    headquarters: {
        type: Array as () => Headquarter[],
        required: true,
    },
});

const optionUnits = ['Mililitros', 'Gramos'];

const open = ref(false);

const form = useForm({
    name: '',
    description: '',
    mesearument_unit: '',
});

const submit = () => {
    form.post(route('dish-categories.store'), {
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
            ><Button title="Agregar Area" class="h-full w-auto bg-blue-400"><Plus /></Button
        ></DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Nueva Categoría de Platos</DialogTitle>
                <Input v-model="form.name" type="text" placeholder="Nombre" />
                <Input v-model="form.description" type="text" placeholder="Descripción" />
            </DialogHeader>
            <div class="flex w-full flex-row">
                <Select v-model="form.mesearument_unit">
                    <SelectTrigger>
                        <SelectValue placeholder="Selecciona una unidad" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectLabel>Unidades</SelectLabel>
                            <SelectItem v-for="option in optionUnits" :value="option"> {{ option }} </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>

            <DialogFooter @click="submit"> Agregar </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
