<script setup lang="ts">
import Input from '@/components/ui/input/Input.vue';
import { Role } from '@/types';
import { AlertCircle, Plus, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

interface Props {
    roles: Role[];
    roleSelected: number;
}

interface Emits {
    (e: 'selectRole', role: Role): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const searchTerm = ref('');
const isDropdownVisible = ref(false);
const selectedRole = ref<Role | null>(null);
const shouldFocusResults = ref(false);

// Modificar el watcher para que no se active cuando hay selección
watch(searchTerm, async (newVal, oldVal) => {
    // Si ya hay un café seleccionado, no mostrar dropdown
    if (selectedRole.value) {
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

const filteredRoles = computed(() => {
    // Si hay un café seleccionado, no mostrar resultados
    if (selectedRole.value) return [];

    if (!searchTerm.value) {
        return props.roles.slice(0, 10);
    }

    const lowerCaseSearchTerm = searchTerm.value.toLowerCase();
    const scoredCafes = props.roles.map((role) => {
        const name = role.name.toLowerCase();
        let score = 0;

        if (name.startsWith(lowerCaseSearchTerm)) score += 3;
        if (name.includes(lowerCaseSearchTerm)) score += 2;
        if (name.split(' ').some((word) => word.startsWith(lowerCaseSearchTerm))) score += 1;

        return { role, score };
    });

    return scoredCafes
        .filter((item) => item.score > 0)
        .sort((a, b) => b.score - a.score)
        .slice(0, 7)
        .map((item) => item.role);
});

const addRole = (role: Role) => {
    selectedRole.value = role;
    searchTerm.value = role.name;
    isDropdownVisible.value = false;

    // Emitir al componente padre
    emit('selectRole', role);
};

if (props.roleSelected) {
    const roleFound = props.roles.find((role) => role.id == props.roleSelected);

    addRole(roleFound);
}

const clearSelection = () => {
    selectedRole.value = null;
    searchTerm.value = '';
    isDropdownVisible.value = false;
    searchInputRef.value?.$el?.focus();
};

const handleKeydown = (event: Event, role?: Role) => {
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
            if (role) addRole(role);
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
                :placeholder="selectedRole ? 'Cargo seleccionado' : 'Buscar cargo'"
                v-model="searchTerm"
                class="w-full px-2 text-center"
                :class="selectedRole ? 'border-green-200 bg-green-50' : ''"
                @click="isDropdownVisible = true"
                @keydown.esc="isDropdownVisible = false"
                @keydown="(e) => handleKeydown(e)"
                autocomplete="off"
                :disabled="!!selectedRole"
            />
            <button
                v-if="selectedRole"
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
            v-if="isDropdownVisible && !selectedRole"
            ref="resultsContainerRef"
            class="absolute z-10 mt-2 max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"
        >
            <div
                v-for="role in filteredRoles"
                :key="role.id"
                :data-role-result="true"
                @click="addRole(role)"
                @keydown="(e) => handleKeydown(e, role)"
                class="flex cursor-pointer items-center gap-3 p-3 transition-colors hover:bg-gray-50 focus:bg-gray-50 focus:ring-2 focus:ring-green-500 focus:outline-none"
                tabindex="0"
            >
                <Plus :size="16" class="flex-shrink-0 text-green-500" />
                <span class="flex-1 text-sm">{{ role.name }}</span>
                <kbd class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-400">Enter</kbd>
            </div>

            <div v-if="filteredRoles.length === 0 && searchTerm" class="p-4 text-center text-gray-500">
                <AlertCircle :size="20" class="mx-auto mb-2 text-gray-400" />
                <p>No se encontró comedor</p>
                <p class="mt-1 text-sm">Intenta con otros términos</p>
            </div>
        </div>

        <!-- Badge de selección (opcional) -->
        <!--  <div v-if="selectedRole" class="mt-2 flex items-center justify-center">
            <span class="rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800"> ✓ {{ selectedRole.name }} seleccionado </span>
        </div> -->
    </div>
</template>
