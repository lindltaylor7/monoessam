<script lang="ts" setup>
import { Role } from '@/types';
// NO necesitamos useDroppable aquí
import Draggable from './Draggable.vue';
import GuardRolesModal from './GuardRolesModal.vue';
// 💡 Importar el nuevo componente Droppable
import GuardRolesDropzone from './GuardRolesDropzone.vue';

// --- Interfaces y Tipos ---
interface Guardia {
    id: number;
    name: string;
    roles: Array<Role & { pivot?: { guard_id: number; role_id: number } }>;
    [key: string]: any;
}
interface User {
    id: number;
    name: string;
    type: number;
    avatar?: string;
}
interface Props {
    users: Array<User>;
    roles: Array<Role>;
    // Asumimos que 'guard' es un array de guardias si vas a usar varios
    guard: Guardia;
}

const props = defineProps<Props>();

const handleRoleDrop = (payload: { user: User; guardId: number }) => {
    console.log(`Usuario ${payload.user.name} arrastrado a la Guardia ID: ${payload.guardId}`);

    // Aquí puedes:
    // 1. Abrir un modal para confirmar la asignación del rol.
    // 2. Hacer una llamada API (Inertia POST/PUT) para asociar el usuario a la guardia.
    // 3. Remover el usuario de la lista de 'users' no asignados (si aplica).

    // Ejemplo de llamada a modal si usas GuardRolesModal para la asignación:
    // guardRolesModalRef.value.open(payload.user);
};
</script>

<template>
    <div class="container">
        <div class="content">
            <GuardRolesDropzone :guard="guard" @roleDropped="handleRoleDrop" class="h-[75vh]">
                <GuardRolesModal :roles="roles" :guard="guard" />

                <div class="mt-5 flex flex-wrap gap-3">
                    <Draggable v-for="user in users" :user="user" :key="user.id" />
                </div>
            </GuardRolesDropzone>
        </div>
    </div>
</template>
