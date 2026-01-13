import { Mine, Unit, Cafe } from '@/types';
import { ref, watch, Ref } from 'vue';

export function useHeadcountSelection(mines: Mine[]) {
    const selectedOptions = ref<{
        mine: string | null;
        unit: string | null;
        cafe: string | null;
    }>({
        mine: null,
        unit: null,
        cafe: null,
    });

    const selectedUnits = ref<Unit[]>([]);
    const selectedCafes = ref<Cafe[]>([]);

    watch(
        selectedOptions,
        (newVal) => {
            // Cambió la mina
            if (newVal.mine !== null && newVal.mine !== undefined) {
                const mineSelected = mines.find((mine) => String(mine.id) === String(newVal.mine));
                // @ts-ignore - The structure of mine in props might include units if it comes from eloquent with relations
                selectedUnits.value = mineSelected ? (mineSelected as any).units || [] : [];
            } else {
                selectedUnits.value = [];
            }

            // Cambió la unidad
            if (newVal.unit) {
                const unitSelected = selectedUnits.value.find((unit) => String(unit.id) === String(newVal.unit));
                // @ts-ignore - The structure of unit might include cafes
                selectedCafes.value = unitSelected ? (unitSelected as any).cafes || [] : [];
            } else {
                selectedCafes.value = [];
            }
        },
        { deep: true }
    );

    return {
        selectedOptions,
        selectedUnits,
        selectedCafes,
    };
}
