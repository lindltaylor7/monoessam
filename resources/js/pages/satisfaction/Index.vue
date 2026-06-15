<script setup lang="ts">
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useHeadcountSelection } from '@/composables/useHeadcountSelection';
import AppLayout from '@/layouts/AppLayout.vue';
import { Mine } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import dayjs from 'dayjs';
import 'dayjs/locale/es';
import { Angry, Frown, Laugh, Lock, Meh, Smile } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';

dayjs.locale('es');

interface Props {
    mines: Mine[];
    locked_mine_id: number | null;
    today_votes: Record<number, number>;
}

const props = defineProps<Props>();

const { selectedOptions, selectedUnits, selectedCafes, selectedServices } = useHeadcountSelection(props.mines);

// La mina viene fijada por el usuario autenticado: se preselecciona y no se puede cambiar
const mineLocked = computed(() => props.locked_mine_id !== null);
if (props.mines.length === 1) {
    selectedOptions.value.mine = String(props.mines[0].id);
}

// Al cambiar una selección padre, limpiar las hijas para evitar valores obsoletos
watch(
    () => selectedOptions.value.mine,
    () => {
        selectedOptions.value.unit = null;
        selectedOptions.value.cafe = null;
        selectedOptions.value.service = null;
    },
);
watch(
    () => selectedOptions.value.unit,
    () => {
        selectedOptions.value.cafe = null;
        selectedOptions.value.service = null;
    },
);
watch(
    () => selectedOptions.value.cafe,
    () => {
        selectedOptions.value.service = null;
    },
);

const allSelected = computed(
    () => !!selectedOptions.value.mine && !!selectedOptions.value.unit && !!selectedOptions.value.cafe && !!selectedOptions.value.service,
);

const todayVotes = ref<Record<number, number>>({ ...props.today_votes });
const isSubmitting = ref(false);

const selectedCafe = computed(() => selectedCafes.value.find((c: any) => String(c.id) === String(selectedOptions.value.cafe)));

const todayCount = computed(() => {
    if (!selectedOptions.value.cafe) return 0;
    return todayVotes.value[Number(selectedOptions.value.cafe)] || 0;
});

const faces = [
    {
        score: 1,
        icon: Angry,
        label: 'Muy insatisfecho',
        circle: 'bg-red-500 shadow-red-500/40',
        bar: 'bg-red-500',
        swalColor: '#ef4444',
    },
    {
        score: 2,
        icon: Frown,
        label: 'Insatisfecho',
        circle: 'bg-orange-400 shadow-orange-400/40',
        bar: 'bg-orange-400',
        swalColor: '#fb923c',
    },
    {
        score: 3,
        icon: Meh,
        label: 'Neutral',
        circle: 'bg-yellow-400 shadow-yellow-400/40',
        bar: 'bg-yellow-400',
        swalColor: '#facc15',
    },
    {
        score: 4,
        icon: Smile,
        label: 'Satisfecho',
        circle: 'bg-green-300 shadow-green-300/40',
        bar: 'bg-green-300',
        swalColor: '#86efac',
    },
    {
        score: 5,
        icon: Laugh,
        label: 'Muy satisfecho',
        circle: 'bg-green-500 shadow-green-500/40',
        bar: 'bg-green-500',
        swalColor: '#22c55e',
    },
];

