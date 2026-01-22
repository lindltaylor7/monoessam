import { Staff } from '@/types';
import { computed, ref, Ref } from 'vue';

export function useStaffFilter(initialStaff: Ref<Staff[]>) {
    const searchQuery = ref('');
    const selectedUnitId = ref('0'); // Using string for select compatibility

    const filteredStaff = computed(() => {
    let result = initialStaff.value;

    // 1. Filtro por Unidad (se mantiene igual)
    if (selectedUnitId.value !== '0') {
        result = result.filter((staff) => 
            staff.staffable?.unit_id === parseInt(selectedUnitId.value)
        );
    }

    // 2. Filtro por Búsqueda (Fuzzy Search con Levenshtein)
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase().trim();
        const MAX_DISTANCE = 2; // Máximo de errores permitidos

        result = result.filter((staff) => {
            const name = staff.name.toLowerCase();
            const dni = staff.dni.toLowerCase();

            // A. Coincidencia exacta o parcial (Rápido)
            if (name.includes(query) || dni.includes(query)) return true;

            // B. Lógica Levenshtein (Fuzzy)
            // Dividimos el nombre por palabras para comparar la query con cada una
            const nameWords = name.split(' ');
            const isCloseMatch = nameWords.some(word => {
                // Solo comparamos si la palabra tiene un largo similar a la query
                if (Math.abs(word.length - query.length) > MAX_DISTANCE) return false;
                return getLevenshteinDistance(query, word) <= MAX_DISTANCE;
            });

            return isCloseMatch;
        });
    }

    return result;
});

    const getLevenshteinDistance = (a, b) => {
    const matrix = [];

    for (let i = 0; i <= b.length; i++) matrix[i] = [i];
    for (let j = 0; j <= a.length; j++) matrix[0][j] = j;

    for (let i = 1; i <= b.length; i++) {
        for (let j = 1; j <= a.length; j++) {
            if (b.charAt(i - 1) === a.charAt(j - 1)) {
                matrix[i][j] = matrix[i - 1][j - 1];
            } else {
                matrix[i][j] = Math.min(
                    matrix[i - 1][j - 1] + 1, // sustitución
                    matrix[i][j - 1] + 1,     // inserción
                    matrix[i - 1][j] + 1      // eliminación
                );
            }
        }
    }
    return matrix[b.length][a.length];
};

    return {
        searchQuery,
        selectedUnitId,
        filteredStaff,
    };
}
