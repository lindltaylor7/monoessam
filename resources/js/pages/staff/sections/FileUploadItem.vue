<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { AlertCircle, Calendar, CalendarCheck, Paperclip } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    file: {
        label: string;
        file: any;
        expirationDate?: null | string;
    };
    index: number;
}

interface Emits {
    (e: 'upload', event: Event, label: string): void;
    (e: 'uploadDate', selectedDate: string, index: number): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const nameFile = computed(() => {
    const f = props.file.file;
    if (f instanceof File) return f.name;
    if (typeof f === 'string' && f.length > 0) return f.split('/').pop();
    return null;
});

const manageFile = (event: Event) => {
    emit('upload', event, props.file.label);
};

const manageFileDate = (event: Event) => {
    const selectedDate = (event.target as HTMLInputElement).value;
    emit('uploadDate', selectedDate, props.index);
};
</script>

<template>
    <div class="group rounded-xl border border-zinc-200 bg-white p-5 shadow-sm transition-all duration-300 hover:border-zinc-300 hover:shadow-md">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div
                    class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition-colors group-hover:bg-blue-600 group-hover:text-white"
                >
                    <Paperclip class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <h3 class="truncate text-sm font-bold text-zinc-900 md:text-base">{{ file.label }}</h3>
                    <p class="mt-0.5 flex items-center gap-1.5 text-xs text-zinc-500">
                        <span class="h-1.5 w-1.5 rounded-full bg-blue-400"></span>
                        Formatos aceptados: PDF, JPG
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-4 sm:flex-row sm:items-end lg:items-center">
                <div class="relative w-full sm:w-auto">
                    <Input type="file" :id="`file-${index}`" class="hidden" accept="application/pdf, image/jpeg" @change="manageFile" />
                    <label
                        :for="`file-${index}`"
                        class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-2.5 text-sm font-semibold transition-all"
                        :class="
                            nameFile
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                : 'border-zinc-200 bg-zinc-50 text-zinc-600 hover:border-blue-400 hover:bg-blue-50 hover:text-blue-600'
                        "
                    >
                        <i :class="nameFile ? 'ri-checkbox-circle-fill' : 'ri-upload-2-line'" class="text-lg"></i>
                        <span class="max-w-[150px] truncate">
                            {{ nameFile || 'Subir documento' }}
                        </span>
                    </label>
                </div>

                <div v-if="file.expirationDate !== undefined" class="min-w-[220px] space-y-1.5">
                    <label class="flex items-center gap-1 text-[10px] font-bold tracking-wider text-zinc-400 uppercase">
                        <Calendar class="h-3 w-3" />
                        Fecha de Expiración
                    </label>

                    <div class="group/date relative">
                        <input
                            type="date"
                            :value="file.expirationDate"
                            @change="manageFileDate"
                            class="w-full rounded-lg border-2 bg-white px-3 py-2 text-sm font-medium transition-all outline-none focus:ring-4 focus:ring-blue-100"
                            :class="
                                file.expirationDate
                                    ? 'border-emerald-500/30 bg-emerald-50/30 text-emerald-700 focus:border-emerald-500'
                                    : 'border-zinc-200 text-zinc-500 focus:border-blue-500'
                            "
                        />
                        <div class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2">
                            <CalendarCheck v-if="file.expirationDate" class="h-4 w-4 text-emerald-600" />
                            <Calendar v-else class="h-4 w-4 text-zinc-400" />
                        </div>
                    </div>
                </div>

                <div v-else class="flex h-[42px] items-center gap-2 rounded-lg border border-zinc-100 bg-zinc-50 px-4">
                    <AlertCircle class="h-4 w-4 text-zinc-400" />
                    <span class="text-xs font-medium text-zinc-500">No requiere vigencia</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Estética para el input date en navegadores que lo soportan */
input[type='date']::-webkit-calendar-picker-indicator {
    background: transparent;
    bottom: 0;
    color: transparent;
    cursor: pointer;
    height: auto;
    left: 0;
    position: absolute;
    right: 0;
    top: 0;
    width: auto;
}
</style>
