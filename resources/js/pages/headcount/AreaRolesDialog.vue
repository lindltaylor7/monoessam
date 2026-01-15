<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { LampDesk } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    area: {
        type: Object,
        required: true,
    },
    roles: {
        type: Array,
        required: true,
    },
});

const open = ref(false);

const form = useForm({
    name: '',
    cafe_id: 0,
    headquarter_id: null,
});

const submit = () => {
    form.post(route('areas'), {
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
            ><Button title="Agregar Area" class="h-full w-auto bg-blue-400"><LampDesk /></Button
        ></DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Roles de {{ area.name }} - {{ area.headquarter ? area.headquarter.name : area.cafe ? area.cafe.name : '' }}</DialogTitle>
            </DialogHeader>
            <div class="flex items-center space-x-2" v-for="role in props.roles" :key="role.id">
                <Checkbox id="terms" />
                <label for="terms" class="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                    {{ role.name }}
                </label>
            </div>
            <DialogFooter @click="submit"> Actualizar </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
