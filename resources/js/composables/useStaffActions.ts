import axios from 'axios';

export function useStaffActions(onDeleteSuccess: (id: number) => void) {
    const deleteStaff = async (staffId: number) => {
        if (!confirm('¿Estás seguro de eliminar a este personal?')) return;

        try {
            await axios.delete(`/staff/${staffId}`);
            onDeleteSuccess(staffId);
        } catch (err) {
            console.error('Error al eliminar personal:', err);
            alert('Ocurrió un error al intentar eliminar el registro.');
        }
    };

    return {
        deleteStaff,
    };
}
