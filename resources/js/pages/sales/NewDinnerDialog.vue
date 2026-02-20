<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Cafe } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';
const open = ref(false);

const props = defineProps<{
    cafes: Cafe[];
    subdealerships: any[];
}>();

const form = useForm({
    name: '',
    dni: '',
    phone: '',
    subdealership_id: null,
    cafe_id: null,
});

const submit = () => {
    form.post(route('dinners.insert'), {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button title="Agregar Usuario" class="w-full h-11 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-200 transition-all flex items-center justify-center gap-2">
                <UserPlus class="h-4 w-4" /> 
                Nuevo Comensal
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Nuevo Comensal</DialogTitle>
                <Input v-model="form.name" type="text" placeholder="Nombre" />
                <Input v-model="form.dni" type="text" placeholder="DNI" />
                <Input v-model="form.phone" type="text" placeholder="Teléfono" />
            </DialogHeader>
            <div class="flex w-full flex-col">
                <div class="w-full">
                    <Select v-model="form.subdealership_id">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Selecciona una subconcecionaria" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Subconcecionaria</SelectLabel>
                                <SelectItem v-for="subdealership in subdealerships" :value="subdealership.id" :key="subdealership.id">
                                    {{ subdealership.name }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>
                <div class="mt-2 w-full">
                    <Select class="w-full" v-model="form.cafe_id">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Selecciona una cafetería" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Cafeterías</SelectLabel>
                                <SelectItem v-for="cafe in props.cafes" :value="cafe.id" :key="cafe.id"> {{ cafe.name }} </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <DialogFooter>
                <Button @click="submit">Agregar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
