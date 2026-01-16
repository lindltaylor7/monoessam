<script lang="ts" setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import Icon from '@/components/Icon.vue';
import UserModal from './UserModal.vue';
import PermissionModal from './PermissionModal.vue';
import { Shield } from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
    email: string;
    roles: { id: number; name: string; permissions: Permission[] }[];
    areas: { id: number; name: string; pivot?: { role_id: number; area_id: number } }[];
    units: { id: number; name: string }[];
    permissions: { id: number; name: string; sidebar_name?: string | null }[];
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
    permissions: Permission[];
}>();

const breadcrumbs = [
    { title: 'Usuarios', href: '/users' },
];

const isModalOpen = ref(false);
const isPermissionModalOpen = ref(false);
const editingUser = ref<User | null>(null);

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
            preserveScroll: true
        });
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Usuarios" />

        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Usuarios</h1>
                    <p class="text-muted-foreground text-sm">Administra los usuarios del sistema, sus roles, áreas y unidades.</p>
                </div>
                <Button @click="openCreateModal">
                    <Icon name="plus" class="mr-2 h-4 w-4" />
                    Nuevo Usuario
                </Button>
            </div>

            <div class="border bg-card overflow-hidden rounded-xl shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow class="bg-muted/30">
                            <TableHead class="w-[280px]">Nombre</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Rol / Área</TableHead>
                            <TableHead>Unidades</TableHead>
                            <TableHead class="text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="user in users.data" :key="user.id" class="hover:bg-muted/50 transition-colors">
                            <TableCell class="font-medium">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center text-primary font-semibold text-sm">
                                        {{ user.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-semibold">{{ user.name }}</span>
                                        <span class="text-[10px] text-muted-foreground uppercase tracking-wider font-medium">ID: {{ user.id }}</span>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">{{ user.email }}</TableCell>
                            <TableCell>
                                <div class="flex flex-col gap-1">
                                    <div class="flex flex-wrap gap-1">
                                        <Badge v-for="role in user.roles" :key="role.id" variant="secondary" class="font-normal px-2 py-0 h-5">
                                            {{ role.name }}
                                        </Badge>
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <Badge v-for="area in user.areas" :key="area.id" variant="outline" class="font-normal border-primary/20 bg-primary/5 text-primary px-2 py-0 h-5">
                                            {{ area.name }}
                                        </Badge>
                                    </div>
                                    <span v-if="user.roles.length === 0 && user.areas.length === 0" class="text-muted-foreground text-xs italic">Sin asignar</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-wrap gap-1 max-w-[200px]">
                                    <Badge v-for="unit in user.units" :key="unit.id" variant="outline" class="font-normal bg-orange-50 text-orange-700 border-orange-200 px-2 py-0 h-5">
                                        {{ unit.name }}
                                    </Badge>
                                    <span v-if="user.units.length === 0" class="text-muted-foreground text-xs italic">Sin unidades</span>
                                </div>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" @click="openEditModal(user)" title="Editar" class="h-8 w-8 text-primary hover:text-primary hover:bg-primary/10">
                                        <Icon name="pencil" class="h-4 w-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon" @click="openPermissionModal(user)" title="Permisos" class="h-8 w-8 text-orange-600 hover:text-orange-600 hover:bg-orange-50">
                                        <Shield class="h-4 w-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10" @click="deleteUser(user.id)" title="Eliminar">
                                        <Icon name="trash" class="h-4 w-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="users.data.length === 0">
                            <TableCell colspan="5" class="h-32 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <Icon name="users" class="h-8 w-8 opacity-20" />
                                    <p class="text-sm">No se encontraron usuarios registrados.</p>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Pagination Footer -->
                <div v-if="users.total > users.per_page" class="flex items-center justify-between px-6 py-4 border-t bg-muted/20">
                    <div class="text-sm text-muted-foreground text-xs">
                        Mostrando <span class="font-medium">{{ users.from }}</span> a <span class="font-medium">{{ users.to }}</span> de <span class="font-medium">{{ users.total }}</span> resultados
                    </div>
                    <div class="flex items-center gap-1">
                        <template v-for="(link, k) in users.links" :key="k">
                            <div v-if="link.url === null" class="h-8 min-w-[32px] px-2 flex items-center justify-center text-xs text-muted-foreground/50 rounded-md border border-transparent" v-html="link.label" />
                            <Link v-else :href="link.url" class="h-8 min-w-[32px] px-2 flex items-center justify-center text-xs rounded-md border transition-all" :class="{ 'bg-primary text-primary-foreground border-primary shadow-sm font-bold': link.active, 'bg-background hover:bg-muted border-muted-foreground/20 text-muted-foreground': !link.active }" v-html="link.label" preserve-scroll />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <UserModal 
            v-model:open="isModalOpen"
            :user="editingUser"
            :roles="roles"
            :areas="areas"
            :units="units"
        />

        <PermissionModal 
            v-model:open="isPermissionModalOpen"
            :user="editingUser"
            :permissions="permissions"
        />
    </AppLayout>
</template>