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
    code: '',
    description: '',
});

const submit = () => {
    form.post(route('services.store'), {
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
            ><Button title="Agregar Servicio" class="h-full w-auto bg-blue-400"><CirclePlus /></Button
        ></DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Insertar Servicio </DialogTitle>
            </DialogHeader>
            <Input type="text" v-model="form.name" placeholder="Nombre del Servicio" class="mb-1" />
            <Input type="text" v-model="form.code" placeholder="Código del Servicio" class="mb-1" />
            <Input type="text" v-model="form.description" placeholder="Descripción del Servicio" class="mb-1" />
            <DialogFooter @click="submit"> Registrar </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
