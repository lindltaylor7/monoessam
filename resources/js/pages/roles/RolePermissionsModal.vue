<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Switch } from '@/components/ui/switch';
import { Permission, Role } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { ListCheck, ShieldCheck } from 'lucide-vue-next';
import { ref } from 'vue';

interface RoleWithPermissions extends Role {
    permissions?: Permission[];
}

interface Props {
    permissions: Permission[];
    role: RoleWithPermissions;
}

const props = defineProps<Props>();

const open = ref(false);

const form = useForm({
    role_id: props.role?.id,
    permissions: props.role?.permissions?.map((p: Permission) => p.id) ?? [],
});

const isEnabled = (permissionId: number) => form.permissions.includes(permissionId);

const toggle = (permissionId: number) => {
    const idx = form.permissions.indexOf(permissionId);
    if (idx === -1) {
        form.permissions.push(permissionId);
    } else {
        form.permissions.splice(idx, 1);
    }
};

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
        <DialogTrigger as-child>
            <Button title="Editar Permisos" class="h-full w-auto bg-blue-500 hover:bg-blue-600">
                <ListCheck class="mr-1 h-4 w-4" />
                Permisos
            </Button>
        </DialogTrigger>

        <DialogContent class="flex max-h-[85vh] flex-col gap-0 p-0 sm:max-w-[500px]">
            <!-- Header -->
            <DialogHeader class="border-b px-6 py-4">
                <DialogTitle class="text-lg font-semibold">Permisos del Rol</DialogTitle>
                <div class="mt-1 flex items-center gap-2">
                    <span class="text-muted-foreground text-sm">Rol:</span>
                    <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                        {{ role.name }}
                    </span>
                </div>
            </DialogHeader>

            <!-- Info banner -->
            <div class="bg-blue-50 px-6 py-2.5 dark:bg-blue-950/40">
                <p class="text-xs text-blue-600 dark:text-blue-400">
                    Activa o desactiva los permisos para este rol.
                </p>
            </div>

            <!-- Permissions list -->
            <div class="flex-1 overflow-y-auto">
                <div class="divide-y divide-border">
                    <div
                        v-for="permission in permissions"
                        :key="permission.id"
                        class="flex items-center justify-between gap-4 px-6 py-3.5 transition-colors hover:bg-muted/40"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                :class="isEnabled(permission.id)
                                    ? 'bg-blue-100 dark:bg-blue-950'
                                    : 'bg-muted dark:bg-muted/60'"
                            >
                                <ShieldCheck
                                    class="h-4 w-4 transition-colors"
                                    :class="isEnabled(permission.id) ? 'text-blue-500' : 'text-muted-foreground'"
                                />
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium leading-snug">{{ permission.name }}</p>
                                <p v-if="permission.sidebar_name" class="text-muted-foreground truncate text-xs leading-snug">
                                    {{ permission.sidebar_name }}
                                </p>
                                <p v-else class="text-muted-foreground truncate text-xs leading-snug">
                                    {{ permission.route_name ?? 'Permiso del sistema' }}
                                </p>
                            </div>
                        </div>

                        <Switch
                            :checked="isEnabled(permission.id)"
                            @update:checked="toggle(permission.id)"
                            class="shrink-0 data-[state=checked]:bg-blue-500"
                        />
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <DialogFooter class="border-t px-6 py-4">
                <div class="flex w-full items-center justify-between gap-3">
                    <span class="text-muted-foreground text-xs">
                        {{ form.permissions.length }} de {{ permissions.length }} permisos activos
                    </span>
                    <Button
                        class="bg-blue-500 hover:bg-blue-600 text-white"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        Guardar cambios
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
