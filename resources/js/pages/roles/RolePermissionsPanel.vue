<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Permission, Role } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { ShieldCheck, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface RoleWithPermissions extends Role {
    permissions?: Permission[];
}

interface Props {
    permissions: Permission[];
    role: RoleWithPermissions;
}

const props = defineProps<Props>();
const emit = defineEmits<{ close: [] }>();

// Ref separado para reactividad granular de includes()
const activeIds = ref<number[]>(props.role.permissions?.map((p: Permission) => p.id) ?? []);

const form = useForm({ role_id: props.role.id, permissions: [] as number[] });

watch(
    () => props.role,
    (newRole) => {
        activeIds.value = newRole.permissions?.map((p: Permission) => p.id) ?? [];
        form.role_id = newRole.id;
    },
);

const isEnabled = (id: number) => activeIds.value.includes(id);

const toggle = (id: number) => {
    const idx = activeIds.value.indexOf(id);
    if (idx === -1) activeIds.value.push(id);
    else activeIds.value.splice(idx, 1);
};

const submit = () => {
    form.permissions = [...activeIds.value];
    form.post(route('roles.permissions'));
};
</script>

<template>
    <div class="bg-background flex w-[380px] shrink-0 flex-col overflow-hidden rounded-xl border shadow-sm">
        <!-- Header -->
        <div class="flex items-start justify-between border-b px-5 py-4">
            <div>
                <h2 class="text-base font-semibold">Permisos del Rol</h2>
                <span class="mt-1.5 inline-block rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                    {{ role.name }}
                </span>
            </div>
            <button
                class="hover:bg-muted rounded-lg p-1.5 transition-colors"
                @click="emit('close')"
            >
                <X class="text-muted-foreground h-4 w-4" />
            </button>
        </div>

        <!-- Info banner -->
        <div class="border-b bg-blue-50 px-5 py-2.5 dark:bg-blue-950/40">
            <p class="text-xs text-blue-600 dark:text-blue-400">
                Activa o desactiva los permisos para este rol.
            </p>
        </div>

        <!-- Permissions list -->
        <div class="divide-border flex-1 divide-y overflow-y-auto">
            <div
                v-for="permission in permissions"
                :key="permission.id"
                class="hover:bg-muted/40 flex items-center justify-between gap-3 px-5 py-3.5 transition-colors"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-colors"
                        :class="isEnabled(permission.id) ? 'bg-blue-100 dark:bg-blue-950' : 'bg-muted'"
                    >
                        <ShieldCheck
                            class="h-4 w-4 transition-colors"
                            :class="isEnabled(permission.id) ? 'text-blue-500' : 'text-muted-foreground'"
                        />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium leading-snug">{{ permission.name }}</p>
                        <p class="text-muted-foreground truncate text-xs leading-snug">
                            {{ permission.sidebar_name ?? permission.route_name ?? 'Permiso del sistema' }}
                        </p>
                    </div>
                </div>

                <!-- Toggle nativo para evitar problemas de reactividad con reka-ui -->
                <button
                    type="button"
                    role="switch"
                    :aria-checked="isEnabled(permission.id)"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    :class="isEnabled(permission.id) ? 'bg-blue-500' : 'bg-input dark:bg-input/80'"
                    @click="toggle(permission.id)"
                >
                    <span
                        class="pointer-events-none block h-5 w-5 rounded-full bg-white shadow-md ring-0 transition-transform duration-200"
                        :class="isEnabled(permission.id) ? 'translate-x-5' : 'translate-x-0'"
                    />
                </button>
            </div>
        </div>

        <!-- Footer -->
        <div class="border-t px-5 py-4">
            <div class="flex items-center justify-between gap-3">
                <span class="text-muted-foreground text-xs">
                    {{ activeIds.length }} de {{ permissions.length }} activos
                </span>
                <Button
                    class="bg-blue-500 text-white hover:bg-blue-600"
                    :disabled="form.processing"
                    @click="submit"
                >
                    Guardar cambios
                </Button>
            </div>
        </div>
    </div>
</template>
