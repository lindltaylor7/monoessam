<script setup lang="ts">
import { AlertTriangle, X } from 'lucide-vue-next';

interface Conflict {
    service_name: string;
    service_code: string;
    cafe_name: string;
}

interface DuplicateData {
    dinner: { id: number; name: string; dni: string };
    conflicts: Conflict[];
    message: string;
}

defineProps<{
    show: boolean;
    data: DuplicateData | null;
}>();

const emits = defineEmits<{
    (e: 'cancel'): void;
    (e: 'confirm'): void;
}>();
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="show && data" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="emits('cancel')" />

            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95 translate-y-2"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                appear
            >
                <div class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <!-- Header -->
                    <div class="relative overflow-hidden bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-5 text-white">
                        <div class="absolute -top-6 -right-6 h-24 w-24 rounded-full bg-white/10 blur-2xl" />
                        <div class="relative flex items-start gap-3 pr-8">
                            <div class="mt-0.5 flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/20">
                                <AlertTriangle class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold tracking-tight">Servicio ya consumido</h3>
                                <p class="mt-0.5 text-sm text-amber-100/90">{{ data.dinner.name }} · DNI {{ data.dinner.dni }}</p>
                            </div>
                        </div>
                        <button
                            @click="emits('cancel')"
                            class="absolute top-4 right-4 flex h-7 w-7 items-center justify-center rounded-full bg-white/20 transition hover:bg-white/30"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-5">
                        <p class="mb-3 text-sm text-slate-500">Este comensal ya consumió los siguientes servicios hoy:</p>

                        <div class="overflow-hidden rounded-xl border border-amber-100">
                            <div class="bg-amber-50 px-4 py-2">
                                <div class="grid grid-cols-[1fr_auto] gap-2 text-[10px] font-bold tracking-wider text-amber-600 uppercase">
                                    <span>Servicio</span>
                                    <span class="text-right">Cafetería</span>
                                </div>
                            </div>
                            <div class="divide-y divide-amber-50">
                                <div
                                    v-for="(conflict, i) in data.conflicts"
                                    :key="i"
                                    class="grid grid-cols-[1fr_auto] items-center gap-3 bg-white px-4 py-2.5"
                                >
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">{{ conflict.service_name }}</p>
                                        <p class="font-mono text-[11px] text-slate-400">{{ conflict.service_code }}</p>
                                    </div>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-right text-[11px] font-semibold text-slate-600">
                                        {{ conflict.cafe_name }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p class="mt-4 text-sm text-slate-500">¿Desea permitir el registro de todas formas?</p>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/80 px-5 py-3">
                        <button
                            @click="emits('cancel')"
                            class="rounded-lg border border-slate-200 bg-white px-5 py-2 text-sm font-medium text-slate-600 shadow-sm transition hover:bg-slate-50 active:scale-95"
                        >
                            Cancelar
                        </button>
                        <!--  <button
                            @click="emits('confirm')"
                            class="rounded-lg bg-amber-500 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600 active:scale-95"
                        >
                            Permitir de todas formas
                        </button> -->
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>