const submitScore = async (face: (typeof faces)[number]) => {
    // Guard: one vote at a time, interaction blocked until the data is stored
    if (isSubmitting.value) return;

    if (!allSelected.value) {
        Swal.fire({
            icon: 'warning',
            title: 'Complete todos los campos',
            text: 'Debe seleccionar mina, unidad, comedor y servicio antes de calificar.',
        });
        return;
    }

    isSubmitting.value = true;

    Swal.fire({
        title: 'Guardando su respuesta...',
        html: `<span style="font-weight:700;color:${face.swalColor}">${face.label}</span>`,
        allowOutsideClick: false,
        allowEscapeKey: false,
        allowEnterKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading(),
    });

    try {
        const { data } = await axios.post(route('satisfaction.store'), {
            cafe_id: Number(selectedOptions.value.cafe),
            score: face.score,
            service: selectedOptions.value.service,
        });

        todayVotes.value[Number(selectedOptions.value.cafe)] = data.today_total;

        await Swal.fire({
            icon: 'success',
            title: '¡Gracias por su opinión!',
            text: 'Su respuesta fue registrada correctamente.',
            timer: 2000,
            timerProgressBar: true,
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
        });
    } catch (error: any) {
        await Swal.fire({
            icon: 'error',
            title: 'No se pudo registrar',
            text: error.response?.data?.message || 'Ocurrió un problema al guardar su respuesta. Intente nuevamente.',
            confirmButtonColor: '#ef4444',
        });
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Head title="Satisfacción" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Selector de comedor -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                        Seleccione el comedor que desea calificar <span class="text-red-500">*</span>
                        <span class="text-muted-foreground ml-1 text-xs font-normal">(todos los campos son obligatorios)</span>
                    </p>
                    <span
                        v-if="mineLocked"
                        class="flex shrink-0 items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-bold text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900"
                        title="Su usuario está asignado a esta mina"
                    >
                        <Lock class="h-3 w-3" /> Mina asignada a su usuario
                    </span>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Select v-model="selectedOptions.mine" :disabled="mineLocked">
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

                    <Select v-model="selectedOptions.unit" :disabled="!selectedOptions.mine">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Selecciona una unidad" />
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

                    <Select v-model="selectedOptions.cafe" :disabled="!selectedOptions.unit">
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

                    <Select v-model="selectedOptions.service" :disabled="!selectedOptions.cafe">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Selecciona un servicio" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Servicios</SelectLabel>
                                <SelectItem v-for="service in selectedServices" :value="service.name" :key="service.pivot?.id || service.id">
                                    {{ service.name }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <!-- Panel NPS -->
            <div
                class="relative flex flex-1 flex-col items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-b from-blue-400 to-blue-500 p-8 shadow-lg"
            >
                <template v-if="allSelected">
                    <p class="text-sm font-semibold tracking-widest text-blue-100 uppercase">
                        {{ selectedCafe?.name }} · {{ dayjs().format('dddd D [de] MMMM') }}
                    </p>
                    <h2 class="mt-2 text-center text-2xl font-black text-white md:text-3xl">
                        ¿Cómo calificaría su experiencia de hoy?
                    </h2>
                    <p class="mt-1 text-sm font-medium text-blue-100">
                        Servicio: {{ selectedOptions.service }}
                    </p>

                    <!-- Caras -->
                    <div class="mt-10 flex items-end gap-4 md:gap-8" :class="{ 'pointer-events-none opacity-60': isSubmitting }">
                        <button
                            v-for="face in faces"
                            :key="face.score"
                            type="button"
                            @click="submitScore(face)"
                            :disabled="isSubmitting"
                            class="group flex flex-col items-center gap-2 transition-transform hover:scale-110 focus:outline-none active:scale-95"
                            :title="face.label"
                        >
                            <span
                                class="flex h-16 w-16 items-center justify-center rounded-full shadow-lg transition-shadow md:h-24 md:w-24"
                                :class="face.circle"
                            >
                                <component :is="face.icon" class="h-9 w-9 text-zinc-900/80 md:h-14 md:w-14" stroke-width="1.75" />
                            </span>
                            <span
                                class="text-[10px] font-bold text-white/0 transition-colors group-hover:text-white md:text-xs"
                            >
                                {{ face.label }}
                            </span>
                        </button>
                    </div>

                    <!-- Barra de escala -->
                    <div class="mt-6 flex w-full max-w-2xl items-center gap-3">
                        <span class="text-2xl font-black text-red-300">−</span>
                        <div class="flex h-4 flex-1 gap-1 overflow-hidden">
                            <div
                                v-for="face in faces"
                                :key="face.score"
                                class="flex-1"
                                :class="[face.bar, face.score === 1 ? 'rounded-l-full' : '', face.score === 5 ? 'rounded-r-full' : '']"
                            ></div>
                        </div>
                        <span class="text-2xl font-black text-green-200">+</span>
                    </div>

                    <p class="mt-8 rounded-full bg-white/20 px-4 py-1.5 text-xs font-bold text-white">
                        {{ todayCount }} opinión{{ todayCount === 1 ? '' : 'es' }} registrada{{ todayCount === 1 ? '' : 's' }} hoy en este comedor
                    </p>
                </template>

                <template v-else>
                    <div class="flex flex-col items-center gap-3 text-center">
                        <div class="flex items-center gap-3 opacity-50">
                            <span v-for="face in faces" :key="face.score" class="flex h-12 w-12 items-center justify-center rounded-full" :class="face.circle">
                                <component :is="face.icon" class="h-7 w-7 text-zinc-900/80" stroke-width="1.75" />
                            </span>
                        </div>
                        <h2 class="mt-4 text-2xl font-black text-white">Encuesta de Satisfacción</h2>
                        <p class="max-w-md text-sm text-blue-100">
                            Complete la selección de mina, unidad, comedor y servicio en la parte superior para habilitar la encuesta del día.
                        </p>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
