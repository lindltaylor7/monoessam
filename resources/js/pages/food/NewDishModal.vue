<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Cafe, Headquarter } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash } from 'lucide-vue-next';
import { ref } from 'vue';
import CalcPopover from './CalcPopover.vue';

const props = defineProps({
    cafes: {
        type: Array as () => Cafe[],
        required: true,
    },
    headquarters: {
        type: Array as () => Headquarter[],
        required: true,
    },
    ingredients: {
        type: Array,
    },
});

const optionUnits = ['Mililitros', 'Gramos'];

const totalGrossWeight = ref(0.0);
const totalSolidWaste = ref(0.0);
const totalLiquidWaste = ref(0.0);
const totalCalories = ref(0.0);
const totalCost = ref(0.0);
const totalfinalProduct = ref(0.0);

const open = ref(false);

const ingredientsFounded = ref([]);

const form = useForm({
    name: '',
    description: '',
    mesearument_unit: '',
    ingredients: [],
});

const submit = () => {
    form.post(route('dish-category-insert'), {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
};

const calcMassiveProperties = (id, calcArray) => {
    const operationTypes = [
        {
            property: 'gross_weight',
            totalProperty: totalGrossWeight,
            resultCalc: calcArray[0],
        },
        {
            property: 'solid_waste',
            totalProperty: totalSolidWaste,
            resultCalc: calcArray[1],
        },
        {
            property: 'liquid_waste',
            totalProperty: totalLiquidWaste,
            resultCalc: calcArray[2],
        },
        {
            property: 'calories',
            totalProperty: totalCalories,
            resultCalc: calcArray[3],
        },
        {
            property: 'cost',
            totalProperty: totalCost,
            resultCalc: calcArray[4],
        },
        {
            property: 'final_product',
            totalProperty: totalfinalProduct,
            resultCalc: calcArray[5],
        },
    ];

    const ingredientIndex = form.ingredients.findIndex((ing) => ing.id == id);

    operationTypes.forEach((operation) => {
        if (ingredientIndex !== -1) {
            form.ingredients[ingredientIndex][operation.property] = operation.resultCalc;
            operation.totalProperty.value = form.ingredients.reduce((sum, ingredient) => {
                return sum + (parseFloat(ingredient[operation.property]) || 0);
            }, 0);
        }
    });
};

const selectIngredient = (ingredient) => {
    ingredient.originalValues = {
        liquid_waste: ingredient.liquid_waste,
        solid_waste: ingredient.solid_waste,
        calories: ingredient.calories,
    };
    form.ingredients = [...(form.ingredients || []), ingredient];
};

const deleteIngredient = (id) => {
    form.ingredients = form.ingredients.filter((ingredient) => ingredient.id !== id);
};

const searchIngredients = (e: Event) => {
    if (e.target.value == '') ingredientsFounded.value = [];
    else ingredientsFounded.value = props.ingredients?.filter((ingredient) => ingredient.name.toLowerCase().includes(e.target.value)).slice(0, 10);
};

const updateValues = (ingredientID, e: Event) => {
    console.log(ingredientID, e.target.value);
    const multiplier = parseFloat(e.target.value);
    const ingredientSelected = form.ingredients.find((ingredient) => ingredient.id == ingredientID);

    if (ingredientSelected) {
        // Si no existen los valores originales, guardarlos primero
        if (!ingredientSelected.originalValues) {
            ingredientSelected.originalValues = {
                liquid_waste: ingredientSelected.liquid_waste,
                solid_waste: ingredientSelected.solid_waste,
                calories: ingredientSelected.calories,
            };
        }

        // Calcular los nuevos valores basados en los originales
        ingredientSelected.liquid_waste = ingredientSelected.originalValues.liquid_waste * multiplier;
        ingredientSelected.solid_waste = ingredientSelected.originalValues.solid_waste * multiplier;
        ingredientSelected.calories = ingredientSelected.originalValues.calories * multiplier;
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger
            ><Button title="Agregar Area" class="h-full w-auto bg-green-400"><Plus /></Button
        ></DialogTrigger>
        <DialogContent class="w-full max-w-[90vw] lg:max-w-[1200px]">
            <DialogHeader>
                <DialogTitle>Nuevo Plato</DialogTitle>
                <Input v-model="form.name" type="text" placeholder="Nombre" />
                <Input v-model="form.description" type="text" placeholder="Descripción" />
            </DialogHeader>
            <div class="flex w-full flex-row">
                <Select v-model="form.mesearument_unit">
                    <SelectTrigger>
                        <SelectValue placeholder="Seleccione nivel de Plato" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectLabel>Unidades</SelectLabel>
                            <SelectItem value="1">Master</SelectItem>
                            <SelectItem value="2">Staff</SelectItem>
                            <SelectItem value="3">Empleado</SelectItem>
                            <SelectItem value="4">Obrero</SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>
            <div class="flex flex-col">
                <Input type="text" placeholder="Buscar ingrediente" @keyup="searchIngredients"></Input>
                <div class="grid grid-cols-3 gap-4">
                    <div class="">
                        <Button
                            v-for="ingredient in ingredientsFounded"
                            :key="ingredient.id"
                            class="my-1 w-full bg-red-400"
                            :title="ingredient.name"
                            @click="selectIngredient(ingredient)"
                            >{{ ingredient.name.length > 35 ? ingredient.name.substring(0, 35) + '...' : ingredient.name }}</Button
                        >
                    </div>
                    <div class="col-span-2">
                        <Table>
                            <TableCaption>Lista de Categoría de Platos.</TableCaption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-[200px]">Nombre</TableHead>
                                    <TableHead class="w-[100px]">Descripción</TableHead>
                                    <TableHead class="w-[100px]">Precio unitario</TableHead>
                                    <TableHead class="text-right">Opciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="ingredient in form.ingredients" :key="ingredient.id">
                                    <TableCell class="font-medium">{{ ingredient.name }}</TableCell>
                                    <TableCell class="font-medium">{{ ingredient.description }}</TableCell>
                                    <TableCell class="font-medium">{{ ingredient.presentation }}</TableCell>
                                    <TableCell class="text-right">
                                        <Button @click="deleteIngredient(ingredient.id)" size="sm" class="bg-red-500"
                                            ><Trash class="h-4 w-4"
                                        /></Button>
                                        <CalcPopover
                                            :ingredient="ingredient"
                                            :totalMateriaPrima="totalGrossWeight"
                                            :totalSolidWaste="totalSolidWaste"
                                            :totalLiquidWaste="totalLiquidWaste"
                                            :totalCalories="totalCalories"
                                            :totalCost="totalCost"
                                            :totalfinalProduct="totalfinalProduct"
                                            @calcMassiveProperties="calcMassiveProperties"
                                        />
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>
            <div class="flex w-full flex-col">
                <p>Total Materia Prima: {{ totalGrossWeight }} gramos</p>
                <p>Total Desechos solidos: {{ totalSolidWaste }} gramos</p>
                <p>Total Desechos liquidos: {{ totalLiquidWaste }} gramos</p>
                <p>Total Calorias: {{ totalCalories }}</p>
                <p>Costo total: S./ {{ totalCost }}</p>
                <p>Producto Final: {{ totalfinalProduct }}</p>
            </div>
            <DialogFooter @click="submit"> Agregar </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
