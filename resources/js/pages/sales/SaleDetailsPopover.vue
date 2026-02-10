<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { BetweenHorizonalStart } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    ticket: object;
}>();

const popoverOpen = ref(false);

// Función para calcular el total del ticket
const totalTicket = computed(() => {
    const total = props.ticket?.ticket_details?.reduce((sum, detail) => sum + parseFloat(detail.total), 0) || 0;
    console.log(total);
    return parseFloat(total).toFixed(2); // Formatear a dos decimales
});
</script>

<template>
    <Popover v-model:open="popoverOpen">
        <PopoverTrigger asChild>
            <Button variant="ghost" size="sm" class="text-blue-600 hover:bg-blue-50" aria-label="Ver detalles del ticket">
                <span class="ml-2 hidden sm:inline">Detalles</span>
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-80 p-0" align="start">
            <div class="p-4">
                
                <h3 class="mb-3 flex items-center text-lg font-semibold">
                    <BetweenHorizonalStart class="mr-2 h-4 w-4" />
                    Detalles del Ticket
                </h3>

                <div class="space-y-3">
                    <div
                        v-for="detail in props.ticket?.ticket_details"
                        :key="detail.id"
                        class="flex items-center justify-between border-b py-2 last:border-b-0"
                    >
                        <div>
                            <p class="font-medium">{{ detail.service_name }}</p>
                            <p class="text-sm text-gray-500">Cantidad: {{ detail.amount }}</p>
                        </div>
                        <span class="font-semibold">S./ {{ parseFloat(detail.total).toFixed(2) }}</span>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between border-t pt-3">
                    <span class="font-medium">Total:</span>
                    <span class="text-lg font-bold">S./ {{ totalTicket }}</span>
                </div>
            </div>

            <div class="border-t bg-gray-50 px-4 py-2 text-right">
                <Button variant="ghost" size="sm" @click="popoverOpen = false" class="text-blue-600 hover:bg-blue-100"> Cerrar </Button>
            </div>
        </PopoverContent>
    </Popover>
</template>
