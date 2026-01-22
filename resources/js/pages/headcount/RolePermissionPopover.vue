<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Permission, Role } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    role: Role;
    permissions: Permission[];
}>();

const popoverOpen = ref(false);
const selectedPermissions = ref<Record<number, boolean>>({});

// Inicializar los permisos seleccionados basados en el rol actual
watch(
    () => props.role,
    (role) => {
        selectedPermissions.value = {};
        if (role.permissions) {
            role.permissions.forEach((permission) => {
                selectedPermissions.value[permission.id] = true;
            });
        }
    },
    { immediate: true },
);

const form = useForm({
    roleId: 0,
    permissions: {} as Record<number, boolean>,
});

function onPermissionChange(id: number, checked: boolean) {
    selectedPermissions.value[id] = checked;
}

const sendPermissions = () => {
    popoverOpen.value = false;
    form.roleId = props.role.id;
    form.permissions = selectedPermissions.value;

    form.post(route('role-permissions'), {
        onSuccess: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <Popover v-model:open="popoverOpen">
        <PopoverTrigger asChild>
            <Button variant="outline" class="flex items-center gap-2 bg-yellow-400 text-white"> Ver permisos </Button>
        </PopoverTrigger>
        <PopoverContent class="w-64 space-y-3 p-4">
            <div class="max-h-64 space-y-2 overflow-y-auto">
                <div
                    v-for="permission in permissions"
                    :key="permission.id"
                    class="flex items-center space-x-2 rounded p-2 transition-colors hover:bg-gray-50"
                >
                    <input
                        class="text-primary focus:ring-primary h-4 w-4 rounded border-gray-300"
                        type="checkbox"
                        :id="`permission-${permission.id}`"
                        :checked="selectedPermissions[permission.id] || false"
                        @change="onPermissionChange(permission.id, $event.target.checked)"
                    />
                    <label class="cursor-pointer text-sm font-medium text-gray-700" :for="`permission-${permission.id}`">
                        {{ permission.name }}
                    </label>
                </div>
            </div>

            <Button @click="sendPermissions" class="mt-2 w-full"> Sincronizar </Button>
        </PopoverContent>
    </Popover>
</template>
