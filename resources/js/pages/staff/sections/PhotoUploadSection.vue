<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputSearchRole from '@/pages/users/InputSearchRole.vue';
import InputSearchSelectable from '@/pages/users/InputSearchSelectable.vue';
import { Business, Role, Unit } from '@/types';
import { BriefcaseBusiness, Building, LayoutList, MapPin, Store, Truck, User2, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import InputSelectableUnit from '../InputSelectableUnit.vue';

interface Props {
    imagePreview: string | null;
    cafeId: number | null;
    cafes: any[];
    units: Unit[];
    unitId: number;
    areaId: number;
    workplace: string;
    roleId: number;
    roles: Role[];
    businneses: Business[];
    headquarter: any;
}

interface Emits {
    (e: 'trigger-upload'): void;
    (e: 'remove-image'): void;
    (e: 'select-cafe', cafe: any): void;
    (e: 'select-unit', unit: Unit): void;
    (e: 'select-role', role: Role): void;
    (e: 'select-area', areaId: number): void;
    (e: 'update:workplace', workplace: number): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const workplace = ref('');
const businnesSelected = ref(0);
const headquartersSelected = ref([]);
const headquarterSelected = ref(0);
const areasSelected = ref([]);
const areaSelected = ref(0);

if(props.workplace != 0){
    workplace.value = props.workplace
    console.log(props)

    if(props.areaId != 0){
        businnesSelected.value = props.headquarter?.business?.id,
        
        headquartersSelected.value = props.businneses.find((b) => b.id == businnesSelected.value).headquarters

        headquarterSelected.value = props.headquarter?.id,

        areasSelected.value = headquartersSelected.value.find((h) => h.id == headquarterSelected.value).areas

        areaSelected.value = props.areaId
    }

}   

watch(businnesSelected, (newValue) => {
    headquartersSelected.value = props.businneses.find((b) => b.id == newValue).headquarters;
});

watch(headquarterSelected, (newValue) => {
    areasSelected.value = headquartersSelected.value.find((h) => h.id == newValue).areas;
});

watch(areaSelected, (newValue) => {
    emit('select-area', newValue);
});
</script>

<template>
    <div class="rounded-2xl border border-zinc-100 bg-white p-7 shadow-sm transition-all duration-300 hover:shadow-md">
        <div class="flex flex-col gap-8 lg:flex-row">
            <!-- Columna izquierda: Foto -->
            <div class="flex flex-col items-center space-y-4 lg:w-52">
                <!-- Contenedor de imagen -->
                <div class="group relative">
                    <div
                        class="relative overflow-hidden rounded-xl border-2 border-zinc-100 shadow-sm transition-all duration-300 group-hover:border-zinc-200 group-hover:shadow-md"
                    >
                        <img
                            :src="imagePreview || 'https://placehold.co/150x200/f4f4f5/71717a?text=FOTO'"
                            alt="Foto Colaborador"
                            class="h-48 w-36 object-cover transition-transform duration-500 group-hover:scale-[1.02]"
                        />

                        <!-- Overlay con efecto hover -->
                        <div
                            v-if="!imagePreview"
                            class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-black/5 to-black/0 opacity-0 transition-all duration-300 group-hover:opacity-100"
                        >
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white/90 shadow-lg">
                                <i class="ri-image-add-line text-xl text-zinc-700"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Botón eliminar -->
                    <button
                        v-if="imagePreview"
                        @click="emit('remove-image')"
                        type="button"
                        class="absolute -top-2 -right-2 flex h-8 w-8 items-center justify-center rounded-full bg-red-500 text-white shadow-lg ring-2 ring-white transition-all duration-200 hover:scale-110 hover:bg-red-600 active:scale-95"
                    >
                        <X :size="14" />
                    </button>
                </div>

                <!-- Botón de acción -->
                <Button
                    variant="outline"
                    size="sm"
                    class="w-full gap-2 border-zinc-200 text-zinc-700 hover:bg-zinc-50 hover:text-zinc-900"
                    @click="emit('trigger-upload')"
                    type="button"
                >
                    <i :class="imagePreview ? 'ri-image-edit-line' : 'ri-upload-2-line'"></i>
                    {{ imagePreview ? 'Cambiar Foto' : 'Subir Foto' }}
                </Button>

                <!-- Información adicional -->
                <div class="rounded-lg bg-zinc-50 px-3 py-2">
                    <p class="text-center text-xs leading-tight text-zinc-500">
                        <span class="font-medium text-zinc-600">Formatos:</span> JPG o PNG<br />
                        <span class="font-medium text-zinc-600">Tamaño máximo:</span> 5MB
                    </p>
                </div>
            </div>

            <!-- Divisor vertical -->
            <div class="hidden lg:flex lg:items-center">
                <div class="h-48 w-px bg-gradient-to-b from-transparent via-zinc-200 to-transparent"></div>
            </div>

            <!-- Columna derecha: Formulario -->
            <div class="flex-1">
                <!-- Header con mejor jerarquía -->
                <div class="mb-6 border-b border-zinc-100 pb-4">
                    <div class="mb-2 flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                            <BriefcaseBusiness />
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-zinc-900">Información Laboral</h3>
                            <p class="mt-1 text-sm text-zinc-500">Configura los detalles laborales del colaborador</p>
                        </div>
                    </div>
                </div>

                <!-- Contenido del formulario -->
                <div class="space-y-6">
                    <!-- Lugar de trabajo -->
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                            <div class="flex h-5 w-5 items-center justify-center rounded-md bg-zinc-100 text-zinc-500">
                                <Building />
                            </div>
                            Lugar de trabajo
                        </label>
                        <div class="pl-7">
                           
                            <Select class="w-full" v-model="workplace">
                                <SelectTrigger class="h-11 border-zinc-200 bg-white hover:bg-zinc-50">
                                    <SelectValue placeholder="Seleccionar lugar de trabajo" />
                                </SelectTrigger>
                                <SelectContent class="border-zinc-200 bg-white shadow-lg">
                                    <SelectItem value="1" class="hover:bg-zinc-50 focus:bg-zinc-50">
                                        <div class="flex items-center gap-2 py-1">
                                            <i class="ri-community-line text-blue-500"></i>
                                            <span>Oficina</span>
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="2" class="hover:bg-zinc-50 focus:bg-zinc-50">
                                        <div class="flex items-center gap-2 py-1">
                                            <i class="ri-truck-line text-emerald-500"></i>
                                            <span>Unidad</span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- Sección dinámica basada en selección -->
                    <div class="transition-all duration-300">
                        <!-- Sede (Oficina) -->
                        <div v-if="workplace == '1'" class="space-y-3 pl-7">
                            <div class="rounded-xl border border-zinc-100 bg-zinc-50/50 p-4">
                                <div class="space-y-3">
                                    <!-- Título de sección -->
                                    <div class="mb-2 flex items-center gap-2">
                                        <div class="flex h-6 w-6 items-center justify-center rounded-md bg-blue-100 text-blue-600">
                                            <Building />
                                        </div>
                                        <span class="text-sm font-medium text-zinc-700">Configuración de oficina</span>
                                    </div>

                                    <!-- Fila de tres selectores -->
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <!-- Empresa -->
                                        <div class="space-y-2">
                                            <label class="flex items-center gap-2 text-xs font-medium text-zinc-600">
                                                <div class="flex h-4 w-4 items-center justify-center rounded bg-blue-50 text-blue-500">
                                                    <Building />
                                                </div>
                                                Empresa
                                            </label>
                                            <Select class="w-full" v-model="businnesSelected">
                                                <SelectTrigger class="h-9 border-zinc-200 bg-white text-sm">
                                                    <SelectValue placeholder="Seleccionar" />
                                                </SelectTrigger>
                                                <SelectContent class="border-zinc-200 bg-white shadow-lg">
                                                    <SelectItem
                                                        :value="businnes.id"
                                                        v-for="businnes in businneses"
                                                        :key="businnes.id"
                                                        class="text-sm hover:bg-zinc-50"
                                                    >
                                                        {{ businnes.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <!-- Sede -->
                                        <div class="space-y-2">
                                            <label class="flex items-center gap-2 text-xs font-medium text-zinc-600">
                                                <div class="flex h-4 w-4 items-center justify-center rounded bg-blue-50 text-blue-500">
                                                    <MapPin />
                                                </div>
                                                Sede
                                            </label>
                                            <Select class="w-full" v-model="headquarterSelected">
                                                <SelectTrigger class="h-9 border-zinc-200 bg-white text-sm">
                                                    <SelectValue placeholder="Seleccionar" />
                                                </SelectTrigger>
                                                <SelectContent class="border-zinc-200 bg-white shadow-lg">
                                                    <SelectItem
                                                        :value="headquarter.id"
                                                        v-for="headquarter in headquartersSelected"
                                                        :key="headquarter.id"
                                                        class="text-sm hover:bg-zinc-50"
                                                    >
                                                        {{ headquarter.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <!-- Área -->
                                        <div class="space-y-2">
                                            <label class="flex items-center gap-2 text-xs font-medium text-zinc-600">
                                                <div class="flex h-4 w-4 items-center justify-center rounded bg-blue-50 text-blue-500">
                                                    <LayoutList />
                                                </div>
                                                Área
                                            </label>
                                            <Select class="w-full" v-model="areaSelected">
                                                <SelectTrigger class="h-9 border-zinc-200 bg-white text-sm">
                                                    <SelectValue placeholder="Seleccionar" />
                                                </SelectTrigger>
                                                <SelectContent class="border-zinc-200 bg-white shadow-lg">
                                                    <SelectItem
                                                        :value="area.id"
                                                        v-for="area in areasSelected"
                                                        :key="area.id"
                                                        class="text-sm hover:bg-zinc-50"
                                                    >
                                                        {{ area.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <!-- Flecha indicadora de flujo -->
                                    <div class="hidden items-center justify-center pt-2 md:flex">
                                        <div class="flex items-center space-x-1 text-blue-400">
                                            <i class="ri-arrow-right-s-line text-lg"></i>
                                            <i class="ri-arrow-right-s-line text-lg"></i>
                                            <i class="ri-arrow-right-s-line text-lg"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Unidad y Café (Unidad) -->
                        <div v-if="workplace == '2'" class="space-y-5 pl-7">
                            <div class="rounded-xl border border-zinc-100 bg-zinc-50/50 p-4">
                                <div class="space-y-4">
                                    <!-- Unidad -->
                                    <div class="space-y-2">
                                        <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                                            <div class="flex h-5 w-5 items-center justify-center rounded-md bg-zinc-100 text-emerald-600">
                                                <Truck />
                                            </div>
                                            Unidad asignada
                                        </label>
                                        <InputSelectableUnit :units="units" @selectUnit="emit('select-unit', $event)" :unitSelected="unitId" />
                                    </div>

                                    <!-- Café -->
                                    <div class="space-y-2">
                                        <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                                            <div class="flex h-5 w-5 items-center justify-center rounded-md bg-amber-100 text-amber-600">
                                                <Store />
                                            </div>
                                            Café asignado
                                        </label>
                                        <InputSearchSelectable :cafes="cafes" @selectCafe="emit('select-cafe', $event)" :cafeSelected="cafeId" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rol -->
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                            <div class="flex h-5 w-5 items-center justify-center rounded-md bg-purple-100 text-purple-600">
                                <User2 />
                            </div>
                            Rol o cargo
                        </label>
                        <div class="pl-7">
                            <InputSearchRole @selectRole="emit('select-role', $event)" :roles="roles" :roleSelected="roleId" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
