<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { useForm } from '@inertiajs/vue3';
import { CirclePlus } from 'lucide-vue-next';
import { ref } from 'vue';

const open = ref(false);

const form = useForm({
    name: '',
    ruc: '',
    fiscal_address: '',
    legal_address: '',
    phone: '',
    email: '',
});

const submit = () => {
    form.post(route('dealerships.store'), {
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
            ><Button title="Agregar Concesionaria" class="h-full w-auto bg-blue-400"><CirclePlus /></Button
        ></DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Insertar Concesionaria </DialogTitle>
            </DialogHeader>
            <Input type="text" v-model="form.name" placeholder="Nombre del Servicio" class="mb-1" />
            <Input type="text" v-model="form.ruc" placeholder="RUC de la Concesionaria" class="mb-1" />
            <Input type="text" v-model="form.fiscal_address" placeholder="Dirección Fiscal" class="mb-1" />
            <Input type="text" v-model="form.legal_address" placeholder="Dirección Legal" class="mb-1" />
            <Input type="text" v-model="form.phone" placeholder="Teléfono" class="mb-1" />
            <Input type="email" v-model="form.email" placeholder="Correo Electrónico" class="mb-1" />
            <DialogFooter @click="submit"> Registrar </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
