<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, Plus, Search, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

// --- Tipos de Datos ---
interface Role {
    id: number;
    name: string;
}

interface SelectedRoleGroup {
    role: Role;
    quantity: number;
}

const props = defineProps({
    guard: {
        type: Object,
        required: true,
    },
    roles: {
        type: Array as () => Role[],
        default: () => [],
    },
});

const emit = defineEmits<{
    (e: 'asignRoles', roles: Array<any>): void;
}>();

const open = ref(false);
const searchTerm = ref('');
const selectedRoles = ref<SelectedRoleGroup[]>([]);
const isDropdownVisible = ref(false);
const isSubmitting = ref(false);
const showSuccess = ref(false);

const form = useForm({
    guard_id: 0,
    roles_ids: [] as number[],
});

// Roles filtrados con mejor scoring de búsqueda
const filteredRoles = computed(() => {
    if (!searchTerm.value) {
        return props.roles.slice(0, 5);
    }

    const lowerCaseSearchTerm = searchTerm.value.toLowerCase();
    const scoredRoles = props.roles.map((role) => {
        const name = role.name.toLowerCase();
        let score = 0;

        // Priorizar coincidencias exactas al inicio
        if (name.startsWith(lowerCaseSearchTerm)) score += 3;
        // Coincidencias que contienen el término
        if (name.includes(lowerCaseSearchTerm)) score += 2;
        // Coincidencias parciales
        if (name.split(' ').some((word) => word.startsWith(lowerCaseSearchTerm))) score += 1;

        return { role, score };
    });

    return scoredRoles
        .filter((item) => item.score > 0)
        .sort((a, b) => b.score - a.score)
        .slice(0, 7)
        .map((item) => item.role);
});
const shouldFocusResults = ref(false);
// Watcher para abrir dropdown automáticamente
watch(searchTerm, async (newVal, oldVal) => {
    isDropdownVisible.value = newVal.length > 0;

    // Solo mover el focus si antes el usuario presionó ArrowDown
    if (shouldFocusResults.value && newVal.length > 0) {
        await nextTick();
        const firstResult = document.querySelector('[data-role-result]') as HTMLElement;
        firstResult?.focus();
    }

    // Resetear flag
    shouldFocusResults.value = false;
});

// Agregar rol
const addRole = (role: Role) => {
    const existing = selectedRoles.value.find(r => r.role.id === role.id);
    if (existing) {
        existing.quantity++;
    } else {
        selectedRoles.value.push({ role, quantity: 1 });
    }
    
    searchTerm.value = '';
    isDropdownVisible.value = false;

    // Enfocar el input de búsqueda después de agregar
    nextTick(() => {
        searchInputRef.value?.$el?.focus();
    });
};

const increaseQuantity = (roleId: number) => {
    const roleGroup = selectedRoles.value.find(r => r.role.id === roleId);
    if (roleGroup) roleGroup.quantity++;
};

const decreaseQuantity = (roleId: number) => {
    const index = selectedRoles.value.findIndex(r => r.role.id === roleId);
    if (index !== -1) {
        if (selectedRoles.value[index].quantity > 1) {
            selectedRoles.value[index].quantity--;
        } else {
            selectedRoles.value.splice(index, 1);
        }
    }
};

const validateQuantity = (roleId: number, e: Event) => {
    const val = (e.target as HTMLInputElement).value;
    let num = parseInt(val, 10);
    if (isNaN(num) || num < 1) num = 1;
    
    const roleGroup = selectedRoles.value.find(r => r.role.id === roleId);
    if (roleGroup) roleGroup.quantity = num;
};

// Remover rol
const removeRole = (roleId: number) => {
    selectedRoles.value = selectedRoles.value.filter((r) => r.role.id !== roleId);
};

const totalSelectedQuantity = computed(() => {
    return selectedRoles.value.reduce((total, group) => total + group.quantity, 0);
});

// Limpiar todos los roles seleccionados
const clearAllRoles = () => {
    selectedRoles.value = [];
};

