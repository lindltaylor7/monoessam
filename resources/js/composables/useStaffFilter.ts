import { Staff } from '@/types';
import { computed, ref, Ref } from 'vue';

export function useStaffFilter(initialStaff: Ref<Staff[]>) {
    const searchQuery = ref('');
    const selectedUnitId = ref('0'); // Using string for select compatibility

    const filteredStaff = computed(() => {
        let result = initialStaff.value;

        // Filter by Unit
        if (selectedUnitId.value !== '0') {
            result = result.filter((staff) => 
                staff.staffable?.unit_id === parseInt(selectedUnitId.value)
            );
        }

        // Filter by Search Query
        if (searchQuery.value.trim()) {
            const query = searchQuery.value.toLowerCase().trim();
            result = result.filter((staff) => 
                staff.name.toLowerCase().includes(query) || 
                staff.dni.toLowerCase().includes(query)
            );
        }

        return result;
    });

    return {
        searchQuery,
        selectedUnitId,
        filteredStaff,
    };
}
