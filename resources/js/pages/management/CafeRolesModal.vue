<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/vue3';
import { CheckCircle2, Search, ShieldCheck, UserCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    cafe: any;
    roles: any[];
    open: boolean;
}>();

const emit = defineEmits(['update:open']);

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

const roleSearchQuery = ref('');
const selectedRoleIds = ref<number[]>([]);

// Sincronizar roles seleccionados con el café cuando se abre el modal
watch(
    () => props.open,
    (newVal) => {
        if (newVal && props.cafe) {
            selectedRoleIds.value = props.cafe.roles ? props.cafe.roles.map((r: any) => r.id) : [];
            roleSearchQuery.value = '';
        }
    },
);

const filteredRoles = computed(() => {
    if (!roleSearchQuery.value) return props.roles;
    return props.roles.filter((role) => role.name.toLowerCase().includes(roleSearchQuery.value.toLowerCase()));
});

const toggleRole = (roleId: number) => {
    const index = selectedRoleIds.value.indexOf(roleId);
    if (index === -1) {
        selectedRoleIds.value.push(roleId);
    } else {
        selectedRoleIds.value.splice(index, 1);
    }
};

const handleSave = () => {
    if (!props.cafe) return;

    router.post(
        route('cafes.roles.sync', props.cafe.id),
        {
            role_ids: selectedRoleIds.value,
        },
        {
            onSuccess: () => {
                isOpen.value = false;
            },
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="overflow-hidden rounded-3xl border-none p-0 shadow-2xl sm:max-w-[500px]">
            <DialogHeader class="bg-slate-900 p-8 text-white">
                <DialogTitle class="flex items-center gap-3 text-2xl font-black">
                    <ShieldCheck class="h-6 w-6 text-amber-500" />
                    Gestionar Roles
                </DialogTitle>
                <DialogDescription class="font-medium text-slate-400">
                    Configura qué roles están habilitados para:
                    <span class="font-black text-white underline decoration-amber-500 underline-offset-4">
                        {{ cafe?.name }}
                    </span>
                </DialogDescription>
            </DialogHeader>

            <div class="p-8 pb-0">
                <div class="relative">
                    <Search class="absolute top-3 left-3 h-4 w-4 text-slate-400" />
                    <Input
                        v-model="roleSearchQuery"
                        placeholder="Buscar rol..."
                        class="h-10 rounded-xl border-slate-100 bg-slate-50 pl-10 focus:ring-amber-500"
                    />
                </div>
            </div>

            <div class="custom-scrollbar max-h-[50vh] space-y-4 overflow-y-auto p-8">
                <p class="mb-4 text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase">Roles Disponibles en el Sistema</p>

                <div class="grid grid-cols-1 gap-2">
                    <button
                        v-for="role in filteredRoles"
                        :key="role.id"
                        @click="toggleRole(role.id)"
                        class="group flex w-full items-center justify-between rounded-2xl border p-4 text-left transition-all duration-200"
                        :class="
                            selectedRoleIds.includes(role.id)
                                ? 'border-indigo-200 bg-indigo-50 ring-2 ring-indigo-600/10'
                                : 'border-slate-100 bg-white hover:border-slate-300'
                        "
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl transition-colors"
                                :class="
                                    selectedRoleIds.includes(role.id)
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-slate-50 text-slate-400 group-hover:bg-slate-100'
                                "
                            >
                                <UserCircle class="h-5 w-5" />
                            </div>
                            <span
                                class="text-sm font-bold tracking-tight"
                                :class="selectedRoleIds.includes(role.id) ? 'text-indigo-900' : 'text-slate-600'"
                            >
                                {{ role.name }}
                            </span>
                        </div>

                        <CheckCircle2
                            class="h-5 w-5 transition-all duration-300"
                            :class="
                                selectedRoleIds.includes(role.id)
                                    ? 'scale-110 text-indigo-600 opacity-100'
                                    : 'text-slate-100 opacity-0 group-hover:opacity-100'
                            "
                        />
                    </button>
                </div>

                <div v-if="filteredRoles.length === 0" class="rounded-2xl border-2 border-dashed border-slate-100 bg-slate-50 py-12 text-center">
                    <UserCircle class="mx-auto mb-2 h-10 w-10 text-slate-200" />
                    <p class="text-xs font-bold text-slate-400">No se encontraron roles con ese nombre.</p>
                </div>
            </div>

            <DialogFooter class="flex flex-row items-center gap-2 border-t border-slate-100 bg-slate-50 p-8 sm:justify-between">
                <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">{{ selectedRoleIds.length }} seleccionados</p>
                <div class="flex gap-3">
                    <Button
                        variant="ghost"
                        @click="isOpen = false"
                        class="rounded-xl text-[10px] font-bold tracking-widest text-slate-500 uppercase hover:bg-slate-100"
                    >
                        Cancelar
                    </Button>
                    <Button
                        @click="handleSave"
                        class="rounded-xl bg-slate-900 px-8 text-[10px] font-black tracking-widest text-white uppercase shadow-lg shadow-slate-200 hover:bg-black"
                    >
                        Guardar Cambios
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
