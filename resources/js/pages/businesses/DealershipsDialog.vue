<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

interface DealershipRecord {
    id: number;
    name: string;
    ruc: string | null;
    fiscal_address: string | null;
    legal_address: string | null;
    phone: string | null;
    email: string | null;
}

const props = defineProps<{
    open: boolean;
    dealership?: DealershipRecord | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const form = useForm({
    name: '',
    ruc: '',
    fiscal_address: '',
    legal_address: '',
    phone: '',
    email: '',
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        form.clearErrors();
        if (props.dealership) {
            form.name = props.dealership.name || '';
            form.ruc = props.dealership.ruc || '';
            form.fiscal_address = props.dealership.fiscal_address || '';
            form.legal_address = props.dealership.legal_address || '';
            form.phone = props.dealership.phone || '';
            form.email = props.dealership.email || '';
        } else {
            form.reset();
        }
    },
);

const submit = () => {
    const onSuccess = () => {
        emit('update:open', false);
        form.reset();
    };

    if (props.dealership) {
        form.put(route('dealerships.update', props.dealership.id), { onSuccess });
    } else {
        form.post(route('dealerships.store'), { onSuccess });
    }
};
</script>
<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ dealership ? 'Editar Concesionaria' : 'Insertar Concesionaria' }}</DialogTitle>
            </DialogHeader>
            <div class="space-y-1">
                <Input type="text" v-model="form.name" placeholder="Nombre de la Concesionaria" class="mb-1" />
                <span v-if="form.errors.name" class="block text-xs text-red-500">{{ form.errors.name }}</span>
            </div>
            <Input type="text" v-model="form.ruc" placeholder="RUC de la Concesionaria" class="mb-1" />
            <Input type="text" v-model="form.fiscal_address" placeholder="Dirección Fiscal" class="mb-1" />
            <Input type="text" v-model="form.legal_address" placeholder="Dirección Legal" class="mb-1" />
            <Input type="text" v-model="form.phone" placeholder="Teléfono" class="mb-1" />
            <div class="space-y-1">
                <Input type="email" v-model="form.email" placeholder="Correo Electrónico" class="mb-1" />
                <span v-if="form.errors.email" class="block text-xs text-red-500">{{ form.errors.email }}</span>
            </div>
            <DialogFooter class="gap-2">
                <Button type="button" variant="ghost" @click="emit('update:open', false)">Cancelar</Button>
                <Button type="button" :disabled="form.processing" @click="submit">
                    {{ form.processing ? 'Guardando...' : dealership ? 'Actualizar' : 'Registrar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
