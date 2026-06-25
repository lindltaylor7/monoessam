<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Business } from '@/types';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, watch } from 'vue'; // Importar 'watch'

const props = defineProps<{
    businesses: Business[];
}>();

// Estados para las listas de búsqueda (mantener solo la lista actual para el ComboBox)
const mines = ref([]);
const units = ref([]);
const cafes = ref([]);
const open = ref(false);
const showSuccessMessage = ref(false); // Nuevo estado para feedback

const isLoading = ref({
    mine: false,
    unit: false,
    cafe: false,
});

const form = useForm({
    mine: '',
    mineLat: '' as string,
    mineLng: '' as string,
    mineAddr: '' as string,
    mineId: 0,
    unit: '',
    unitLat: '' as string,
    unitLng: '' as string,
    unitAddr: '' as string,
    unitId: 0,
    cafe: '',
    cafeLat: '' as string,
    cafeLng: '' as string,
    cafeAddr: '' as string,
    cafeId: 0,
    businessId: 0,
});

function parseCoords(pasteStr: string, latField: 'mineLat'|'unitLat'|'cafeLat', lngField: 'mineLng'|'unitLng'|'cafeLng') {
    const parts = pasteStr.trim().split(/[\s,]+/);
    if (parts.length >= 2) {
        const a = parseFloat(parts[0]);
        const b = parseFloat(parts[1]);
        if (!isNaN(a) && !isNaN(b)) {
            (form as any)[latField] = String(a);
            (form as any)[lngField] = String(b);
        }
    }
}

// --- WATCHERS PARA LA JERARQUÍA Y LIMPIEZA ---

// Si cambia la Mina, limpiar la Unidad y la Cafetería
watch(
    () => form.mineId,
    (newId, oldId) => {
        if (newId !== oldId) {
            form.unit = '';
            form.unitId = 0;
            form.cafe = '';
            form.cafeId = 0;
            units.value = []; // Limpiar resultados de búsqueda de unidad
            cafes.value = []; // Limpiar resultados de búsqueda de café
            // Si hay un mineId, automáticamente buscamos las unidades asociadas a ESA mina.
            if (newId !== 0) {
                searchUnitByMineId(newId);
            }
        }
    },
);

// Si cambia la Unidad, limpiar la Cafetería
watch(
    () => form.unitId,
    (newId, oldId) => {
        if (newId !== oldId) {
            form.cafe = '';
            form.cafeId = 0;
            cafes.value = []; // Limpiar resultados de búsqueda de café
            if (newId !== 0) {
                searchCafeByUnitId(newId);
            }
        }
    },
);

// --- LÓGICA DE ENVÍO Y MANEJO DE ESTADO ---

// La creación finaliza en el nivel de Cafetería.
const submit = () => {
    // Si hay un cafe seleccionado (form.cafeId > 0), no hacemos nada,
    // ya que este formulario es para CREAR la jerarquía.
    // Si se llegó a este punto, significa que el usuario quiere crear la Cafetería.

    // El botón Submit ya no existe, usamos insertCafe como acción final.
    // Si el usuario quiere crear solo Mina o Unidad, usará sus respectivos botones '+'.

    // Si el usuario llega aquí y la cafetería no está creada, forzamos la creación:
    if (form.cafe.trim() !== '' && form.unitId > 0 && form.cafeId === 0) {
        insertCafe();
    }
};

// --- FUNCIONES DE BÚSQUEDA (MEJORADAS PARA ENFOCARSE EN EL ÁRBOL) ---

// Búsqueda de Mina (global)
const searchMine = debounce(() => {
    // Si queremos que se busquen todas al enfocar/borrar, ahora el backend lo soporta
    isLoading.value.mine = true;
    const query = form.mine.trim();
    axios
        .get('/mines/search/' + query)
        .then((result) => {
            mines.value = result.data;
        })
        .finally(() => (isLoading.value.mine = false));
}, 300);

