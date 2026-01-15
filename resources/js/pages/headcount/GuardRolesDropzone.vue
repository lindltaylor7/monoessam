<script setup lang="ts">
import { Role, Staff } from '@/types';
import { useDroppable } from '@vue-dnd-kit/core';
import axios from 'axios';
import { nextTick, ref } from 'vue'; // Importa nextTick
import { ClickOutside as vClickOutside } from 'vue-click-outside';
import Draggable from './Draggable.vue';

interface Props {
    role: Role;
    users: User[];
    staff: Staff;
}

interface User {
    id: number;
    name: string;
    type: number;
    avatar?: string;
}
const emit = defineEmits<{
    (e: 'roleAssigned', userId: number): void;
    (e: 'unassignUser', userId: number): void;
}>();

const props = defineProps<Props>();

// --- Estados Reactivos ---
const userDropped = ref<Staff | null>(null);
const isEditing = ref(false);
const searchText = ref('');
const searchResults = ref<User[]>([]);
const inputRef = ref<HTMLInputElement | null>(null); // Referencia al input

console.log(props);

if (props.role?.staff) {
    console.log(props);
    userDropped.value = props.staff as Staff;
}

// --- Métodos de Lógica ---
// ... (unassignUser, assignUserToRole, searchUsers, closeEditing, selectUser sin cambios)

// Lógica de Desasignación
const unassignUser = (userId: number) => {
    userDropped.value = null;
    emit('unassignUser', userId);
};

// Lógica de Asignación a través de la API (Común para Drag & Drop y Click/Search)
const assignUserToRole = (user: User) => {
    userDropped.value = user;
    isEditing.value = false;
    searchText.value = '';
    searchResults.value = [];

    axios
        .post('guards/roles/user', {
            user_id: user.id,
            guard_role_id: props.role.id,
        })
        .then((response) => {
            emit('roleAssigned', user.id);
        })
        .catch((error) => {
            console.error('Error al asignar el rol:', error);
            userDropped.value = props.role?.user ? (props.role.user as User) : null;
        });
};

// Lógica de Búsqueda
const searchUsers = async () => {
    if (searchText.value.length < 3) {
        searchResults.value = [];
        return;
    }
    const allUsers: User[] = props.users;

    searchResults.value = allUsers.filter((user) => user.name.toLowerCase().includes(searchText.value.toLowerCase())).slice(0, 5);
};

// Iniciar el modo de edición (al hacer clic en "Sin asignar")
const startEditing = () => {
    if (!userDropped.value) {
        isEditing.value = true;

        // Utilizar nextTick para enfocar el input después del renderizado
        nextTick(() => {
            if (inputRef.value) {
                inputRef.value.focus();
            }
        });
    }
};

// Salir del modo de edición (al hacer clic fuera)
const closeEditing = () => {
    isEditing.value = false;
    searchText.value = '';
    searchResults.value = [];
};

// Asignación al seleccionar un usuario de la lista de búsqueda
const selectUser = (user: User) => {
    assignUserToRole(user);
};

// --- Lógica de Drag and Drop (DND) ---
const { elementRef: guardRolesDropzoneRef, isOvered } = useDroppable({
    id: 'guard-roles-dropzone',
    events: {
        onDrop: (store, payload) => {
            const droppedUser = payload.items[0].data?.user;
            console.log(droppedUser);
            if (droppedUser) {
                assignUserToRole(droppedUser);
            }
        },
    },
});
</script>

<template>
    <div v-click-outside="closeEditing" ref="guardRolesDropzoneRef" class="relative min-h-[6rem] w-full">
        <div v-if="userDropped && userDropped.id">
            <Draggable :user="userDropped" @unassignUser="unassignUser" :showButtonDelete="true" />
        </div>

        <div v-else>
            <div v-if="isEditing" class="relative">
                <input
                    type="text"
                    v-model="searchText"
                    @input="searchUsers"
                    placeholder="Buscar y asignar usuario..."
                    class="w-full rounded border border-blue-500 p-2 focus:ring-2 focus:ring-blue-500"
                    @keyup.esc="closeEditing"
                    ref="inputRef"
                />

                <ul v-if="searchResults.length" class="absolute z-10 mt-1 w-full rounded border border-zinc-300 bg-white shadow-lg">
                    <li v-for="user in searchResults" :key="user.id" @click="selectUser(user)" class="cursor-pointer p-2 hover:bg-blue-100">
                        {{ user.name }}
                    </li>
                </ul>
                <div v-else-if="searchText.length >= 3" class="p-2 text-sm text-zinc-500">No se encontraron usuarios.</div>
            </div>

            <div
                v-else
                @click="startEditing"
                :class="{
                    'cursor-pointer rounded border-2 border-dashed p-5 transition-colors': true,
                    'border-zinc-600': !isOvered,
                    'border-green-500 bg-green-50 dark:bg-zinc-800': isOvered, // Feedback visual DND
                }"
            >
                Haga clic o arrastre para asignar
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Estilos necesarios para v-click-outside (si no lo maneja tu librería) */
.min-h-\[6rem\] {
    min-height: 6rem;
}
</style>
