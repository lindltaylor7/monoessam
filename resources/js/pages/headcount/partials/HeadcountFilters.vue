<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Cafe, Mine, Unit } from '@/types';
import { Sheet, List } from 'lucide-vue-next';
import GuardModal from '../GuardModal.vue';

interface Props {
    mines: Mine[];
    selectedUnits: Unit[];
    selectedCafes: Cafe[];
    selectedOptions: {
        mine: string | null;
        unit: string | null;
        cafe: string | null;
    };
    changedView: boolean;
}

defineProps<Props>();
const emit = defineEmits(['update:selectedOptions', 'toggle-view', 'export-excel', 'assign-guards']);

// Helper to emit updates. Since selectedOptions is an object ref passed down, 
// direct mutation might work if it's the same object, but proper v-model pattern is better.
// However, given the structure, we will rely on v-model binding of internal properties if we pass the object.
// But we cannot v-model complex objects easily without deeper nesting.
// Easier approach: The parent passed a reactive object. We can bind directly to its properties if we are careful,
// OR we emit regular events.
// Let's use direct binding to the object properties for simplicity here given it's a ref object in parent.
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex w-full flex-row gap-2">
            <Button title="Ver en lista" class="cursor-pointer" @click="$emit('toggle-view')">
                <List v-if="!changedView" />
                <Sheet v-else /> <!-- Icono alternativo si quisieras indicar volver -->
            </Button>
            <Button class="cursor-pointer bg-green-500" title="Exportar Headcount en Excel" @click="$emit('export-excel')">
                <Sheet />
            </Button>
        </div>
        
        <p>Seleccione una mina, unidad y comedor para asignar guardias y roles</p>
        
        <div class="flex flex-col md:flex-row gap-2">
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
            <GuardModal 
                :cafeId="Number(selectedOptions.cafe) || 0" 
                @assignGuards="(guards) => $emit('assign-guards', guards)" 
            />
        </div>
    </div>
</template>
