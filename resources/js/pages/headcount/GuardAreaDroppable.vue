<script lang="ts" setup>
import { Role } from '@/types';
import axios from 'axios';
import { Trash } from 'lucide-vue-next';
import GuardRolesDropzone from './GuardRolesDropzone.vue';
import GuardRolesModal from './GuardRolesModal.vue';

interface Props {
    users: Array<any>;
    roles: Array<Role>;
    guard: {
        id: number;
        name: string;
        roles: Array<Role & { pivot?: { guard_id: number; role_id: number } }>;
        [key: string]: any;
    };
    unassignedUsers: Array<any>;
}

interface User {
    id: number;
    name: string;
    type: number;
    avatar?: string;
}

const emit = defineEmits<{
    (e: 'dropped', user: User): void;
    (e: 'asignRolesToGuard', guardId: number, roles: Array<any>): void;
    (e: 'deleteGuardRole', guardId: number, roleId: number): void;
    (e: 'unassignUser', userId: number): void;
    (e: 'deleteGuard', guardId: number): void;
}>();

const props = defineProps<Props>();

const asignRoles = (roles: Role[]) => {
    console.log('calling a asignar roles', roles);
    emit('asignRolesToGuard', props.guard.id, roles);
};

const deleteRole = (role: Role) => {
    if (confirm('Estás seguro de eliminar este rol?')) {
        axios
            .delete(`/guards/roles/${role.id}`)
            .then(() => {
                console.log('Rol eliminado con éxito');
            })
            .catch((error) => {
                console.error('Error al eliminar el rol:', error);
            });

        emit('deleteGuardRole', props.guard.id, role.id);
    }
};

const roleAssigned = (userId: number) => {
    emit('dropped', userId);
};

const unassignUser = (userId: number) => {
    emit('unassignUser', userId);
};

const deleteGuard = (guardId: number) => {
    if (confirm('Estás seguro de eliminar este guardia, si lo hace se eliminarán todos los roles y usuarios asignados?')) {
        axios
            .delete(`/guards/${guardId}`)
            .then(() => {
                console.log('Guardia eliminado con éxito');
                emit('deleteGuard', guardId);
            })
            .catch((error) => {
                console.error('Error al eliminar el guardia:', error);
            });
    }
};
</script>

<template>
    <div class="container">
        <div class="content">
            <div class="dropzone">
                <div class="header">
                    <h3 class="title">{{ guard.name }}</h3>
                    <div class="flex items-center gap-4">
                        <GuardRolesModal :roles="roles" :guard="guard" @asignRoles="asignRoles" />
                        <button class="delete-btn" @click="deleteGuard(guard.id)" title="Eliminar rol">
                            <Trash :size="16" />
                        </button>
                    </div>
                </div>

                <div v-if="guard.assigned_roles?.length > 0" class="roles-section h-[50vh] overflow-y-auto">
                    <h4 class="section-title">Roles Asignados</h4>
                    <div class="grid gap-6 md:grid-cols-4">
                        <div v-for="role in guard.assigned_roles" :key="role.id" class="role-card">
                            <div class="role-info">
                                <span class="role-name">{{ role.role.name }}</span>
                                <button class="delete-btn" @click="deleteRole(role)" title="Eliminar rol">
                                    <Trash :size="16" />
                                </button>
                            </div>
                            <GuardRolesDropzone
                                :role="role"
                                :staff="role.staff"
                                @roleAssigned="roleAssigned"
                                @unassignUser="unassignUser"
                                :users="unassignedUsers"
                            />
                        </div>
                    </div>
                </div>
                <div v-else class="empty-state">
                    <p class="empty-text">No hay roles asignados</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.container {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 5px;
}

.content {
    display: flex;
    gap: 30px;
    margin-top: 20px;
    width: 100%;
}

.dropzone {
    width: 100%;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    padding: 24px;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.dropzone:hover {
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f1f5f9;
}

.title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.section-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.roles-section {
    margin-bottom: 24px;
}

.roles-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.role-card {
    background: #f8fafc;
    border-radius: 8px;
    padding: 16px;
    border-left: 4px solid #10b981;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
}

.role-card:hover {
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
}

.role-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.role-name {
    font-weight: 600;
    color: #334155;
    font-size: 0.95rem;
}

.delete-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    background: transparent;
    border: none;
    color: #ef4444;
    cursor: pointer;
    transition: all 0.2s ease;
}

.delete-btn:hover {
    background: #fef2f2;
    color: #dc2626;
}

.empty-state {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 24px;
    background: #f8fafc;
    border-radius: 8px;
    margin-bottom: 24px;
}

.empty-text {
    color: #64748b;
    font-style: italic;
    margin: 0;
}

.users-section {
    margin-top: 24px;
}

.users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 12px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .content {
        flex-direction: column;
        gap: 20px;
    }

    .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .users-grid {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    }
}
</style>
