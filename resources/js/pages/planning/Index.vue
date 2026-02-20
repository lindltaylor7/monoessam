<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { Cafe, WeeklyProgram, Dish, DishCategory, MenuStructure, MealType } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ref, computed } from 'vue';
import dayjs from 'dayjs';
import 'dayjs/locale/es';

dayjs.locale('es');

interface Props {
    cafes: Cafe[];
    programs: WeeklyProgram[];
    dish_categories: DishCategory[];
    menu_structure: MenuStructure[];
    dishes: Dish[];
}

const props = defineProps<Props>();

const meals: MealType[] = ['Desayuno', 'Almuerzo', 'Cena', 'Refrigerio'];
const startDate = ref(dayjs().startOf('week').add(1, 'day').format('YYYY-MM-DD'));

// Local reactive state for the grid to avoid find() in template
const portionsGrid = ref<Record<string, number>>({});
const itemsGrid = ref<Record<string, string>>({});

const form = useForm({
    cafe_id: '',
    start_date: startDate.value,
    end_date: dayjs(startDate.value).add(6, 'days').format('YYYY-MM-DD'),
    items: [] as any[],
    portions: [] as any[],
});

const dates = computed(() => {
    return Array.from({ length: 7 }, (_, i) => dayjs(form.start_date).add(i, 'days').format('YYYY-MM-DD'));
});

// Initialize items and portions
const initializeGrid = () => {
    portionsGrid.value = {};
    itemsGrid.value = {};
    
    dates.value.forEach(date => {
        meals.forEach(meal => {
            const portKey = `${date}_${meal}`;
            portionsGrid.value[portKey] = 0;
            
            const structureForMeal = props.menu_structure.filter(s => s.meal_type === meal);
            structureForMeal.forEach(s => {
                const itemKey = `${date}_${meal}_${s.dish_category_id}`;
                itemsGrid.value[itemKey] = '';
            });
        });
    });
};

// Reset grid when date changes
const handleDateChange = () => {
    form.end_date = dayjs(form.start_date).add(6, 'days').format('YYYY-MM-DD');
    initializeGrid();
};

initializeGrid();

const submit = () => {
    // Sync local grid to form arrays before posting
    form.portions = [];
    form.items = [];

    for (const [key, val] of Object.entries(portionsGrid.value)) {
        const [date, meal] = key.split('_');
        form.portions.push({
            date,
            meal_type: meal as MealType,
            portions_count: val
        });
    }

    for (const [key, val] of Object.entries(itemsGrid.value)) {
        const [date, meal, catId] = key.split('_');
        // Only push if a dish is selected OR we want to send the requirement anyway
        // For now, let's keep all entries but send null/nullish if empty
        form.items.push({
            date,
            meal_type: meal as MealType,
            dish_category_id: parseInt(catId),
            dish_id: val || null
        });
    }

    form.post(route('planning.store'));
};

const getDishesByCategory = (categoryId: number) => {
    return props.dishes.filter(d => 
        d.dish_categories?.some((c: any) => c.id === categoryId)
    );
};

</script>

<template>
    <Head title="Planificación Semanal" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Planificación Semanal</h1>
                <Link :href="route('purchase_orders.index')">
                    <Button variant="outline" class="rounded-xl">Ver Órdenes de Compra</Button>
                </Link>
            </div>

            <Card class="rounded-2xl border-none shadow-sm">
                <CardHeader>
                    <CardTitle>Nueva Programación</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium">Café / Comedor</label>
                            <Select v-model="form.cafe_id">
                                <SelectTrigger class="rounded-xl">
                                    <SelectValue placeholder="Seleccionar Café" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="cafe in cafes" :key="cafe.id" :value="cafe.id.toString()">
                                        {{ cafe.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium">Fecha de Inicio</label>
                            <Input type="date" v-model="form.start_date" @change="handleDateChange" class="rounded-xl" />
                        </div>
                        <div class="flex items-end">
                            <Button @click="submit" :disabled="form.processing" class="w-full rounded-xl bg-primary text-primary-foreground">
                                Guardar Planificación
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="overflow-x-auto rounded-2xl border bg-card shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-[150px]">Comida / Día</TableHead>
                            <TableHead v-for="date in dates" :key="date" class="text-center">
                                <div class="capitalize">{{ dayjs(date).format('dddd') }}</div>
                                <div class="text-xs text-muted-foreground">{{ dayjs(date).format('DD/MM') }}</div>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-for="meal in meals" :key="meal">
                            <TableRow class="bg-muted/30">
                                <TableCell class="font-bold underline">{{ meal }}</TableCell>
                                <TableCell v-for="date in dates" :key="date">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[10px] uppercase text-muted-foreground">Raciones</label>
                                        <Input 
                                            type="number" 
                                            v-model="portionsGrid[`${date}_${meal}`]"
                                            class="h-8 rounded-lg text-center"
                                        />
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="struct in menu_structure.filter(s => s.meal_type === meal)" :key="struct.id">
                                <TableCell class="pl-6 text-sm italic text-muted-foreground">
                                    {{ struct.dish_category?.name || 'Categoría' }}
                                </TableCell>
                                <TableCell v-for="date in dates" :key="date">
                                    <Select v-model="itemsGrid[`${date}_${meal}_${struct.dish_category_id}`]">
                                        <SelectTrigger class="h-9 rounded-xl text-xs">
                                            <SelectValue placeholder="Elegir plato" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="dish in getDishesByCategory(struct.dish_category_id)" :key="dish.id" :value="dish.id.toString()">
                                                {{ dish.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>
            </div>
            
            <div class="mt-8">
                <h2 class="mb-4 text-xl font-bold">Programaciones Guardadas</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="program in programs" :key="program.id" class="rounded-2xl border-none shadow-sm">
                        <CardHeader>
                            <CardTitle class="text-lg">{{ program.cafe?.name }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col gap-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Periodo:</span>
                                    <span>{{ dayjs(program.start_date).format('DD/MM') }} - {{ dayjs(program.end_date).format('DD/MM') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Estado:</span>
                                    <span class="capitalize">{{ program.status }}</span>
                                </div>
                                <Button 
                                    @click="router.post(route('planning.generate-po', program.id))" 
                                    class="mt-4 rounded-xl" 
                                    variant="secondary"
                                >
                                    Generar Quebrado (PO)
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
