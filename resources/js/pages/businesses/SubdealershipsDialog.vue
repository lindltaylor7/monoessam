<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/vue3';
import { CirclePlus } from 'lucide-vue-next';
import { ref } from 'vue';

const open = ref(false);

const props = defineProps({
    dealerships: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    name: '',
    ruc: '',
    fiscal_address: '',
    legal_address: '',
    phone: '',
    email: '',
    dealership_id: 0,
});

const submit = () => {
    form.post(route('subdealerships.store'), {
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
            ><Button title="Agregar Subconcesionaria" class="h-full w-auto bg-blue-400"><CirclePlus /></Button
        ></DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Insertar Subconcesionaria </DialogTitle>
            </DialogHeader>
            <Input type="text" v-model="form.name" placeholder="Nombre del Servicio" class="mb-1" />
            <Input type="text" v-model="form.ruc" placeholder="RUC de la Concesionaria" class="mb-1" />
            <Input type="text" v-model="form.fiscal_address" placeholder="Dirección Fiscal" class="mb-1" />
            <Input type="text" v-model="form.legal_address" placeholder="Dirección Legal" class="mb-1" />
            <Input type="text" v-model="form.phone" placeholder="Teléfono" class="mb-1" />
            <Input type="email" v-model="form.email" placeholder="Correo Electrónico" class="mb-1" />
            <Select v-model="form.dealership_id" class="w-full">
                <SelectTrigger>
                    <SelectValue placeholder="Selecciona un concesionario" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectLabel>Concesionarias</SelectLabel>
                        <SelectItem v-for="dealership in props.dealerships" :value="dealership.id" :key="dealership.id">
                            {{ dealership.name }}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
            <DialogFooter @click="submit"> Registrar </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
