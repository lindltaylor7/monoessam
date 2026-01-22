<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { ListCheck, Pencil } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Permission } from '@/types';

const props = defineProps<{
    permission?: Permission;
}>();

const open = ref(false);

const form = useForm({
    name: props.permission?.name ?? '',
    sidebar_name: props.permission?.sidebar_name ?? '',
    route_name: props.permission?.route_name ?? '',
    icon_class: props.permission?.icon_class ?? '',
});

// Update form when prop changes (for editing)
watch(() => props.permission, (newPermission) => {
    if (newPermission) {
        form.name = newPermission.name;
        form.sidebar_name = newPermission.sidebar_name ?? '';
        form.route_name = newPermission.route_name ?? '';
        form.icon_class = newPermission.icon_class ?? '';
    }
}, { immediate: true });

const submit = () => {
    if (props.permission) {
        form.put(route('permissions.update', props.permission.id), {
            onSuccess: () => {
                open.value = false;
            },
        });
    } else {
        form.post(route('permissions.store'), {
            onSuccess: () => {
                open.value = false;
                form.reset();
            },
        });
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button v-if="permission" variant="ghost" size="icon" title="Editar Permiso">
                <Pencil class="h-4 w-4" />
            </Button>
            <Button v-else title="Agregar Permiso" class="h-full w-auto bg-blue-400">
                <ListCheck />
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ permission ? 'Editar Permiso' : 'Nuevo Permiso' }}</DialogTitle>
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="grid gap-2">
                    <Label for="name">Nombre</Label>
                    <Input id="name" v-model="form.name" type="text" placeholder="p.ej. users.index" />
                </div>
                <div class="grid gap-2">
                    <Label for="sidebar_name">Nombre de Sidebar</Label>
                    <Input id="sidebar_name" v-model="form.sidebar_name" type="text" placeholder="p.ej. Usuarios" />
                </div>
                <div class="grid gap-2">
                    <Label for="route_name">URL / Ruta</Label>
                    <Input id="route_name" v-model="form.route_name" type="text" placeholder="p.ej. /users" />
                </div>
                <div class="grid gap-2">
                    <Label for="icon_class">Icono (Clase o nombre)</Label>
                    <Input id="icon_class" v-model="form.icon_class" type="text" placeholder="p.ej. Users" />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="open = false">Cancelar</Button>
                <Button :disabled="form.processing" @click="submit">
                    {{ permission ? 'Guardar Cambios' : 'Agregar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
