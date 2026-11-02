<script lang="ts" setup>
import EmptyState from '@/components/shared/EmptyState.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Building2, MapPin, Pencil, Plus, Search, Shield, ShieldCheck, Trash2, Users, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PermissionModal from './PermissionModal.vue';
import UserModal from './UserModal.vue';

interface Mine {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    roles: { id: number; name: string; permissions: Permission[] }[];
    areas: { id: number; name: string; pivot?: { role_id: number; area_id: number } }[];
    units: { id: number; name: string }[];
    permissions: { id: number; name: string; sidebar_name?: string | null }[];
    mine: Mine | null;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedUsers {
    data: User[];
    links: PaginationLinks[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    from: number;
    to: number;
}

interface Role {
    id: number;
    name: string;
}

interface Area {
    id: number;
    name: string;
}

interface Unit {
    id: number;
    name: string;
}

interface Business {
    id: number;
    name: string;
}

interface Permission {
    id: number;
    name: string;
    sidebar_name?: string | null;
}

const props = defineProps<{
    users: PaginatedUsers;
    roles: Role[];
    areas: Area[];
    units: Unit[];
    mines: Mine[];
    businesses: Business[];
    permissions: Permission[];
}>();

const breadcrumbs = [{ title: 'Usuarios', href: '/users' }];

const isModalOpen = ref(false);
const isPermissionModalOpen = ref(false);
const editingUser = ref<User | null>(null);
const searchQuery = ref('');

const filteredUsers = computed(() => {
    if (!searchQuery.value.trim()) return props.users.data;
    const q = searchQuery.value.toLowerCase();
    return props.users.data.filter((u) => {
        const nameMatch = u.name.toLowerCase().includes(q);
        const emailMatch = u.email.toLowerCase().includes(q);
        const roleMatch = u.roles?.some((r) => r.name.toLowerCase().includes(q));
        const areaMatch = u.areas?.some((a) => a.name.toLowerCase().includes(q));
        const unitMatch = u.units?.some((un) => un.name.toLowerCase().includes(q));
        return nameMatch || emailMatch || roleMatch || areaMatch || unitMatch;
    });
});

const form = useForm({});

// Accessing route globally from Ziggy
declare const route: any;

const openCreateModal = () => {
    editingUser.value = null;
    isModalOpen.value = true;
};

const openEditModal = (user: User) => {
    editingUser.value = user;
    isModalOpen.value = true;
};

const openPermissionModal = (user: User) => {
    editingUser.value = user;
    isPermissionModalOpen.value = true;
};

const deleteUser = (id: number) => {
    if (confirm('¿Estás seguro de que deseas eliminar este usuario?')) {
        form.delete(route('users.destroy', id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Usuarios" />

        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header Unificado -->
            <PageHeader
                title="Usuarios del Sistema"
                subtitle="Administra cuentas de acceso, roles, áreas operativas y unidades asignadas"
                badge="Control de Acceso"
            >
                <template #actions>
                    <Button @click="openCreateModal" class="gap-2 shadow-xs">
                        <Plus class="h-4 w-4" />
                        <span>Nuevo Usuario</span>
                    </Button>
                </template>
            </PageHeader>

            <!-- Métricas Clave -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    title="Total Usuarios"
                    :value="users.total || 0"
                    subtitle="Cuentas registradas"
                    color="primary"
                >
                    <template #icon>
                        <Users class="h-5 w-5" />
                    </template>
                </StatCard>
                <StatCard
                    title="Roles Configurados"
                    :value="roles.length"
                    subtitle="Perfiles de autorización"
                    color="purple"
                >
                    <template #icon>
                        <ShieldCheck class="h-5 w-5" />
                    </template>
                </StatCard>
                <StatCard
                    title="Áreas Operativas"
                    :value="areas.length"
                    subtitle="Departamentos asignables"
                    color="info"
                >
                    <template #icon>
                        <Building2 class="h-5 w-5" />
                    </template>
                </StatCard>
                <StatCard
                    title="Unidades / Sedes"
                    :value="units.length"
                    subtitle="Centros de atención"
                    color="success"
                >
                    <template #icon>
                        <MapPin class="h-5 w-5" />
                    </template>
                </StatCard>
            </div>

            <!-- Filtros Rápidos -->
            <Card class="border-border/60 shadow-xs">
                <CardContent class="p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="relative w-full sm:max-w-md">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Filtrar por nombre, correo, rol o área..."
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
                        <p class="text-xs text-muted-foreground">
                            Mostrando {{ filteredUsers.length }} de {{ users.total }} usuarios
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Tabla de Usuarios -->
            <Card class="overflow-hidden border-border/60 shadow-xs">
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader class="bg-muted/40">
                            <TableRow>
                                <TableHead class="w-[280px] font-semibold">Usuario</TableHead>
                                <TableHead class="font-semibold">Correo Electrónico</TableHead>
                                <TableHead class="font-semibold">Rol & Área</TableHead>
                                <TableHead class="font-semibold">Unidades Asignadas</TableHead>
                                <TableHead class="text-right font-semibold">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="user in filteredUsers"
                                :key="user.id"
                                class="hover:bg-muted/30 transition-colors"
                            >
                                <TableCell class="font-medium">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="bg-red-50 border-red-200 text-red-700 dark:bg-red-950/40 dark:border-red-900 dark:text-red-300 flex h-9 w-9 items-center justify-center rounded-lg border text-sm font-bold shrink-0"
                                        >
                                            {{ user.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-sm font-semibold text-foreground truncate">{{ user.name }}</span>
                                            <span class="text-muted-foreground text-[10px] font-mono tracking-wider">ID #{{ user.id }}</span>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="text-muted-foreground text-sm font-mono">
                                    {{ user.email }}
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex flex-wrap gap-1">
                                            <Badge
                                                v-for="role in user.roles"
                                                :key="role.id"
                                                variant="secondary"
                                                class="h-5 px-2 text-[11px] font-medium"
                                            >
                                                {{ role.name }}
                                            </Badge>
                                        </div>
                                        <div class="flex flex-wrap gap-1">
                                            <Badge
                                                v-for="area in user.areas"
                                                :key="area.id"
                                                variant="outline"
                                                class="border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300 h-5 px-2 text-[11px] font-normal"
                                            >
                                                {{ area.name }}
                                            </Badge>
                                        </div>
                                        <span
                                            v-if="user.roles.length === 0 && user.areas.length === 0"
                                            class="text-muted-foreground text-xs italic"
                                        >
                                            Sin rol asignado
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex max-w-[220px] flex-wrap gap-1">
                                        <Badge
                                            v-for="unit in user.units"
                                            :key="unit.id"
                                            variant="outline"
                                            class="h-5 border-amber-200 bg-amber-50 px-2 text-[11px] font-normal text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
                                        >
                                            {{ unit.name }}
                                        </Badge>
                                        <span v-if="user.units.length === 0" class="text-muted-foreground text-xs italic">
                                            Todas / Sin asignar
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="openEditModal(user)"
                                            title="Editar Usuario"
                                            class="h-8 w-8 text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="openPermissionModal(user)"
                                            title="Permisos Específicos"
                                            class="h-8 w-8 text-amber-600 hover:bg-amber-50 hover:text-amber-700"
                                        >
                                            <Shield class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10"
                                            @click="deleteUser(user.id)"
                                            title="Eliminar Usuario"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>

                            <TableRow v-if="filteredUsers.length === 0">
                                <TableCell colspan="5" class="p-8">
                                    <EmptyState
                                        title="No se encontraron usuarios"
                                        description="No hay cuentas que coincidan con el término de búsqueda actual."
                                    >
                                        <template #icon>
                                            <Users class="h-7 w-7 text-muted-foreground" />
                                        </template>
                                        <template #action>
                                            <Button
                                                v-if="searchQuery"
                                                variant="outline"
                                                size="sm"
                                                @click="searchQuery = ''"
                                            >
                                                Limpiar Filtro
                                            </Button>
                                            <Button
                                                v-else
                                                size="sm"
                                                @click="openCreateModal"
                                            >
                                                <Plus class="h-4 w-4 mr-1.5" />
                                                Registrar Usuario
                                            </Button>
                                        </template>
                                    </EmptyState>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Footer de Paginación -->
                <div
                    v-if="users.total > users.per_page"
                    class="flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-border/60 bg-muted/10 px-4 py-3"
                >
                    <p class="text-xs text-muted-foreground order-2 sm:order-1">
                        Mostrando <span class="font-medium text-foreground">{{ users.from }}</span> a
                        <span class="font-medium text-foreground">{{ users.to }}</span> de
                        <span class="font-medium text-foreground">{{ users.total }}</span> resultados
                    </p>
                    <div class="flex items-center gap-1 order-1 sm:order-2 flex-wrap justify-center">
                        <template v-for="(link, k) in users.links" :key="k">
                            <span
                                v-if="link.url === null"
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2.5 text-xs text-muted-foreground/40 cursor-not-allowed select-none"
                                v-html="link.label"
                            />
                            <Link
                                v-else
                                :href="link.url"
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2.5 text-xs font-medium transition-colors"
                                :class="[
                                    link.active
                                        ? 'bg-primary text-primary-foreground font-semibold shadow-xs'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                ]"
                                v-html="link.label"
                                preserve-scroll
                            />
                        </template>
                    </div>
                </div>
            </Card>
        </div>

        <UserModal
            v-model:open="isModalOpen"
            :user="editingUser"
            :roles="roles"
            :areas="areas"
            :units="units"
            :mines="mines"
            :businesses="businesses"
        />

        <PermissionModal
            v-model:open="isPermissionModalOpen"
            :user="editingUser"
            :permissions="permissions"
        />
    </AppLayout>
</template>