// Búsqueda de Unidad: Limitada a la Mina seleccionada (Mejora de UX)
const searchUnitByMineId = debounce((mineId: number) => {
    if (mineId === 0) {
        units.value = [];
        return;
    }
    isLoading.value.unit = true;
    const query = form.unit.trim();
    axios
        .get(`/units/search/${mineId}/${query}`)
        .then((result) => {
            units.value = result.data;
        })
        .finally(() => (isLoading.value.unit = false));
}, 300);

// Búsqueda de Café: Limitada a la Unidad seleccionada
const searchCafeByUnitId = debounce((unitId: number) => {
    if (unitId === 0) {
        cafes.value = [];
        return;
    }
    isLoading.value.cafe = true;
    const query = form.cafe.trim();
    axios
        .get(`/cafes/search/${unitId}/${query}`)
        .then((result) => {
            cafes.value = result.data;
        })
        .finally(() => (isLoading.value.cafe = false));
}, 300);

// --- FUNCIONES PARA CREAR NUEVOS ELEMENTOS ---
const insertMine = () => {
    if (form.mine.trim() === '') return;

    isLoading.value.mine = true;
    axios
        .post('/mines', {
            name:      form.mine,
            latitude:  form.mineLat  ? parseFloat(form.mineLat)  : null,
            longitude: form.mineLng  ? parseFloat(form.mineLng)  : null,
            address:   form.mineAddr || null,
        })
        .then((response) => {
            selectMine(response.data);
        })
        .catch(console.error)
        .finally(() => (isLoading.value.mine = false));
};

const insertUnit = () => {
    if (form.unit.trim() === '' || form.mineId === 0) return;

    isLoading.value.unit = true;
    axios
        .post('/units', {
            name:      form.unit,
            mine_id:   form.mineId,
            latitude:  form.unitLat  ? parseFloat(form.unitLat)  : null,
            longitude: form.unitLng  ? parseFloat(form.unitLng)  : null,
            address:   form.unitAddr || null,
        })
        .then((response) => {
            selectUnit(response.data);
        })
        .catch(console.error)
        .finally(() => (isLoading.value.unit = false));
};

const insertCafe = () => {
    if (form.cafe.trim() === '' || form.unitId === 0 || form.businessId === 0) return;

    isLoading.value.cafe = true;
    axios
        .post('/cafes', {
            name:        form.cafe,
            unit_id:     form.unitId,
            business_id: form.businessId,
            latitude:    form.cafeLat  ? parseFloat(form.cafeLat)  : null,
            longitude:   form.cafeLng  ? parseFloat(form.cafeLng)  : null,
            address:     form.cafeAddr || null,
        })
        .then(() => {
            // Mostrar mensaje de éxito y limpiar formulario
            form.reset();
            open.value = false;
            showSuccessMessage.value = true;
            setTimeout(() => (showSuccessMessage.value = false), 3000);
        })
        .catch(console.error)
        .finally(() => (isLoading.value.cafe = false));
};

// --- FUNCIONES PARA SELECCIONAR ELEMENTOS ---
const selectMine = (mine: { id: number; name: string }) => {
    form.mine = mine.name;
    form.mineId = mine.id;
    mines.value = []; // Cierra la lista de autocompletado
};

const selectUnit = (unit: { id: number; name: string }) => {
    form.unit = unit.name;
    form.unitId = unit.id;
    units.value = [];
    form.unit = unit.name; // Asegura que el nombre de la unidad se muestre en el campo
};

const selectCafe = (cafe: { id: number; name: string }) => {
    form.cafe = cafe.name;
    form.cafeId = cafe.id;
    cafes.value = [];
};

// Función debounce (sin cambios, se mantiene)
function debounce(fn: Function, delay: number) {
    let timeoutId: number;
    return function (...args: any[]) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn.apply(null, args), delay);
    };
}
</script>

