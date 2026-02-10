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
}
</script>

<template>
    <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2 lg:grid-cols-2">
            <div
                class="group overflow-hidden rounded-lg border-l-4 border-orange-400 bg-white shadow-sm transition-all hover:-translate-y-0.5 hover:border-orange-500 hover:shadow-md"
                v-for="service in services"
                :key="service.id"
            >
                <div class="p-4">
                    <div class="flex items-start justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-lg font-semibold text-gray-800" :title="service.name">{{ service.name }}</p>
                            <p class="mt-1 text-sm text-gray-500">
                                Código: <span class="font-mono">{{ service.code }}</span>
                            </p>
                            <div class="mt-3">
                                <div class="flex w-full items-center space-x-3">
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
                            </div>
                        </div>
                        <div class="ml-2 flex w-[50%] flex-col items-end">
                            <span
                                class="rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800 transition-colors group-hover:bg-green-200"
                                >S./{{ service.pivot.price }}</span
                            >
                            <div class="mt-1 flex flex-col items-end">
                                <label for="quantity" class="text-dark text-sm font-medium">Cantidad</label>
                                <Input id="quantity" type="number" class="w-[60%]" v-model="quantities[service.id]" min="1" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
