<script lang="ts" setup>
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { ref, watch } from 'vue';

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

const activeIds = ref<number[]>([]);

const form = useForm({
    permissions: [] as number[],
    user_id: null as number | null,
});

declare const route: any;

watch(
    () => props.open,
    (newVal) => {
        if (newVal && props.user) {
            form.user_id = props.user.id;

            const directIds = props.user.permissions.map((p) => p.id);
            const roleIds = props.user.roles.flatMap((role) => role.permissions.map((p) => p.id));
            activeIds.value = [...new Set([...directIds, ...roleIds])];
        }
    },
);

const isSelected = (id: number) => activeIds.value.includes(id);

const togglePermission = (id: number) => {
    const idx = activeIds.value.indexOf(id);
    if (idx > -1) activeIds.value.splice(idx, 1);
    else activeIds.value.push(id);
};

const submit = () => {
    if (!props.user) return;
    form.permissions = [...activeIds.value];
    form.post(route('permissions.user.update', props.user.id), {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="flex max-h-[85vh] flex-col gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-[500px]">

            <!-- Header naranja -->
            <div class="relative bg-orange-600 px-6 py-6 text-white">
                <DialogHeader>
                    <DialogTitle class="text-xl font-bold">Permisos de Usuario</DialogTitle>
                    <DialogDescription class="text-orange-100">
                        Gestiona los permisos para
                        <span class="font-bold text-white">{{ user?.name }}</span>.
                    </DialogDescription>
                </DialogHeader>
                <div class="bg-background absolute right-6 -bottom-5 flex h-10 w-10 items-center justify-center rounded-full border shadow-lg">
                    <ShieldCheck class="h-5 w-5 text-orange-600" />
                </div>
            </div>

            <!-- Info bar -->
            <div class="border-b bg-orange-50 px-6 py-2.5 dark:bg-orange-950/20">
                <p class="text-xs text-orange-600 dark:text-orange-400">
                    <span class="font-semibold">{{ activeIds.length }}</span> de
                    <span class="font-semibold">{{ permissions.length }}</span> permisos activos
                </p>
            </div>

            <!-- Lista de permisos -->
            <div class="custom-scrollbar flex-1 divide-y overflow-y-auto">
                <div
                    v-for="permission in permissions"
                    :key="permission.id"
                    class="flex cursor-pointer items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-muted/40"
                    @click="togglePermission(permission.id)"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-colors"
                            :class="isSelected(permission.id) ? 'bg-orange-100 dark:bg-orange-950' : 'bg-muted'"
                        >
                            <ShieldCheck
                                class="h-4 w-4 transition-colors"
                                :class="isSelected(permission.id) ? 'text-orange-500' : 'text-muted-foreground'"
                            />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium leading-snug">
                                {{ permission.sidebar_name || permission.name }}
                            </p>
                            <p v-if="permission.sidebar_name" class="text-muted-foreground truncate text-xs leading-snug">
                                {{ permission.name }}
                            </p>
                        </div>
                    </div>

                    <!-- Toggle nativo -->
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="isSelected(permission.id)"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2"
                        :class="isSelected(permission.id) ? 'bg-orange-500' : 'bg-input dark:bg-input/80'"
                        @click.stop="togglePermission(permission.id)"
                    >
                        <span
                            class="pointer-events-none block h-5 w-5 rounded-full bg-white shadow-md ring-0 transition-transform duration-200"
                            :class="isSelected(permission.id) ? 'translate-x-5' : 'translate-x-0'"
                        />
                    </button>
                </div>
            </div>

            <!-- Footer -->
            <DialogFooter class="border-t px-6 py-4">
                <div class="flex w-full gap-2">
                    <Button type="button" variant="ghost" class="border" @click="$emit('update:open', false)">
                        Cancelar
                    </Button>
                    <Button
                        class="flex-1 bg-orange-600 font-semibold text-white shadow-md hover:bg-orange-700"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        <Icon v-if="form.processing" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
                        <Icon v-else name="save" class="mr-2 h-4 w-4" />
                        Sincronizar Permisos
                    </Button>
                </div>
            </DialogFooter>
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