<template>
    <div v-if="showSuccessMessage" class="fixed top-4 right-4 z-50 rounded-lg bg-green-500 p-4 text-white shadow-lg">Lugar creado con éxito!</div>

    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline">Añadir Lugar</Button>
        </DialogTrigger>
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle class="mb-4 text-lg font-semibold">Agregar Nuevo Lugar (Mina → Unidad → Cafetería)</DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-6">
                <div>
                    <label class="mb-1 block text-sm font-medium">1. Mina (Requerido)</label>
                    <div class="relative flex gap-2">
                        <Input
                            v-model="form.mine"
                            type="text"
                            placeholder="Buscar o nombrar nueva Mina..."
                            @input="searchMine"
                            @focus="searchMine"
                            class="flex-1"
                        />
                        <Button
                            type="button"
                            @click="insertMine"
                            :disabled="!form.mine.trim() || isLoading.mine || form.mineId > 0"
                            title="Crear nueva Mina con este nombre"
                        >
                            <span v-if="isLoading.mine">...</span>
                            <span v-else>+</span>
                        </Button>

                        <ul
                            v-if="mines.length > 0"
                            class="absolute top-full z-10 mt-1 max-h-40 w-[80%] overflow-auto rounded-md border bg-white shadow-lg"
                        >
                            <li
                                v-for="mine in mines"
                                :key="mine.id"
                                class="cursor-pointer p-2 hover:bg-gray-100 hover:text-black"
                                @click="selectMine(mine)"
                            >
                                {{ mine.name }}
                            </li>
                        </ul>
                    </div>
                    <!-- Location fields for mine -->
                    <div v-if="form.mineId === 0" class="mt-2 space-y-2">
                        <div>
                            <label class="mb-0.5 block text-xs text-gray-500">Dirección (opcional)</label>
                            <Input v-model="form.mineAddr" type="text" placeholder="Av. Principal 123, Huancayo" class="h-8 text-xs" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-0.5 block text-xs text-gray-500">Latitud</label>
                                <Input v-model="form.mineLat" type="number" step="any" placeholder="-12.0516" class="h-8 text-xs" />
                            </div>
                            <div>
                                <label class="mb-0.5 block text-xs text-gray-500">Longitud</label>
                                <Input v-model="form.mineLng" type="number" step="any" placeholder="-75.2187" class="h-8 text-xs" />
                            </div>
                            <div class="col-span-2">
                                <Input
                                    type="text" placeholder="Pegar desde Google Maps: -12.0516, -75.2187"
                                    class="h-8 text-xs"
                                    @input="(e: any) => parseCoords(e.target.value, 'mineLat', 'mineLng')"
                                />
                            </div>
                        </div>
                    </div>
                    <div v-if="form.mineId > 0" class="mt-1 text-sm text-green-600">Mina seleccionada: {{ form.mine }}.</div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">2. Unidad</label>
                    <div class="relative flex gap-2">
                        <Input
                            v-model="form.unit"
                            type="text"
                            placeholder="Buscar o nombrar nueva Unidad..."
                            @input="searchUnitByMineId(form.mineId)"
                            @focus="searchUnitByMineId(form.mineId)"
                            class="flex-1"
                            :disabled="form.mineId === 0"
                        />
                        <Button
                            type="button"
                            @click="insertUnit"
                            :disabled="!form.unit.trim() || isLoading.unit || form.mineId === 0 || form.unitId > 0"
                            title="Crear nueva Unidad dentro de la Mina seleccionada"
                        >
                            <span v-if="isLoading.unit">...</span>
                            <span v-else>+</span>
                        </Button>

                        <ul
                            v-if="units.length > 0"
                            class="absolute top-full z-10 mt-1 max-h-40 w-[80%] overflow-auto rounded-md border bg-white shadow-lg"
                        >
                            <li
                                v-for="unit in units"
                                :key="unit.id"
                                class="cursor-pointer p-2 hover:bg-gray-100 hover:text-black"
                                @click="selectUnit(unit)"
                            >
                                {{ unit.name }}
                            </li>
                        </ul>
                    </div>
                    <!-- Location fields for unit -->
                    <div v-if="form.mineId > 0 && form.unitId === 0" class="mt-2 space-y-2">
                        <div>
                            <label class="mb-0.5 block text-xs text-gray-500">Dirección (opcional)</label>
                            <Input v-model="form.unitAddr" type="text" placeholder="Av. Principal 123, Huancayo" class="h-8 text-xs" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-0.5 block text-xs text-gray-500">Latitud</label>
                                <Input v-model="form.unitLat" type="number" step="any" placeholder="-12.0516" class="h-8 text-xs" />
                            </div>
                            <div>
                                <label class="mb-0.5 block text-xs text-gray-500">Longitud</label>
                                <Input v-model="form.unitLng" type="number" step="any" placeholder="-75.2187" class="h-8 text-xs" />
                            </div>
                            <div class="col-span-2">
                                <Input
                                    type="text" placeholder="Pegar desde Google Maps: -12.0516, -75.2187"
                                    class="h-8 text-xs"
                                    @input="(e: any) => parseCoords(e.target.value, 'unitLat', 'unitLng')"
                                />
                            </div>
                        </div>
                    </div>
                    <div v-if="form.unitId > 0" class="mt-1 text-sm text-green-600">Unidad seleccionada: {{ form.unit }}.</div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">3. Cafetería y Empresa (Acción Final)</label>
                    <div class="relative flex gap-2">
                        <Input
                            v-model="form.cafe"
                            type="text"
                            placeholder="Nombrar nueva Cafetería..."
                            @input="searchCafeByUnitId(form.unitId)"
                            @focus="searchCafeByUnitId(form.unitId)"
                            class="flex-1"
                            :disabled="form.unitId === 0"
                        />

                        <Select class="w-1/3" v-model="form.businessId" :disabled="form.unitId === 0">
                            <SelectTrigger>
                                <SelectValue placeholder="Empresa" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Empresas</SelectLabel>
                                    <SelectItem v-for="business in props.businesses" :value="business.id" :key="business.id">
                                        {{ business.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Button
                            type="button"
                            @click="insertCafe"
                            :disabled="!form.cafe.trim() || isLoading.cafe || form.unitId === 0 || form.businessId === 0"
                            title="Crear nueva Cafetería y asociarla a la Unidad y Empresa"
                            variant="default"
                        >
                            <span v-if="isLoading.cafe">Guardar</span>
                            <span v-else>Crear</span>
                        </Button>

                        <ul
                            v-if="cafes.length > 0"
                            class="absolute top-full z-10 mt-1 max-h-40 w-[80%] overflow-auto rounded-md border bg-white shadow-lg"
                        >
                            <li
                                v-for="cafe in cafes"
                                :key="cafe.id"
                                class="cursor-pointer p-2 hover:bg-gray-100 hover:text-black"
                                @click="selectCafe(cafe)"
                            >
                                {{ cafe.name }}
                            </li>
                        </ul>
                    </div>

                    <!-- Location fields for cafe -->
                    <div v-if="form.unitId > 0 && form.cafeId === 0" class="mt-2 space-y-2">
                        <div>
                            <label class="mb-0.5 block text-xs text-gray-500">Dirección (opcional)</label>
                            <Input v-model="form.cafeAddr" type="text" placeholder="Av. Principal 123, Huancayo" class="h-8 text-xs" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-0.5 block text-xs text-gray-500">Latitud</label>
                                <Input v-model="form.cafeLat" type="number" step="any" placeholder="-12.0516" class="h-8 text-xs" />
                            </div>
                            <div>
                                <label class="mb-0.5 block text-xs text-gray-500">Longitud</label>
                                <Input v-model="form.cafeLng" type="number" step="any" placeholder="-75.2187" class="h-8 text-xs" />
                            </div>
                            <div class="col-span-2">
                                <Input
                                    type="text" placeholder="Pegar desde Google Maps: -12.0516, -75.2187"
                                    class="h-8 text-xs"
                                    @input="(e: any) => parseCoords(e.target.value, 'cafeLat', 'cafeLng')"
                                />
                            </div>
                        </div>
                    </div>
                    <div v-if="form.cafeId > 0" class="mt-1 text-sm font-semibold text-red-500">
                        ⚠️ Esta Cafetería ya existe (ID: {{ form.cafeId }}).
                    </div>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
