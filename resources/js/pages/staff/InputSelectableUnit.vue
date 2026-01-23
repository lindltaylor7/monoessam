<script setup lang="ts">
import Input from '@/components/ui/input/Input.vue';
import { Cafe, Unit } from '@/types';
import { AlertCircle, Plus, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

interface Props {
    units: Unit[];
    unitSelected: number;
}

interface Emits {
    (e: 'selectUnit', unit: Unit): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const searchTerm = ref('');
const isDropdownVisible = ref(false);
const selectedUnit = ref<Unit | null>(null);
const shouldFocusResults = ref(false);

// Modificar el watcher para que no se active cuando hay selección
watch(searchTerm, async (newVal) => {
    // Si ya hay un café seleccionado, no mostrar dropdown
    if (selectedUnit.value) {
        isDropdownVisible.value = false;
        return;
    }

    isDropdownVisible.value = newVal.length > 0;

    if (shouldFocusResults.value && newVal.length > 0) {
        await nextTick();
        const firstResult = document.querySelector('[data-role-result]') as HTMLElement;
        firstResult?.focus();
    }

    shouldFocusResults.value = false;
});

const filteredUnits = computed(() => {
    // Si hay un café seleccionado, no mostrar resultados
    if (selectedUnit.value) return [];

    if (!searchTerm.value) {
        return props.units.slice(0, 5);
    }

    const lowerCaseSearchTerm = searchTerm.value.toLowerCase();
    const scoredUnits = props.units.map((unit) => {
        const name = unit.name.toLowerCase();
        let score = 0;

        if (name.startsWith(lowerCaseSearchTerm)) score += 3;
        if (name.includes(lowerCaseSearchTerm)) score += 2;
        if (name.split(' ').some((word) => word.startsWith(lowerCaseSearchTerm))) score += 1;

        return { unit, score };
    });

    return scoredUnits
        .filter((item) => item.score > 0)
        .sort((a, b) => b.score - a.score)
        .slice(0, 7)
        .map((item) => item.unit);
});

const addUnit = (unit: Unit) => {
    selectedUnit.value = unit;
    searchTerm.value = unit.name;
    isDropdownVisible.value = false;

    // Emitir al componente padre
    emit('selectUnit', unit);
};

if (props.unitSelected) {
    const unitFound = props.units.find((unit) => unit.id == props.unitSelected);

    addUnit(unitFound);
}

const clearSelection = () => {
    selectedUnit.value = null;
    searchTerm.value = '';
    isDropdownVisible.value = false;
    //searchInputRef.value?.$el?.focus();
};

const handleKeydown = (event: Event, cafe?: Cafe) => {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            const nextResult = (event.target as HTMLElement).nextElementSibling as HTMLElement;
            nextResult?.focus();
            break;
        case 'ArrowUp':
            event.preventDefault();
            const prevResult = (event.target as HTMLElement).previousElementSibling as HTMLElement;
            prevResult?.focus() || searchInputRef.value?.$el?.focus();
            break;
        case 'Enter':
            event.preventDefault();
            if (cafe) addCafe(cafe);
            break;
        case 'Escape':
            isDropdownVisible.value = false;
            searchInputRef.value?.$el?.focus();
            break;
    }
};
</script>

<template>
    <div class="relative">
        <div class="relative">
            <Input
                ref="searchInputRef"
                :placeholder="selectedUnit ? 'Unidad seleccionada' : 'Buscar unidad'"
                v-model="searchTerm"
                class="w-full px-2 text-center"
                :class="selectedUnit ? 'border-green-200 bg-green-50' : ''"
                @click="isDropdownVisible = true"
                @keydown.esc="isDropdownVisible = false"
                @keydown="(e) => handleKeydown(e)"
                autocomplete="off"
                :disabled="!!selectedUnit"
            />
            <button
                v-if="selectedUnit"
                @click="clearSelection"
                class="absolute top-1/2 right-3 -translate-y-1/2 transform text-green-600 hover:text-green-800"
                title="Cambiar comedor"
            >
                <X :size="16" />
            </button>
            <button
                v-else-if="searchTerm"
                @click="clearSelection"
                class="absolute top-1/2 right-3 -translate-y-1/2 transform text-gray-400 hover:text-gray-600"
            >
                <X :size="16" />
            </button>
        </div>
        <!-- Dropdown de resultados (solo visible si no hay selección) -->
        <div
            v-if="isDropdownVisible && !selectedUnit"
            ref="resultsContainerRef"
            class="absolute z-10 mt-2 max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"
        >
            <div
                v-for="unit in filteredUnits"
                :key="unit.id"
                :data-role-result="true"
                @click="addUnit(unit)"
                @keydown="(e) => handleKeydown(e, unit)"
                class="flex cursor-pointer items-center gap-3 p-3 transition-colors hover:bg-gray-50 focus:bg-gray-50 focus:ring-2 focus:ring-green-500 focus:outline-none"
                tabindex="0"
            >
                <Plus :size="16" class="flex-shrink-0 text-green-500" />
                <span class="flex-1 text-sm">{{ unit.name }} - {{ unit.mine?.name }}</span>
                <kbd class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-400">Enter</kbd>
            </div>

            <div v-if="filteredUnits.length === 0 && searchTerm" class="p-4 text-center text-gray-500">
                <AlertCircle :size="20" class="mx-auto mb-2 text-gray-400" />
                <p>No se encontró unidad</p>
                <p class="mt-1 text-sm">Intenta con otros términos</p>
            </div>
        </div>

        <!-- Badge de selección (opcional) -->
        <!-- <div v-if="selectedCafe" class="mt-2 flex items-center justify-center">
            <span class="rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800"> ✓ {{ selectedCafe.name }} seleccionado </span>
        </div> -->
    </div>
</template>
