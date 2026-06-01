<script lang="ts" setup>
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { Shield } from 'lucide-vue-next';
import { watch } from 'vue';

interface Permission {
    id: number;
    name: string;
    sidebar_name?: string | null;
}

interface User {
    id: number;
    name: string;
    permissions: Permission[];
    roles: {
        id: number;
        name: string;
        permissions: Permission[];
    }[];
}

const props = defineProps<{
    open: boolean;
    user: User | null;
    permissions: Permission[];
}>();

const emit = defineEmits(['update:open']);

const form = useForm({
    permissions: [] as number[],
    user_id: null as number | null,
});

// Accessing route globally from Ziggy
declare const route: any;

watch(
    () => props.open,
    (newVal) => {
        if (newVal && props.user) {
            form.user_id = props.user.id;

            // Obtener IDs de permisos directos
            const directPermissionIds = props.user.permissions.map((p) => p.id);

            // Obtener IDs de permisos de todos los roles
            const rolePermissionIds = props.user.roles.flatMap((role) => role.permissions.map((p) => p.id));

            // Unir y eliminar duplicados usando un Set
            form.permissions = [...new Set([...directPermissionIds, ...rolePermissionIds])];
        }
    },
);

const isSelected = (id: number) => form.permissions.includes(id);

const togglePermission = (id: number) => {
    const permissions = [...form.permissions];
    const index = permissions.indexOf(id);
    if (index > -1) {
        permissions.splice(index, 1);
    } else {
        permissions.push(id);
    }
    form.permissions = permissions;
};

const submit = () => {
    if (!props.user) return;

    form.post(route('permissions.user.update', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-[700px]">
            <div class="relative bg-orange-600 px-6 py-8 text-white">
                <DialogHeader>
                    <DialogTitle class="text-2xl font-bold">Permisos de Usuario</DialogTitle>
                    <DialogDescription class="text-orange-100">
                        Gestiona los permisos específicos para <span class="font-bold text-white">{{ user?.name }}</span
                        >.
                    </DialogDescription>
                </DialogHeader>
                <div class="bg-background absolute right-6 -bottom-6 flex h-12 w-12 items-center justify-center rounded-full border shadow-lg">
                    <Shield class="h-6 w-6 text-orange-600" />
                </div>
            </div>

            <div class="bg-background px-6 pt-10 pb-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-muted-foreground text-sm font-bold tracking-wider uppercase">Listado de Permisos</h3>
                    <div class="text-muted-foreground text-xs">
                        <span class="font-bold text-orange-600">{{ form.permissions.length }}</span> seleccionados
                    </div>
                </div>

                <div
                    class="custom-scrollbar bg-muted/5 grid max-h-[50vh] grid-cols-1 gap-3 overflow-y-auto rounded-xl border p-4 pr-2 md:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="permission in permissions"
                        :key="permission.id"
                        class="hover:bg-muted/10 flex items-center space-x-3 rounded-lg border border-transparent p-2 transition-colors"
                        :class="{ 'border-orange-100 bg-orange-50': isSelected(permission.id) }"
                    >
                        <Checkbox :id="'perm-' + permission.id" :checked="isSelected(permission.id)" @click="togglePermission(permission.id)" />
                        <label :for="'perm-' + permission.id" class="flex-1 cursor-pointer text-sm leading-none font-medium">
                            {{ permission.sidebar_name || permission.name }}
                        </label>
                    </div>
                </div>

                <DialogFooter class="flex gap-2 pt-8">
                    <Button type="button" variant="ghost" @click="$emit('update:open', false)" class="border">Cancelar</Button>
                    <Button
                        @click="submit"
                        :disabled="form.processing"
                        class="flex-1 bg-orange-600 font-semibold text-white shadow-md hover:bg-orange-700"
                    >
                        <Icon v-if="form.processing" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
                        <Icon v-else name="save" class="mr-2 h-4 w-4" />
                        Sincronizar Permisos
                    </Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #e2e8f0;
    border-radius: 20px;
}
</style>
