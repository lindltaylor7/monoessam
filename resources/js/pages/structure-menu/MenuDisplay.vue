<script setup lang="ts">
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useHeadcountSelection } from '@/composables/useHeadcountSelection';
import { Mine } from '@/types';
import { watch } from 'vue';

interface Props {
    mines: Mine[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:serviceable', value: string | null): void;
}>();

const { selectedOptions, selectedUnits, selectedCafes, selectedServices } = useHeadcountSelection(props.mines);

watch(
    () => selectedOptions.value.service,
    (newVal) => {
        emit('update:serviceable', newVal);
    },
);
</script>

<template>
    <div class="border-sidebar-border/70 dark:border-sidebar-border relative col-span-1 flex flex-col gap-4 overflow-hidden rounded-xl border p-4">
        <div class="flex flex-col gap-0.5">
            <p class="text-sm font-semibold">Paso 1 · Seleccione mina, unidad, comedor y servicio</p>
            <p class="text-muted-foreground text-xs">
                La estructura de menú se guarda asociada al servicio elegido. Si el servicio ya tiene una estructura, se cargará automáticamente.
            </p>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <Select class="w-full" v-model="selectedOptions.mine">
                <SelectTrigger class="w-full">
                    <SelectValue placeholder="Selecciona una mina" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectLabel>Minas</SelectLabel>
                        <SelectItem v-for="mine in mines" :value="String(mine.id)" :key="mine.id">
                            {{ mine.name }}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>

            <Select class="w-full" v-model="selectedOptions.unit">
                <SelectTrigger class="w-full">
                    <SelectValue placeholder="Selecciona una unidad minera" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectLabel>Unidades mineras</SelectLabel>
                        <SelectItem v-for="unit in selectedUnits" :value="String(unit.id)" :key="unit.id">
                            {{ unit.name }}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>

            <Select class="w-full" v-model="selectedOptions.cafe">
                <SelectTrigger class="w-full">
                    <SelectValue placeholder="Selecciona un comedor" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectLabel>Comedores</SelectLabel>
                        <SelectItem v-for="cafe in selectedCafes" :value="String(cafe.id)" :key="cafe.id">
                            {{ cafe.name }}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>

            <Select class="w-full" v-model="selectedOptions.service">
                <SelectTrigger class="w-full">
                    <SelectValue placeholder="Selecciona un servicio" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectLabel>Servicios</SelectLabel>
                        <SelectItem v-for="service in selectedServices" :value="String(service.pivot.id)" :key="service.pivot.id">
                            {{ service.name }}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
        </div>
    </div>
</template>
