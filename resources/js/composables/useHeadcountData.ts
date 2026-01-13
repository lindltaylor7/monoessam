import { Cafe, Role, User } from '@/types';
import axios from 'axios';
import { ref, Ref } from 'vue';

export function useHeadcountData() {
    const guardsSelected = ref<Cafe[]>([]);
    const unassignedUsers = ref<User[]>([]);
    const assignedUsers = ref<User[]>([]);
    const selectedPeriods = ref<any[]>([]);

    const fetchCafeData = async (cafeId: number) => {
        try {
            const response = await axios.get(`/cafes/${cafeId}`);
            const cafeData = response.data;
            guardsSelected.value = cafeData.guards;
            unassignedUsers.value = cafeData.users.unassigned;
            assignedUsers.value = cafeData.users.assigned;
            selectedPeriods.value = cafeData.periods;
        } catch (error) {
            console.error('Error fetching cafe data:', error);
        }
    };

    const assignGuards = (guards: Cafe[]) => {
        guardsSelected.value = guards;
    };

    const deleteGuard = (guardId: number) => {
        guardsSelected.value = guardsSelected.value.filter((guard) => guard.id !== guardId);
    };

    // User Assignment Actions
    const handleUserAssignment = (userId: number) => {
        unassignedUsers.value = unassignedUsers.value.filter((user) => user.id !== userId);
    };

    const unassignUser = (userId: number, currentCafeId: number) => {
        // En la lógica original, buscaba en selectedCafes. Aquí necesitamos la referencia al usuario.
        // Asumimos que si se desasigna, vuelve a estar disponible.
        // Esta lógica dependía de `selectedCafes` en el componente original para encontrar al usuario completo.
        // Simplificaremos asumiendo que el compoente hijo pasa el usuario o recargamos datos.
        // Por ahora mantenemos la lógica de UI: mover a unassigned.
        
        // NOTA: La lógica original era un poco confusa buscando en `selectedCafes.staffs`.
        // Para refactorizar limpiamente, idealmente el backend debería manejar esto o deberíamos
        // tener todos los usuarios disponibles.
        // Por ahora, recargar los datos del café podría ser lo más seguro para consistencia.
        if (currentCafeId) {
             fetchCafeData(currentCafeId);
        }
    };

    // Role Actions
    const asignRolesToGuard = (guardId: number, roles: Role[]) => {
        const guard = guardsSelected.value.find((g) => g.id === guardId);
        if (guard) {
            guard.assigned_roles = [];
            roles.forEach((role) => {
                const newRole = {
                    role: {
                        id: role.id,
                        guard_id: guardId,
                        role_id: role.id,
                        name: role.name,
                    },
                };
                // @ts-ignore - assigned_roles dynamic structure
                guard.assigned_roles.push(newRole);
            });
        }
    };

    const deleteGuardRole = (guardId: number, roleId: number) => {
        const guard = guardsSelected.value.find((g) => g.id === guardId);
        if (guard) {
             // @ts-ignore
            guard.assigned_roles = guard.assigned_roles.filter((role: any) => role.id !== roleId);
        }
    };

    return {
        guardsSelected,
        unassignedUsers,
        assignedUsers,
        selectedPeriods,
        fetchCafeData,
        assignGuards,
        deleteGuard,
        handleUserAssignment,
        unassignUser,
        asignRolesToGuard,
        deleteGuardRole,
    };
}
