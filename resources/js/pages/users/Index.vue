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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import Icon from '@/components/Icon.vue';

interface User {
    id: number;
    name: string;
    email: string;
    roles: { id: number; name: string }[];
    areas: { id: number; name: string; pivot?: { role_id: number; area_id: number } }[];
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

const props = defineProps<{
    users: PaginatedUsers;
    roles: Role[];
    areas: Area[];
}>();

const breadcrumbs = [
    { title: 'Usuarios', href: '/users' },
];

const isModalOpen = ref(false);
const editingUser = ref<User | null>(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    role_id: '',
    area_id: '',
});

// Accessing route globally from Ziggy
declare const route: any;

const openCreateModal = () => {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    isModalOpen.value = true;
};

const openEditModal = (user: User) => {
    editingUser.value = user;
    form.name = user.name;
    form.email = user.email;
    form.password = '';
    form.role_id = user.roles[0]?.id.toString() || '';
    form.area_id = user.areas[0]?.id.toString() || '';
    form.clearErrors();
    isModalOpen.value = true;
};

const submit = () => {
    if (editingUser.value) {
        form.put(route('users.update', editingUser.value.id), {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
            },
        });
    } else {
        form.post(route('users.store'), {
            onSuccess: () => {
                isModalOpen.value = false;
                form.reset();
            },
        });
    }
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
                    <p class="text-muted-foreground text-sm">Administra los usuarios del sistema, sus roles y áreas.</p>
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
                            <TableHead class="w-[300px]">Nombre</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Rol</TableHead>
                            <TableHead>Área</TableHead>
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
                                <div class="flex flex-wrap gap-1">
                                    <Badge v-for="role in user.roles" :key="role.id" variant="secondary" class="font-normal px-2 py-0 h-5">
                                        {{ role.name }}
                                    </Badge>
                                    <span v-if="user.roles.length === 0" class="text-muted-foreground text-xs italic">Sin rol</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-wrap gap-1">
                                    <Badge v-for="area in user.areas" :key="area.id" variant="outline" class="font-normal border-primary/20 bg-primary/5 text-primary px-2 py-0 h-5">
                                        {{ area.name }}
                                    </Badge>
                                    <span v-if="user.areas.length === 0" class="text-muted-foreground text-xs italic">Sin área</span>
                                </div>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" @click="openEditModal(user)" title="Editar" class="h-8 w-8">
                                        <Icon name="pencil" class="h-4 w-4 text-muted-foreground" />
                                    </Button>
                                    <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10" @click="deleteUser(user.id)" title="Eliminar">
                                        <Icon name="trash-2" class="h-4 w-4" />
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


        <Dialog v-model:open="isModalOpen">
            <DialogContent class="sm:max-w-[450px] overflow-hidden p-0 gap-0 border-none shadow-2xl">
                <div class="bg-primary px-6 py-8 text-primary-foreground relative">
                    <DialogHeader>
                        <DialogTitle class="text-2xl font-bold">{{ editingUser ? 'Editar Usuario' : 'Crear Usuario' }}</DialogTitle>
                        <DialogDescription class="text-primary-foreground/70">
                            {{ editingUser ? 'Actualiza la información y permisos del usuario.' : 'Ingresa los datos del nuevo usuario para el sistema.' }}
                        </DialogDescription>
                    </DialogHeader>
                    <div class="absolute -bottom-6 right-6 h-12 w-12 rounded-full bg-background flex items-center justify-center shadow-lg border">
                         <Icon :name="editingUser ? 'user-cog' : 'user-plus'" class="h-6 w-6 text-primary" />
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-4 px-6 pt-10 pb-6 bg-background">
                    <div class="grid gap-2">
                        <Label for="name" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Nombre Completo</Label>
                        <Input id="name" v-model="form.name" placeholder="Ej. Juan Pérez" :class="{'border-destructive shadow-sm': form.errors.name}" class="h-10 border-muted-foreground/20 focus-visible:ring-primary" />
                        <p v-if="form.errors.name" class="text-[0.7rem] font-semibold text-destructive uppercase tracking-tight">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="email" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Correo Electrónico</Label>
                        <Input id="email" type="email" v-model="form.email" placeholder="juan@empresa.com" :class="{'border-destructive shadow-sm': form.errors.email}" class="h-10 border-muted-foreground/20 focus-visible:ring-primary" />
                        <p v-if="form.errors.email" class="text-[0.7rem] font-semibold text-destructive uppercase tracking-tight">{{ form.errors.email }}</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="password" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                            Contraseña 
                            <span v-if="editingUser" class="text-muted-foreground/60 font-normal lowercase">(Dejar en blanco para no cambiar)</span>
                        </Label>
                        <Input id="password" type="password" v-model="form.password" :class="{'border-destructive shadow-sm': form.errors.password}" class="h-10 border-muted-foreground/20 focus-visible:ring-primary" />
                        <p v-if="form.errors.password" class="text-[0.7rem] font-semibold text-destructive uppercase tracking-tight">{{ form.errors.password }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="role_id" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Rol Asignado</Label>
                            <Select v-model="form.role_id">
                                <SelectTrigger :class="{'border-destructive shadow-sm': form.errors.role_id}" class="h-10 border-muted-foreground/20 focus:ring-primary">
                                    <SelectValue placeholder="Seleccionar" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="role in roles" :key="role.id" :value="role.id.toString()">
                                        {{ role.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.role_id" class="text-[0.7rem] font-semibold text-destructive uppercase tracking-tight">{{ form.errors.role_id }}</p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="area_id" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Área Principal</Label>
                            <Select v-model="form.area_id">
                                <SelectTrigger :class="{'border-destructive shadow-sm': form.errors.area_id}" class="h-10 border-muted-foreground/20 focus:ring-primary">
                                    <SelectValue placeholder="Seleccionar" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="area in areas" :key="area.id" :value="area.id.toString()">
                                        {{ area.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.area_id" class="text-[0.7rem] font-semibold text-destructive uppercase tracking-tight">{{ form.errors.area_id }}</p>
                        </div>
                    </div>

                    <DialogFooter class="pt-6 flex gap-2">
                        <Button type="button" variant="ghost" @click="isModalOpen = false" class="border">Cancelar</Button>
                        <Button type="submit" :disabled="form.processing" class="flex-1 bg-primary hover:bg-primary/90 shadow-md">
                            <Icon v-if="form.processing" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
                            {{ editingUser ? 'Guardar Cambios' : 'Crear Usuario' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>