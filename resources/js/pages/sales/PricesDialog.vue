<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { useForm } from '@inertiajs/vue3';
import { DollarSign } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps({
    services: {
        type: Array,
        required: true,
    },
});
const open = ref(false);

const servicesCopy = ref([]);

watch(props, (newVal) => {
    servicesCopy.value = newVal.services;
});

const form = useForm({
    services: [],
});

const submit = () => {
    form.services = servicesCopy.value;

    form.put(route('services.update-prices'), {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger>
            <Button title="Agregar comensales" class="bg-green-600"><DollarSign /> Gestionar Precios</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Gestionar Precios de Servicios</DialogTitle>
            </DialogHeader>
            <div class="flex flex-col gap-4">
                <div v-for="service in servicesCopy" :key="service.id" class="flex items-center gap-4">
                    <p class="w-80 truncate">{{ service.name }}</p>
                    <Input class="w-20" type="number" v-model="service.pivot.price" />
                </div>
            </div>
            <DialogFooter>
                <Button @click="submit">Actualizar Precios</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
