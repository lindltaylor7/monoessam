<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/vue3';
import { ListCheck } from 'lucide-vue-next';
import { ref } from 'vue';

const open = ref(false);

const form = useForm({
    name: '',
    sidebar_name: '',
    route_name: '',
    icon_class: '',
});

const submit = () => {
    form.post(route('permissions.store'), {
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
            ><Button title="Agregar Permiso" class="h-full w-auto bg-blue-400"><ListCheck /></Button
        ></DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Nuevo Permiso</DialogTitle>
                <Input v-model="form.name" type="text" placeholder="Nombre" />
                <Input v-model="form.sidebar_name" type="text" placeholder="Nombre de Sidebar" />
                <Input v-model="form.route_name" type="text" placeholder="URL" />
                <Input v-model="form.icon_class" type="text" placeholder="Icono" />
            </DialogHeader>
            <DialogFooter @click="submit"> Agregar </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
