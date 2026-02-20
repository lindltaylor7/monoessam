<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger, DialogDescription } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Headquarter } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    headquarters: {
        type: Array as () => Headquarter[],
        required: true,
    },
    headquarterId: Number,
});

const open = ref(false);

const form = useForm({
    name: '',
    headquarter_id: null,
});

form.headquarter_id = props.headquarterId;

const submit = () => {
    form.post(route('areas.store'), {
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
            <Button
                class="flex cursor-pointer items-center justify-center rounded-lg border border-transparent bg-green-50 p-2 text-green-600 transition-all duration-200 ease-in-out hover:scale-105 hover:border-green-200 hover:bg-green-100 hover:text-green-700 hover:shadow-sm dark:bg-green-900/20 dark:text-green-400 dark:hover:bg-green-900/40"
                title="Agregar area"
            >
                <Plus class="h-5 w-5" /> </Button
        ></DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Nueva Area</DialogTitle>
                <DialogDescription>Por favor, complete los campos siguientes</DialogDescription>
                <Input v-model="form.name" type="text" placeholder="Nombre" />
            </DialogHeader>
            <div class="flex flex-row">
                <Select v-model="form.headquarter_id">
                    <SelectTrigger>
                        <SelectValue placeholder="Selecciona una sede" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectLabel>Sedes</SelectLabel>
                            <SelectItem v-for="headquarter in headquarters" :value="headquarter.id" :key="headquarter.id">
                                {{ headquarter.business.name }} - {{ headquarter.name }}
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>

            <DialogFooter @click="submit"> Agregar </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
