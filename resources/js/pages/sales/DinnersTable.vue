<script setup lang="ts">
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import Input from '@/components/ui/input/Input.vue';
import { Service } from '@/types';
import { ref } from 'vue';

const props = defineProps({
    services: {
        type: Array as () => Service[],
        required: true,
    },
});

// Objeto para almacenar las cantidades de cada servicio
const quantities = ref<Record<number, number>>(
    props.services.reduce(
        (acc, service) => {
            acc[service.id] = 1; // Valor inicial 1 para cada servicio
            return acc;
        },
        {} as Record<number, number>,
    ),
);

const emits = defineEmits(['addServiceSelected']);

function addServiceSelected(service: Service) {
    const serviceWithQuantity = {
        ...service,
        quantity: quantities.value[service.id] || 1,
    };
    emits('addServiceSelected', serviceWithQuantity);

    quantities.value[service.id] = 1; // Reiniciar cantidad a 1 después de agregar
}
</script>

<template>
    <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-1 lg:grid-cols-1">
            <div
                class="group overflow-hidden rounded-lg border-l-4 border-orange-400 bg-white shadow-sm transition-all hover:-translate-y-0.5 hover:border-orange-500 hover:shadow-md"
                v-for="service in services"
                :key="service.id"
            >
                <div class="p-3">
                    <!-- Name (full width, no truncation) -->
                    <p class="text-base leading-snug font-semibold text-gray-800">{{ service.name }}</p>
                    <p class="mt-0.5 text-xs text-gray-400">
                        Código: <span class="font-mono">{{ service.code }}</span>
                    </p>

                    <!-- Actions row -->
                    <div class="mt-3 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="terms"
                                class="h-5 w-5 rounded border-gray-300 text-green-600 focus:ring-green-500 data-[state=checked]:border-gray-300 data-[state=checked]:bg-green-600"
                                @click="addServiceSelected(service)"
                            />
                            <label
                                for="terms"
                                class="text-dark cursor-pointer text-sm leading-none font-medium transition-colors hover:text-green-600"
                            >
                                Seleccionar
                            </label>
                        </div>

                        <div class="flex items-center gap-3">
                            <span
                                class="rounded-full bg-green-100 px-3 py-1 text-sm font-semibold text-green-800 transition-colors group-hover:bg-green-200"
                            >
                                S./{{ service.pivot.price }}
                            </span>
                            <Input id="quantity" type="number" class="w-20" v-model="quantities[service.id]" min="1" placeholder="Cant." />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
