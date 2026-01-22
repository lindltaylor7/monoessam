<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Cafe, Headquarter } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

// Define el tipo para cada guardia, ahora solo requiere un nombre
interface Guardia {
    name: string;
}

// Opcionalmente, mantén estos tipos si son necesarios para otros campos del formulario
interface Cafe {
    id: number;
    name: string;
}
interface Headquarter {
    id: number;
    name: string;
}

const props = defineProps({
    cafeId: {
        type: Number,
        required: false,
    },
});

const emit = defineEmits<{
    (e: 'assignGuards', guards: Guardia[]): void;
}>();

const open = ref(false);

// 1. Estado para el número de guardias seleccionado (2, 3 o 4)
const numberOfGuards = ref<number | null>(null);

// 2. Definición del formulario de Inertia
const form = useForm({
    cafe_id: 0,
    guards: [],
});

// 4. Array de opciones para el select (de 2 a 4)
const guardOptions = [2, 3, 4];

// 5. Watcher para inicializar/actualizar el array de guardias
// Esto añade o quita objetos { name: '' } del array `form.guards`
watch(
    numberOfGuards,
    (newVal) => {
        if (newVal === null) {
            form.guards = [];
            return;
        }

        const count = newVal as number;
        const currentLength = form.guards.length;

        if (count > currentLength) {
            // Añadir nuevas guardias, inicializando solo con el nombre vacío
            for (let i = currentLength; i < count; i++) {
                form.guards.push({ name: '' });
            }
        } else if (count < currentLength) {
            // Eliminar guardias sobrantes
            form.guards.splice(count);
        }
    },
    { immediate: true },
);

const submit = () => {
    // Validación 1: Asegurar que se haya seleccionado un número
    if (numberOfGuards.value === null) {
        alert('Por favor, selecciona el número de guardias a crear (2 a 4).');
        return;
    }

    // Validación 2: Asegurar que todos los campos de nombre estén llenos
    if (form.guards.some((g) => !g.name.trim())) {
        alert('Por favor, completa el nombre para todas las guardias asignadas.');
        return;
    }

    form.cafe_id = props.cafeId;

    // Envío del formulario con Inertia
    form.post(route('guards.store'), {
        onSuccess: (page) => {
            open.value = false;
            emit('assignGuards', page.props.flash.newGuards);
            form.reset();
            numberOfGuards.value = null;
        },
        onError: (errors) => {
            console.error('Error al enviar el formulario:', errors);
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger>
            <Button class="h-full w-auto bg-green-500 text-white hover:bg-green-600"> Asignar Guardias</Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Asignación de Guardias</DialogTitle>
            </DialogHeader>

            <div class="space-y-4">
                <Label for="num-guards">Número de Guardias a asignar (2 a 4)</Label>
                <select id="num-guards" v-model.number="numberOfGuards" class="w-full rounded-md border border-gray-300 p-2">
                    <option :value="null" disabled>Selecciona la cantidad de guardias</option>
                    <option v-for="num in guardOptions" :key="num" :value="num">{{ num }} Guardias</option>
                </select>
            </div>

            <hr v-if="numberOfGuards" class="my-2" />

            <div v-if="numberOfGuards" class="max-h-60 space-y-4 overflow-y-auto">
                <div v-for="(guardia, index) in form.guards" :key="index" class="rounded-md border p-3">
                    <p class="mb-2 font-semibold">Guardia #{{ index + 1 }}</p>

                    <div class="grid grid-cols-4 items-center gap-4">
                        <Label :for="`guard-name-${index}`" class="col-span-1">Nombre:</Label>
                        <Input
                            :id="`guard-name-${index}`"
                            type="text"
                            v-model="guardia.name"
                            class="col-span-3"
                            :placeholder="`Ej: Guardia ${index + 1}`"
                        />
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="submit"
                    :disabled="!numberOfGuards || form.processing || form.guards.some((g) => !g.name.trim())"
                    @click="submit"
                    class="bg-green-500 text-white hover:bg-green-600"
                >
                    {{ form.processing ? 'Procesando...' : 'Agregar Guardias' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
