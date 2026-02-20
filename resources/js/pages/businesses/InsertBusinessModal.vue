<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger, DialogDescription } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { useForm } from '@inertiajs/vue3';
import { Building2 } from 'lucide-vue-next';

import { ref } from 'vue';

const open = ref(false);

const form = useForm({
    name: '',
    ruc: '',
    fiscal_address: '',
    legal_address: '',
    email: '',
});

const submit = () => {
    form.post(route('businesses'), {
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
            ><Button title="Agregar Empresa" class="h-full w-auto bg-blue-400"><Building2 /></Button
        ></DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Insertar Empresa </DialogTitle>
                <DialogDescription>Por favor, complete los campos siguientes</DialogDescription>
                <Input type="text" v-model="form.name" placeholder="Nombre de la Empresa" class="mb-1" />
                <Input type="text" v-model="form.ruc" placeholder="RUC" class="mb-1" />
                <Input type="text" v-model="form.fiscal_address" placeholder="Dirección Fiscal" class="mb-1" />
                <Input type="text" v-model="form.legal_address" placeholder="Dirección Legal" class="mb-1" />
                <Input type="text" v-model="form.email" placeholder="Email" class="mb-1" />
            </DialogHeader>
            <DialogFooter @click="submit"> Agregar </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
