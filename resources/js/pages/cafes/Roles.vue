<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Building2, CheckCircle2, Coffee, Search, Settings2, ShieldCheck, UserCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    cafes: any[];
    roles: any[];
}

const props = defineProps<Props>();

const searchQuery = ref('');
const roleSearchQuery = ref('');
const isModalOpen = ref(false);
const selectedCafe = ref<any>(null);
const selectedRoleIds = ref<number[]>([]);

const filteredRoles = computed(() => {
    if (!roleSearchQuery.value) return props.roles;
    return props.roles.filter((role) => role.name.toLowerCase().includes(roleSearchQuery.value.toLowerCase()));
});

const filteredCafes = computed(() => {
    if (!searchQuery.value) return props.cafes;
    return props.cafes.filter((cafe) => cafe.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

const openEditModal = (cafe: any) => {
    selectedCafe.value = cafe;
    selectedRoleIds.value = cafe.roles.map((r: any) => r.id);
    roleSearchQuery.value = '';
    isModalOpen.value = true;
};

const toggleRole = (roleId: number) => {
    const index = selectedRoleIds.value.indexOf(roleId);
    if (index === -1) {
        selectedRoleIds.value.push(roleId);
    } else {
        selectedRoleIds.value.splice(index, 1);
    }
};

const handleSave = () => {
    if (!selectedCafe.value) return;

    router.post(
        route('cafes.roles.sync', selectedCafe.value.id),
        {
            role_ids: selectedRoleIds.value,
        },
        {
            onSuccess: () => {
                isModalOpen.value = false;
            },
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Asignación de Roles por Café" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Configuración', href: '#' },
            { title: 'Cafés', href: route('cafes.roles.index') },
            { title: 'Roles', href: route('cafes.roles.index') },
        ]"
    >
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
            <!-- Header Section -->
            <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <h1 class="flex items-center gap-3 text-3xl font-black text-slate-900">
                        <div class="rounded-2xl bg-amber-100 p-2">
                            <Coffee class="h-8 w-8 text-amber-600" />
                        </div>
                        Roles por Café
                    </h1>
                    <p class="mt-2 font-medium text-slate-500">Define qué perfiles de usuario están activos en cada punto de servicio.</p>
                </div>

                <div class="relative w-full md:w-80">
                    <Search class="absolute top-3 left-3 h-4 w-4 text-slate-400" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Buscar café..."
                        class="h-11 rounded-xl border-slate-200 bg-white pl-10 shadow-sm focus:ring-amber-500"
                    />
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <Card class="relative overflow-hidden border-none bg-indigo-600 text-white shadow-xl shadow-indigo-100">
                    <div class="absolute -right-4 -bottom-4 opacity-10">
                        <Building2 class="h-32 w-32" />
                    </div>
                    <CardContent class="p-6">
                        <p class="mb-1 text-xs font-black tracking-widest text-indigo-100 uppercase">Total Cafés</p>
                        <p class="text-4xl font-black">{{ cafes.length }}</p>
                    </CardContent>
                </Card>

                <Card class="relative overflow-hidden border-none bg-amber-500 text-white shadow-xl shadow-amber-100">
                    <div class="absolute -right-4 -bottom-4 opacity-10">
                        <ShieldCheck class="h-32 w-32" />
                    </div>
                    <CardContent class="p-6">
                        <p class="mb-1 text-xs font-black tracking-widest text-amber-100 uppercase">Roles Disponibles</p>
                        <p class="text-4xl font-black">{{ roles.length }}</p>
                    </CardContent>
                </Card>

                <Card class="relative overflow-hidden border-none bg-slate-900 text-white shadow-xl">
                    <div class="absolute -right-4 -bottom-4 opacity-10">
                        <UserCircle class="h-32 w-32" />
                    </div>
                    <CardContent class="p-6">
                        <p class="mb-1 text-xs font-black tracking-widest text-slate-400 uppercase">Asignaciones Activas</p>
                        <p class="text-4xl font-black">
                            {{ cafes.reduce((acc, c) => acc + c.roles.length, 0) }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Cafes List -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <Card
                    v-for="cafe in filteredCafes"
                    :key="cafe.id"
                    class="group overflow-hidden rounded-3xl border-slate-100 bg-white/80 backdrop-blur-sm transition-all duration-300 hover:shadow-2xl hover:shadow-indigo-50/50"
                >
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 p-6">
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 transition-colors group-hover:bg-amber-50 group-hover:text-amber-600"
                            >
                                <Coffee class="h-6 w-6" />
                            </div>
                            <div>
                                <CardTitle class="flex flex-col text-lg font-black tracking-tight text-slate-900 uppercase tabular-nums">
                                    <span>{{ cafe.name }}</span>
                                    <span class="text-[10px] font-bold tracking-tighter text-slate-400">
                                        {{ cafe.unit?.mine?.name }} — {{ cafe.unit?.name }}
                                    </span>
                                </CardTitle>
                                <CardDescription class="mt-1 flex items-center gap-1 text-xs font-bold text-slate-400">
                                    <Badge variant="outline" class="border-slate-200 px-1.5 py-0 text-[10px] text-slate-400">ID: {{ cafe.id }}</Badge>
                                </CardDescription>
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="openEditModal(cafe)"
                            class="h-10 w-10 rounded-2xl p-0 text-slate-400 transition-all hover:bg-indigo-50 hover:text-indigo-600"
                        >
                            <Settings2 class="h-5 w-5" />
                        </Button>
                    </CardHeader>

                    <CardContent class="p-6 pt-0">
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="role in cafe.roles"
                                :key="role.id"
                                class="rounded-full border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700"
                            >
                                {{ role.name }}
                            </Badge>
                            <div v-if="cafe.roles.length === 0" class="text-xs text-slate-400 italic">Sin roles asignados aún.</div>
                        </div>
                    </CardContent>
                </Card>

                <div
                    v-if="filteredCafes.length === 0"
                    class="col-span-full rounded-3xl border-2 border-dashed border-slate-200 bg-slate-50 py-12 text-center"
                >
                    <Coffee class="mx-auto mb-2 h-12 w-12 text-slate-200" />
                    <p class="font-bold text-slate-400">No se encontraron cafés con ese nombre.</p>
                </div>
            </div>

            <!-- Edit Modal -->
            <Dialog v-model:open="isModalOpen">
                <DialogContent class="overflow-hidden rounded-3xl border-none p-0 shadow-2xl sm:max-w-[500px]">
                    <DialogHeader class="bg-slate-900 p-8 text-white">
                        <DialogTitle class="flex items-center gap-3 text-2xl font-black">
                            <Settings2 class="h-6 w-6 text-amber-500" />
                            Gestionar Roles
                        </DialogTitle>
                        <DialogDescription class="font-medium text-slate-400">
                            Configura qué roles están habilitados para:
                            <span class="font-black text-white underline decoration-amber-500 underline-offset-4">
                                {{ selectedCafe?.name }} ({{ selectedCafe?.unit?.mine?.name }})
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

                        <div
                            v-if="filteredRoles.length === 0"
                            class="rounded-2xl border-2 border-dashed border-slate-100 bg-slate-50 py-12 text-center"
                        >
                            <UserCircle class="mx-auto mb-2 h-10 w-10 text-slate-200" />
                            <p class="text-xs font-bold text-slate-400">No se encontraron roles con ese nombre.</p>
                        </div>
                    </div>

                    <DialogFooter class="flex flex-row items-center gap-2 border-t border-slate-100 bg-slate-50 p-8 sm:justify-between">
                        <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">{{ selectedRoleIds.length }} seleccionados</p>
                        <div class="flex gap-3">
                            <Button
                                variant="ghost"
                                @click="isModalOpen = false"
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
        </div>
    </AppLayout>
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