// Manejo de teclado en el dropdown
const handleKeydown = (event: KeyboardEvent, role?: Role) => {
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

// Submit mejorado con feedback visual
const submit = async () => {
    if (selectedRoles.value.length === 0) {
        return;
    }

    form.roles_ids = [];
    selectedRoles.value.forEach(group => {
        for (let i = 0; i < group.quantity; i++) {
            form.roles_ids.push(group.role.id);
        }
    });
    
    form.guard_id = props.guard.id;

    isSubmitting.value = true;

    try {
        await form.post(route('guards.roles.store'));

        showSuccess.value = true;
        setTimeout(() => {
            open.value = false;
            
            const flatInstanceArray: any[] = [];
            selectedRoles.value.forEach(group => {
                for (let i = 0; i < group.quantity; i++) {
                    flatInstanceArray.push({ ...group.role, instanceId: Math.random() });
                }
            });
            emit('asignRoles', flatInstanceArray);
            
            form.reset();
            selectedRoles.value = [];
            showSuccess.value = false;
            isSubmitting.value = false;
        }, 1500);
    } catch (error) {
        isSubmitting.value = false;
    }
};

// Control de visibilidad del dropdown
const searchInputRef = ref<InstanceType<typeof Input> | null>(null);
const resultsContainerRef = ref<HTMLElement | null>(null);

const closeDropdown = (event: MouseEvent) => {
    if (
        searchInputRef.value?.$el &&
        !searchInputRef.value.$el.contains(event.target as Node) &&
        resultsContainerRef.value &&
        !resultsContainerRef.value.contains(event.target as Node)
    ) {
        isDropdownVisible.value = false;
    }
};

const openDropdown = () => {
    isDropdownVisible.value = true;
};

watch(open, (isOpen) => {
    if (isOpen) {
        isDropdownVisible.value = false;
        document.addEventListener('click', closeDropdown);
        // Enfocar el input cuando se abre el modal
        nextTick(() => {
            searchInputRef.value?.$el?.focus();
        });

        selectedRoles.value = [];
    } else {
        document.removeEventListener('click', closeDropdown);
        isDropdownVisible.value = false;
        searchTerm.value = '';
        selectedRoles.value = [];
        showSuccess.value = false;
        isSubmitting.value = false;
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger>
            <Button class="h-auto bg-green-500 text-white transition-colors duration-200 hover:bg-green-400">
                <Plus :size="16" class="mr-2" />
                Agregar roles</Button
            >
        </DialogTrigger>

        <DialogContent class="max-w-md sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Plus :size="20" class="text-green-500" />
                    Asignar roles a {{ guard.name }}
                </DialogTitle>
                <p class="mt-1 text-sm text-gray-500">Puedes agregar múltiples instancias del mismo rol</p>
            </DialogHeader>

            <!-- Estado de éxito -->
            <div v-if="showSuccess" class="flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 p-4">
                <CheckCircle2 :size="20" class="flex-shrink-0 text-green-500" />
                <div>
                    <p class="font-medium text-green-800">Roles asignados correctamente</p>
                    <p class="text-sm text-green-600">Se han agregado {{ totalSelectedQuantity }} roles a la guardia</p>
                </div>
            </div>

            <div v-else class="space-y-4">
                <!-- Búsqueda -->
                <div class="relative">
                    <div class="relative">
                        <Search :size="18" class="absolute top-1/2 left-3 -translate-y-1/2 transform text-gray-400" />
                        <Input
                            ref="searchInputRef"
                            placeholder="Buscar roles (ej: Líder, Supervisor...)"
                            v-model="searchTerm"
                            class="w-full pr-10 pl-10"
                            @click="openDropdown"
                            @keydown.esc="isDropdownVisible = false"
                            @keydown="handleKeydown"
                            autocomplete="off"
                        />
                        <button
                            v-if="searchTerm"
                            @click="
                                searchTerm = '';
                                isDropdownVisible = false;
                            "
                            class="absolute top-1/2 right-3 -translate-y-1/2 transform text-gray-400 hover:text-gray-600"
                        >
                            <X :size="16" />
                        </button>
                    </div>

                    <!-- Dropdown de resultados -->
                    <div
                        v-if="isDropdownVisible"
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
                            <span class="flex-1">{{ role.name }}</span>
                            <kbd class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-400">Enter</kbd>
                        </div>

                        <div v-if="filteredRoles.length === 0 && searchTerm" class="p-4 text-center text-gray-500">
                            <AlertCircle :size="20" class="mx-auto mb-2 text-gray-400" />
                            <p>No se encontraron roles</p>
                            <p class="mt-1 text-sm">Intenta con otros términos</p>
                        </div>
                    </div>
                </div>

                <!-- Roles seleccionados -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-700">Roles seleccionados ({{ totalSelectedQuantity }})</h3>
                        <button
                            v-if="selectedRoles.length > 0"
                            @click="clearAllRoles"
                            class="flex items-center gap-1 text-xs text-red-500 hover:text-red-700"
                        >
                            <X :size="12" />
                            Limpiar todos
                        </button>
                    </div>

                    <div
                        class="min-h-[100px] rounded-lg border-2 border-dashed border-gray-200 p-4 transition-colors"
                        :class="{
                            'border-green-300 bg-green-50': selectedRoles.length > 0,
                            'bg-gray-50': selectedRoles.length === 0,
                        }"
                    >
                        <div v-if="selectedRoles.length === 0" class="py-6 text-center">
                            <Search :size="32" class="mx-auto mb-2 text-gray-300" />
                            <p class="text-sm text-gray-500">Busca y selecciona roles para agregar</p>
                            <p class="mt-1 text-xs text-gray-400">Usa las flechas y Enter para seleccionar rápido</p>
                        </div>

                        <div class="flex flex-col gap-2">
                            <div
                                v-for="group in selectedRoles"
                                :key="group.role.id"
                                class="flex items-center justify-between rounded bg-white px-3 py-2 shadow-sm border border-green-200"
                            >
                                <span class="font-medium text-gray-800">{{ group.role.name }}</span>
                                
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center rounded border border-gray-300 bg-gray-50 overflow-hidden">
                                        <button @click="decreaseQuantity(group.role.id)" class="px-2 py-1 text-gray-600 hover:bg-gray-200 hover:text-gray-900 focus:outline-none transition-colors">
                                            -
                                        </button>
                                        <input 
                                            type="number" 
                                            :value="group.quantity"
                                            @change="(e) => validateQuantity(group.role.id, e)"
                                            min="1"
                                            class="w-12 border-x border-gray-300 bg-white py-1 text-center text-sm focus:outline-none"
                                        />
                                        <button @click="increaseQuantity(group.role.id)" class="px-2 py-1 text-gray-600 hover:bg-gray-200 hover:text-gray-900 focus:outline-none transition-colors">
                                            +
                                        </button>
                                    </div>

                                    <button @click="removeRole(group.role.id)" class="text-red-400 hover:text-red-600 transition-colors" title="Remover rol">
                                        <X :size="18" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contador y estadísticas -->
                <div class="flex items-center justify-between text-sm text-gray-500">
                    <span>
                        {{ totalSelectedQuantity }} rol{{ totalSelectedQuantity !== 1 ? 'es' : '' }} en total
                    </span>
                    <span v-if="selectedRoles.length > 0" class="font-medium text-green-600"> Listo para asignar </span>
                </div>
            </div>

            <DialogFooter class="flex gap-2 sm:gap-0">
                <Button variant="outline" @click="open = false" :disabled="isSubmitting" class="flex-1 sm:flex-none"> Cancelar </Button>
                <Button
                    type="submit"
                    @click="submit"
                    :disabled="form.processing || selectedRoles.length === 0 || isSubmitting"
                    class="flex-1 bg-green-500 text-white transition-all duration-200 hover:bg-green-600 disabled:cursor-not-allowed disabled:bg-gray-300 sm:flex-none"
                    :class="{
                        'cursor-not-allowed opacity-50': selectedRoles.length === 0,
                        'animate-pulse': isSubmitting,
                    }"
                >
                    <template v-if="isSubmitting">
                        <div class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></div>
                        Procesando...
                    </template>
                    <template v-else>
                        <Plus :size="16" class="mr-2" />
                        Agregar {{ totalSelectedQuantity }} rol{{ totalSelectedQuantity !== 1 ? 'es' : '' }}
                    </template>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
/* Animaciones suaves */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Scrollbar personalizado */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Ocultar flechas del input number nativo */
input[type=number]::-webkit-inner-spin-button, 
input[type=number]::-webkit-outer-spin-button { 
  -webkit-appearance: none; 
  margin: 0; 
}
input[type=number] {
  -moz-appearance: textfield;
}
</style>
