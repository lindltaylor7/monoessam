<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Permission, Role, User } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ListCheck, Route, Search, Shield, ShieldOff, SidebarIcon, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PermissionModal from './PermissionModal.vue';

interface Props {
    users: User[];
    roles: Role[];
    permissions: Permission[];
}

const props = defineProps<Props>();

const search = ref('');

const filtered = computed(() => {
    const q = search.value.toLowerCase().trim();
    if (!q) return props.permissions;
    return props.permissions.filter(
        (p) =>
            p.name.toLowerCase().includes(q) ||
            p.sidebar_name?.toLowerCase().includes(q) ||
            p.route_name?.toLowerCase().includes(q) ||
            p.icon_class?.toLowerCase().includes(q),
    );
});

const deletePermission = (id: number) => {
    if (confirm('¿Estás seguro de que deseas eliminar este permiso?')) {
        router.delete(route('permissions.destroy', id));
    }
};
</script>

<template>
    <Head title="Permisos" />
    <AppLayout title="Permisos">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex h-14 w-full items-center justify-between gap-3 rounded-xl bg-gradient-to-r from-blue-50 to-purple-50 px-5 shadow-sm dark:from-gray-700 dark:to-gray-700"
            >
                <div class="flex items-center gap-2.5">
                    <Shield class="h-5 w-5 text-blue-500" />
                    <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">Gestión de Permisos</h2>
                    <Badge variant="secondary" class="bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                        {{ permissions.length }}
                    </Badge>
                </div>
                <PermissionModal />
            </div>

            <!-- Search bar -->
            <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white/80 px-4 py-3 shadow-sm backdrop-blur-sm dark:border-gray-700 dark:bg-gray-800/60">
                <div class="group relative flex-1">
                    <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400 transition-colors group-focus-within:text-blue-500" />
                    <Input
                        v-model="search"
                        placeholder="Buscar por nombre, ruta, sidebar..."
                        class="h-10 rounded-lg border-gray-200 bg-gray-50/50 pl-10 transition-all focus:bg-white focus:ring-4 focus:ring-blue-500/10 dark:border-gray-600 dark:bg-gray-700/50"
                    />
                </div>
                <div class="flex items-center gap-2 rounded-lg border border-blue-100 bg-blue-50/50 px-3 py-1.5 dark:border-blue-900/40 dark:bg-blue-900/20">
                    <ListCheck class="h-4 w-4 text-blue-500" />
                    <span class="text-sm font-semibold text-blue-700 dark:text-blue-300">{{ filtered.length }}</span>
                    <span class="text-xs font-medium uppercase tracking-tighter text-blue-600/70 dark:text-blue-400/70">resultados</span>
                </div>
            </div>

            <!-- Table -->
            <Card class="overflow-hidden border-gray-200 shadow-sm dark:border-gray-700">
                <CardContent class="p-0">
                    <Table>
                        <TableHeader class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                            <TableRow>
                                <TableHead class="w-10 text-center font-bold text-gray-500 dark:text-gray-400">#</TableHead>
                                <TableHead class="font-bold text-gray-700 dark:text-gray-300">Nombre</TableHead>
                                <TableHead class="font-bold text-gray-700 dark:text-gray-300">
                                    <div class="flex items-center gap-1.5">
                                        <SidebarIcon class="h-3.5 w-3.5 text-purple-400" />
                                        Sidebar
                                    </div>
                                </TableHead>
                                <TableHead class="font-bold text-gray-700 dark:text-gray-300">
                                    <div class="flex items-center gap-1.5">
                                        <Route class="h-3.5 w-3.5 text-blue-400" />
                                        Ruta
                                    </div>
                                </TableHead>
                                <TableHead class="font-bold text-gray-700 dark:text-gray-300">Icono</TableHead>
                                <TableHead class="text-right font-bold text-gray-700 dark:text-gray-300">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="(permission, index) in filtered"
                                :key="permission.id"
                                class="group transition-colors hover:bg-blue-50/40 dark:hover:bg-blue-900/10"
                            >
                                <!-- # -->
                                <TableCell class="text-center text-xs font-medium text-gray-400 dark:text-gray-500">
                                    {{ index + 1 }}
                                </TableCell>

                                <!-- Name -->
                                <TableCell>
                                    <code class="rounded-md bg-gray-100 px-2 py-0.5 font-mono text-sm font-semibold text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                        {{ permission.name }}
                                    </code>
                                </TableCell>

                                <!-- Sidebar -->
                                <TableCell>
                                    <span v-if="permission.sidebar_name">
                                        <Badge variant="outline" class="bg-purple-50 font-medium text-purple-700 border-purple-200 dark:bg-purple-900/20 dark:text-purple-300 dark:border-purple-800">
                                            {{ permission.sidebar_name }}
                                        </Badge>
                                    </span>
                                    <span v-else class="text-xs text-gray-300 dark:text-gray-600">—</span>
                                </TableCell>

                                <!-- Route -->
                                <TableCell>
                                    <span v-if="permission.route_name">
                                        <code class="rounded bg-blue-50 px-2 py-0.5 font-mono text-xs text-blue-700 dark:bg-blue-900/20 dark:text-blue-300">
                                            {{ permission.route_name }}
                                        </code>
                                    </span>
                                    <span v-else class="text-xs text-gray-300 dark:text-gray-600">—</span>
                                </TableCell>

                                <!-- Icon -->
                                <TableCell>
                                    <span v-if="permission.icon_class">
                                        <Badge variant="secondary" class="bg-gray-100 font-mono text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                            {{ permission.icon_class }}
                                        </Badge>
                                    </span>
                                    <span v-else class="text-xs text-gray-300 dark:text-gray-600">—</span>
                                </TableCell>

                                <!-- Actions -->
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                        <PermissionModal :permission="permission" />
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 text-red-500 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20"
                                            @click="deletePermission(permission.id)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>

                            <!-- Empty state (filtered) -->
                            <TableRow v-if="filtered.length === 0 && search">
                                <TableCell colspan="6" class="h-48 text-center">
                                    <div class="flex flex-col items-center justify-center gap-2 text-gray-400">
                                        <Search class="h-10 w-10 opacity-20" />
                                        <p class="text-sm font-medium">Sin resultados para "{{ search }}"</p>
                                        <p class="text-xs">Intenta con otro término de búsqueda</p>
                                    </div>
                                </TableCell>
                            </TableRow>

                            <!-- Empty state (no data) -->
                            <TableRow v-if="permissions.length === 0">
                                <TableCell colspan="6" class="h-56 text-center">
                                    <div class="flex flex-col items-center justify-center gap-3 text-gray-400">
                                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                                            <ShieldOff class="h-7 w-7 opacity-40" />
                                        </div>
                                        <p class="font-medium text-gray-500 dark:text-gray-400">No hay permisos registrados</p>
                                        <p class="text-xs">Crea el primer permiso usando el botón de arriba</p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
