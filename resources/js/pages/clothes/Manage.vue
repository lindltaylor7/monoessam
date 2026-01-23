<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

const props = defineProps<{
    roles: Array<{ id: number, name: string }>;
    clothes: Array<{ id: number, name: string, roles: Array<{ id: number }> }>;
}>();

const newClothName = ref('');

const createCloth = () => {
    if(!newClothName.value) return;
    router.post(route('clothes.store'), {
        name: newClothName.value
    }, {
        onSuccess: () => newClothName.value = ''
    });
};

const deleteCloth = (id: number) => {
    if(confirm('¿Seguro que deseas eliminar esta prenda?')) {
        router.delete(route('clothes.destroy', id), {
             preserveScroll: true
        });
    }
};

const toggleRole = (clothId: number, roleId: number, currentStatus: boolean) => {
    router.post(route('clothes.assign-role'), {
        cloth_id: clothId,
        role_id: roleId,
        action: currentStatus ? 'detach' : 'attach'
    }, {
        preserveScroll: true
    });
};

const hasRole = (cloth: any, roleId: number) => {
    return cloth.roles.some((r: any) => r.id === roleId);
};
</script>

<template>
    <Head title="Gestión de Ropa por Rol" />

    <AppLayout :breadcrumbs="[
        { title: 'Personal', href: route('staff.index') },
        { title: 'Ropa', href: route('clothes.index') },
        { title: 'Configuración', href: route('clothes.manage') }
    ]">
        <div class="p-6 space-y-8 max-w-7xl mx-auto">
            <div class="flex justify-between items-center">
                 <h1 class="text-3xl font-bold tracking-tight">Gestión de Ropa y Perfiles</h1>
                 <Link :href="route('clothes.index')">
                    <Button variant="outline">Volver a Registro de Tallas</Button>
                 </Link>
            </div>

            <!-- Create Cloth -->
            <div class="bg-card text-card-foreground rounded-xl border shadow-sm p-6 bg-white">
                <h2 class="text-lg font-semibold mb-4">Agregar Nueva Prenda</h2>
                <div class="flex gap-4 max-w-md">
                    <Input v-model="newClothName" placeholder="Nombre (ej. Pantalón, Camisa)" @keyup.enter="createCloth" />
                    <Button @click="createCloth">Agregar</Button>
                </div>
            </div>

            <!-- Matrix -->
            <div class="bg-card text-card-foreground rounded-xl border shadow-sm overflow-hidden bg-white">
                <div class="p-6 border-b">
                    <h2 class="text-lg font-semibold">Asignación de Prendas a Roles</h2>
                    <p class="text-sm text-muted-foreground">Marca las casillas para asignar prendas permitidas a cada rol.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-muted/50 text-muted-foreground bg-gray-50">
                            <tr>
                                <th class="p-4 font-medium text-gray-500">Prenda</th>
                                <th v-for="role in roles" :key="role.id" class="p-4 font-medium text-center text-gray-500 whitespace-nowrap">
                                    {{ role.name }}
                                </th>
                                <th class="p-4 font-medium text-right text-gray-500">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="cloth in clothes" :key="cloth.id" class="hover:bg-gray-50 transition-colors">
                                <td class="p-4 font-medium">{{ cloth.name }}</td>
                                <td v-for="role in roles" :key="role.id" class="p-4 text-center">
                                    <div class="flex justify-center">
                                        <input 
                                            type="checkbox"
                                            :checked="hasRole(cloth, role.id)"
                                            @change="toggleRole(cloth.id, role.id, hasRole(cloth, role.id))"
                                            class="h-5 w-5 rounded border-gray-300 text-black focus:ring-black cursor-pointer accent-black"
                                        />
                                    </div>
                                </td>
                                <td class="p-4 text-right">
                                    <button @click="deleteCloth(cloth.id)" class="text-red-500 hover:text-red-700 hover:underline text-xs">
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
