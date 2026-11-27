<script setup lang="ts">
import PageHeader from '@/components/shared/PageHeader.vue';
import StatCard from '@/components/shared/StatCard.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { Area, Permission, Role, User } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Building2, ChevronRight, KeyRound, Search, ShieldCheck, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import RoleModal from '../headcount/RoleModal.vue';
import RolePermissionsPanel from './RolePermissionsPanel.vue';

interface RoleWithPermissions extends Role {
    permissions?: Permission[];
    area?: string;
}

interface Props {
    users: User[];
    roles: RoleWithPermissions[];
    areas: Area[];
    permissions: Permission[];
}

const props = defineProps<Props>();

const selectedRole = ref<RoleWithPermissions | null>(null);
const searchQuery = ref('');

const selectRole = (role: RoleWithPermissions) => {
    selectedRole.value = selectedRole.value?.id === role.id ? null : role;
};

const filteredRoles = computed(() => {
    if (!searchQuery.value.trim()) return props.roles;
    const q = searchQuery.value.toLowerCase();
    return props.roles.filter((r) => {
        const nameMatch = r.name.toLowerCase().includes(q);
        const areaMatch = r.area?.toLowerCase().includes(q);
        return nameMatch || areaMatch;
    });
});
</script>

<template>
    <Head title="Roles y Permisos" />
    <AppLayout>
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header Unificado -->
            <PageHeader
                title="Roles y Permisos"
                subtitle="Configuración de perfiles de usuario, niveles de autorización y permisos por área"
                badge="Seguridad & Acceso"
            >
                <template #actions>
                    <RoleModal :areas="areas" />
                </template>
            </PageHeader>

            <!-- Métricas Clave -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    title="Roles del Sistema"
                    :value="roles.length"
                    subtitle="Perfiles activos configurados"
                    color="primary"
                >
                    <template #icon>
                        <ShieldCheck class="h-5 w-5" />
                    </template>
                </StatCard>
                <StatCard
                    title="Permisos en Catálogo"
                    :value="permissions.length"
                    subtitle="Capacidades y accesos del sistema"
                    color="purple"
                >
                    <template #icon>
                        <KeyRound class="h-5 w-5" />
                    </template>
                </StatCard>
                <StatCard
                    title="Áreas con Asignación"
                    :value="areas.length"
                    subtitle="Departamentos estructurados"
                    color="info"
                >
                    <template #icon>
                        <Building2 class="h-5 w-5" />
                    </template>
                </StatCard>
            </div>

            <!-- Contenido Principal: Split view -->
            <div class="flex flex-col lg:flex-row gap-6 items-start">
                <!-- Columna Izquierda: Tabla y Filtro -->
                <div class="flex flex-col gap-4 flex-1 w-full min-w-0">
                    <!-- Buscador -->
                    <Card class="border-border/60 shadow-xs">
                        <CardContent class="p-3">
                            <div class="relative w-full">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Buscar rol por nombre o área..."
                                    class="pl-9 pr-8 h-9"
                                />
                                <button
                                    v-if="searchQuery"
                                    @click="searchQuery = ''"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Tabla de Roles -->
                    <Card class="overflow-hidden border-border/60 shadow-xs">
                        <div class="overflow-x-auto">
                            <Table>
                                <TableHeader class="bg-muted/40">
                                    <TableRow>
                                        <TableHead class="font-semibold">Perfil / Rol</TableHead>
                                        <TableHead class="font-semibold">Área Asignada</TableHead>
                                        <TableHead class="font-semibold">Permisos Asignados</TableHead>
                                        <TableHead class="font-semibold">Estado</TableHead>
                                        <TableHead class="text-right w-12" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="role in filteredRoles"
                                        :key="role.id"
                                        class="cursor-pointer transition-colors"
                                        :class="selectedRole?.id === role.id ? 'bg-red-50/70 dark:bg-red-950/20 font-medium' : 'hover:bg-muted/30'"
                                        @click="selectRole(role)"
                                    >
                                        <TableCell class="font-semibold text-foreground">
                                            <div class="flex items-center gap-2.5">
                                                <div
                                                    class="h-2 w-2 rounded-full"
                                                    :class="selectedRole?.id === role.id ? 'bg-red-600 ring-4 ring-red-100' : 'bg-muted-foreground/40'"
                                                />
                                                <span>{{ role.name }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-muted-foreground text-sm">
                                            <Badge v-if="role.area" variant="outline" class="font-normal text-xs">
                                                {{ role.area }}
                                            </Badge>
                                            <span v-else class="text-muted-foreground/60 text-xs italic">—</span>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary" class="font-normal text-xs">
                                                {{ role.permissions?.length ?? 0 }} permisos
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                                                Activo
                                            </span>
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <ChevronRight
                                                class="ml-auto h-4 w-4 text-muted-foreground transition-transform duration-200"
                                                :class="selectedRole?.id === role.id ? 'rotate-90 text-red-600' : ''"
                                            />
                                        </TableCell>
                                    </TableRow>

                                    <TableRow v-if="filteredRoles.length === 0">
                                        <TableCell colspan="5" class="p-8">
                                            <EmptyState
                                                title="No se encontraron roles"
                                                description="No hay perfiles que coincidan con la búsqueda actual."
                                            >
                                                <template #icon>
                                                    <ShieldCheck class="h-7 w-7 text-muted-foreground" />
                                                </template>
                                            </EmptyState>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </Card>
                </div>

                <!-- Columna Derecha: Panel de Permisos -->
                <Transition
                    enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="opacity-0 translate-x-6"
                    enter-to-class="opacity-100 translate-x-0"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="opacity-100 translate-x-0"
                    leave-to-class="opacity-0 translate-x-6"
                >
                    <div v-if="selectedRole" class="w-full lg:w-96 shrink-0">
                        <RolePermissionsPanel
                            :role="selectedRole"
                            :permissions="permissions"
                            @close="selectedRole = null"
                        />
                    </div>
                </Transition>
            </div>
        </div>
    </AppLayout>
</template>
