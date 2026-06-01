<script setup lang="ts">
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import Input from '@/components/ui/input/Input.vue';
import { Service } from '@/types';
import { ref, watch } from 'vue';

const props = defineProps({
    services: {
        type: Array as () => Service[],
        required: true,
    },
});

const quantities = ref<Record<number, number>>({});

watch(
    () => props.services,
    (newServices) => {
        quantities.value = newServices.reduce(
            (acc, service) => {
                acc[service.id] = quantities.value[service.id] ?? 1;
                return acc;
            },
            {} as Record<number, number>,
        );
    },
    { immediate: true },
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
    <div class="custom-scrollbar divide-y divide-slate-100 overflow-y-auto">
        <div
            v-for="service in services"
            :key="service.id"
            class="group flex items-center gap-3 px-4 py-3 transition-colors hover:bg-slate-50/80"
        >
            <!-- Checkbox -->
            <Checkbox
                :id="'service-' + service.id"
                class="h-5 w-5 shrink-0 rounded data-[state=checked]:border-emerald-600 data-[state=checked]:bg-emerald-600"
                @click="addServiceSelected(service)"
            />

            <!-- Info -->
            <label :for="'service-' + service.id" class="min-w-0 flex-1 cursor-pointer">
                <p class="truncate text-sm font-semibold text-slate-800">{{ service.name }}</p>
                <p class="text-[10px] font-medium text-slate-400">Código: {{ service.code }}</p>
            </label>

            <!-- Precio + Cantidad -->
            <div class="flex shrink-0 items-center gap-2">
                <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">
                    S/.{{ service.pivot.price }}
                </span>
                <Input
                    :id="'qty-' + service.id"
                    type="number"
                    class="h-8 w-16 text-center text-xs"
                    v-model="quantities[service.id]"
                    min="1"
                    placeholder="Cant."
                />
            </div>
        </div>

        <div v-if="services.length === 0" class="flex flex-col items-center justify-center gap-2 py-10 text-slate-300">
            <span class="text-xs font-medium italic">Sin servicios disponibles</span>
        </div>
    </div>
</template>
