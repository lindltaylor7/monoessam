<script setup lang="ts">
import { DateFormatter, type DateValue, getLocalTimeZone } from '@internationalized/date';
import { CalendarIcon } from 'lucide-vue-next';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { ref, watch } from 'vue';

const df = new DateFormatter('es-ES', {
    dateStyle: 'long',
});

const value = ref<DateValue>();

const emit = defineEmits(['updateDate']);

watch(value, (dateValue) => {
    if (!dateValue) return;
    const formattedDate = `${dateValue.year}-${String(dateValue.month).padStart(2, '0')}-${String(dateValue.day).padStart(2, '0')}`;

    emit('updateDate', formattedDate);
});
</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
            <Button
                variant="outline"
                :class="cn('w-full h-11 justify-start text-left font-bold text-xs rounded-xl border-slate-200 hover:bg-slate-50 transition-all', !value && 'text-slate-400')"
            >
                <CalendarIcon class="mr-2 h-4 w-4 text-blue-500" />
                {{ value ? df.format(value.toDate(getLocalTimeZone())) : 'Selecciona fecha' }}
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto p-0">
            <Calendar v-model="value" initial-focus locale="es-ES" />
        </PopoverContent>
    </Popover>
</template>
