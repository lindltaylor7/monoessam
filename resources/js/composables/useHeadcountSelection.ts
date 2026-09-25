import { Cafe, Mine, Unit } from '@/types';
import { ref, watch } from 'vue';

export function useHeadcountSelection(mines: Mine[]) {
    const selectedOptions = ref<{
        mine: string | null;
        unit: string | null;
        cafe: string | null;
        service: string | null;
    }>({
        mine: null,
        unit: null,
        cafe: null,
        service: null,
    });

    const selectedUnits = ref<Unit[]>([]);
    const selectedCafes = ref<Cafe[]>([]);
    const selectedServices = ref<any[]>([]);

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
            // Al cambiar de mina, una unidad que no le pertenece deja de estar seleccionada.
            if (newVal.unit && !selectedUnits.value.some((unit) => String(unit.id) === String(newVal.unit))) {
                newVal.unit = null;
            }

            // Cambió la unidad
            if (newVal.unit) {
                const unitSelected = selectedUnits.value.find((unit) => String(unit.id) === String(newVal.unit));
                // @ts-ignore - The structure of unit might include cafes
                selectedCafes.value = unitSelected ? (unitSelected as any).cafes || [] : [];
            } else {
                selectedCafes.value = [];
            }
            // Un comedor de otra unidad ya no es válido: se limpia para no arrastrar su servicio.
            if (newVal.cafe && !selectedCafes.value.some((cafe) => String(cafe.id) === String(newVal.cafe))) {
                newVal.cafe = null;
            }

            // Cambió el comedor
            if (newVal.cafe) {
                const cafeSelected = selectedCafes.value.find((cafe) => String(cafe.id) === String(newVal.cafe));
                // @ts-ignore - The structure of cafe might include services
                selectedServices.value = cafeSelected ? (cafeSelected as any).services || [] : [];
            } else {
                selectedServices.value = [];
            }
            // El servicio se identifica por el id del pivote comedor-servicio: si no es de este comedor,
            // se limpia (si no, seguiría cargando/guardando ciclos y estructuras del comedor anterior).
            if (newVal.service && !selectedServices.value.some((service) => String(service.pivot?.id) === String(newVal.service))) {
                newVal.service = null;
            }
        },
        { deep: true },
    );

    return {
        selectedOptions,
        selectedUnits,
        selectedCafes,
        selectedServices,
    };
}
