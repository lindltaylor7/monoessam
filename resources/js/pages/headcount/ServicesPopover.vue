<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    services: any[];
    business: {};
}>();

const popoverOpen = ref(false);
const selectedServices = ref<Record<number, boolean>>({});

// Inicializar los permisos seleccionados basados en el rol actual
watch(
    () => props.business,
    (business) => {
        selectedServices.value = {};
        if (business.services) {
            business.services.forEach((service) => {
                selectedServices.value[service.id] = true;
            });
        }
    },
    { immediate: true },
);

const form = useForm({
    businessId: 0,
    services: {} as Record<number, boolean>,
});

function onServiceChange(id: number, checked: boolean) {
    selectedServices.value[id] = checked;
}

const sendServices = () => {
    popoverOpen.value = false;
    form.businessId = props.business.id;
    form.services = selectedServices.value;
    form.post(route('businessServices'), {
        onSuccess: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <Popover v-model:open="popoverOpen">
        <PopoverTrigger asChild>
            <Button variant="outline" class="flex items-center gap-2"> Ver servicios </Button>
        </PopoverTrigger>
        <PopoverContent class="w-64 space-y-3 p-4">
            <div class="max-h-64 space-y-2 overflow-y-auto">
                <div v-for="service in services" :key="service.id" class="flex items-center space-x-2 rounded p-2 transition-colors hover:bg-gray-50">
                    <input
                        class="text-primary focus:ring-primary h-4 w-4 rounded border-gray-300"
                        type="checkbox"
                        :id="`service-${service.id}`"
                        :checked="selectedServices[service.id] || false"
                        @change="onServiceChange(service.id, $event.target.checked)"
                    />
                    <label class="cursor-pointer text-sm font-medium text-gray-700" :for="`service-${service.id}`">
                        {{ service.name }}
                    </label>
                </div>
            </div>

            <Button @click="sendServices" class="mt-2 w-full"> Sincronizar</Button>
        </PopoverContent>
    </Popover>
</template>
