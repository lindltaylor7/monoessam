<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Permission, Role } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { ListCheck } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    permissions: Permission[];
    role: Role;
}

const props = defineProps<Props>();

const open = ref(false);

console.log(props.role);

// Lista de IDs seleccionados
const form = useForm({
    role_id: props.role?.id,
    permissions: props.role?.permissions.map((p) => p.id), // Marca los permisos existentes
});

// Enviar
const submit = () => {
   form.post(route('roles.permissions'), {
        onSuccess: () => {
            open.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger>
            <Button title="Editar Permisos" class="h-full w-auto bg-blue-400">
                <ListCheck />
            </Button>
        </DialogTrigger>

        <DialogContent class="max-h-[80vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Permisos del Usuario</DialogTitle>
            </DialogHeader>

            <div class="mt-4 space-y-3">
                <!-- Lista de checkboxes -->
                <div
                    v-for="permission in permissions"
                    :key="permission.id"
                    class="hover:bg-accent flex cursor-pointer items-center gap-3 rounded-lg border p-2"
                >
                    <input type="checkbox" class="h-4 w-4" :value="permission.id" v-model="form.permissions" />
                    <label class="cursor-pointer text-sm">
                        {{ permission.name }}
                    </label>
                </div>
            </div>

            <DialogFooter>
                <Button class="bg-blue-500" @click="submit"> Actualizar permisos </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
