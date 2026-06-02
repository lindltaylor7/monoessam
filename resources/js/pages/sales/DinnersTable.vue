<script setup lang="ts">
import Input from '@/components/ui/input/Input.vue';
import { Service } from '@/types';
import { CheckCircle2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps({
    services: {
        type: Array as () => Service[],
        required: true,
    },
});

const quantities = ref<Record<number, number>>({});
const checkedState = ref<Record<number, boolean>>({});

watch(
    () => props.services,
    (newServices) => {
        checkedState.value = {};
        quantities.value = newServices.reduce(
            (acc, service) => {
                acc[service.id] = 1;
                return acc;
            },
            {} as Record<number, number>,
        );
    },
    { immediate: true },
);

const emits = defineEmits(['addServiceSelected']);

function handleRowClick(service: Service, event: Event) {
    if ((event.target as HTMLElement).closest('[data-no-toggle]')) return;
    toggleService(service);
}

function toggleService(service: Service) {
    const id = service.id;
    const nowChecked = !checkedState.value[id];
    checkedState.value[id] = nowChecked;

    emits('addServiceSelected', {
        ...service,
        quantity: quantities.value[id] || 1,
    });

    if (!nowChecked) {
        quantities.value[id] = 1;
    }
}
</script>

<template>
    <div class="custom-scrollbar divide-y overflow-y-auto">
        <div
            v-for="service in services"
            :key="service.id"
            class="relative flex cursor-pointer items-center gap-3 px-4 py-3 transition-all duration-150 select-none"
            :class="
                checkedState[service.id]
                    ? 'bg-emerald-500 hover:bg-emerald-500/90'
                    : 'divide-slate-100 hover:bg-slate-50/80'
            "
            @click="handleRowClick(service, $event)"
        >
            <!-- Indicador lateral izquierdo cuando está seleccionado -->
            <div
                class="absolute top-0 left-0 h-full w-1 rounded-r transition-all duration-150"
                :class="checkedState[service.id] ? 'bg-emerald-300' : 'bg-transparent'"
            />

            <!-- Info -->
            <div class="min-w-0 flex-1 pl-1">
                <p
                    class="truncate text-sm font-semibold transition-colors"
                    :class="checkedState[service.id] ? 'text-white' : 'text-slate-800'"
                >
                    {{ service.name }}
                </p>
                <p
                    class="text-[10px] font-medium transition-colors"
                    :class="checkedState[service.id] ? 'text-emerald-100' : 'text-slate-400'"
                >
                    Código: {{ service.code }}
                </p>
            </div>

            <!-- Precio + Cantidad -->
            <div class="flex shrink-0 items-center gap-2" data-no-toggle>
                <span
                    class="rounded-lg px-2.5 py-1 text-xs font-bold transition-colors"
                    :class="checkedState[service.id] ? 'bg-white/20 text-white' : 'bg-emerald-50 text-emerald-700'"
                >
                    S/.{{ service.pivot.price }}
                </span>
                <Input
                    type="number"
                    class="h-8 w-16 text-center text-xs transition-colors"
                    :class="checkedState[service.id] ? 'border-white/30 bg-white/20 text-white placeholder:text-white/50' : ''"
                    v-model="quantities[service.id]"
                    min="1"
                    placeholder="Cant."
                    @click.stop
                />
            </div>

            <!-- Icono check -->
            <CheckCircle2
                class="h-4 w-4 shrink-0 transition-all duration-150"
                :class="checkedState[service.id] ? 'text-white opacity-100' : 'opacity-0'"
            />
        </div>

        <div v-if="services.length === 0" class="flex flex-col items-center justify-center gap-2 py-10 text-slate-300">
            <span class="text-xs font-medium italic">Sin servicios disponibles</span>
        </div>
    </div>
</template>
