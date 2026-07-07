<script setup lang="ts">
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { MapPin, Check, X, Pencil } from 'lucide-vue-next';
import axios from 'axios';
import { ref, watch } from 'vue';

const props = defineProps<{
    latitude:  number | null;
    longitude: number | null;
    address:   string | null;
    patchUrl:  string;
}>();

const emit = defineEmits<{
    (e: 'updated', lat: number | null, lng: number | null, address: string | null): void;
}>();

const open    = ref(false);
const saving  = ref(false);
const lat     = ref<string>(props.latitude  != null ? String(props.latitude)  : '');
const lng     = ref<string>(props.longitude != null ? String(props.longitude) : '');
const addr    = ref<string>(props.address ?? '');
const paste   = ref('');
const error   = ref('');

watch(() => props.latitude,  v => { lat.value  = v != null ? String(v) : ''; });
watch(() => props.longitude, v => { lng.value  = v != null ? String(v) : ''; });
watch(() => props.address,   v => { addr.value = v ?? ''; });

function resetFields() {
    lat.value   = props.latitude  != null ? String(props.latitude)  : '';
    lng.value   = props.longitude != null ? String(props.longitude) : '';
    addr.value  = props.address ?? '';
    paste.value = '';
    error.value = '';
}

function openEditor() {
    resetFields();
    open.value = true;
}

function parsePaste() {
    const parts = paste.value.trim().split(/[\s,]+/);
    if (parts.length >= 2) {
        const a = parseFloat(parts[0]);
        const b = parseFloat(parts[1]);
        if (!isNaN(a) && !isNaN(b)) {
            lat.value   = String(a);
            lng.value   = String(b);
            paste.value = '';
            error.value = '';
        }
    }
}

async function save() {
    const latNum = lat.value !== '' ? parseFloat(lat.value) : null;
    const lngNum = lng.value !== '' ? parseFloat(lng.value) : null;

    if ((lat.value !== '' && isNaN(latNum!)) || (lng.value !== '' && isNaN(lngNum!))) {
        error.value = 'Coordenadas inválidas';
        return;
    }

    saving.value = true;
    error.value  = '';
    try {
        await axios.patch(props.patchUrl, {
            latitude:  latNum,
            longitude: lngNum,
            address:   addr.value || null,
        });
        emit('updated', latNum, lngNum, addr.value || null);
        open.value = false;
    } catch {
        error.value = 'Error al guardar';
    } finally {
        saving.value = false;
    }
}

function cancel() {
    resetFields();
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open">
        <!-- Summary view: texto plano + botón lápiz. Sin @click.stop aquí para que el clic
             siga propagándose y permita seleccionar la tarjeta (Mina/Unidad) contenedora. -->
        <div class="mt-1 flex items-start gap-1.5 group/coord">
            <div class="flex-1 min-w-0 space-y-0.5">
                <span class="flex items-center gap-1 text-xs text-slate-400">
                    <MapPin class="h-3 w-3 shrink-0" />
                    <span v-if="latitude && longitude">
                        {{ Number(latitude).toFixed(5) }}, {{ Number(longitude).toFixed(5) }}
                    </span>
                    <span v-else class="italic">Sin coordenadas</span>
                </span>
                <span v-if="address" class="block pl-4 text-[11px] text-slate-400 truncate max-w-[200px]">{{ address }}</span>
                <span v-else class="block pl-4 text-[11px] italic text-slate-300">Sin dirección</span>
            </div>
            <PopoverTrigger as-child>
                <button
                    @click.stop="openEditor"
                    title="Editar ubicación"
                    class="shrink-0 rounded p-1 text-slate-300 opacity-0 group-hover/coord:opacity-100 hover:bg-slate-100 hover:text-blue-600 transition-all"
                >
                    <Pencil class="h-3 w-3" />
                </button>
            </PopoverTrigger>
        </div>

        <!-- Overlay: se renderiza en un portal, así que nunca queda recortado por el
             overflow del árbol ni empuja el tamaño de la tarjeta contenedora. -->
        <PopoverContent class="w-64 space-y-1.5 p-3" align="start" @click.stop>
            <input
                v-model="addr"
                type="text"
                placeholder="Dirección (ej. Av. Principal 123, Huancayo)"
                class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-800 outline-none focus:ring-1 focus:ring-blue-400"
            />
            <input
                v-model="paste"
                placeholder="Pegar coordenadas desde Maps: -12.051, -75.218"
                class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-800 outline-none focus:ring-1 focus:ring-blue-400"
                @input="parsePaste"
            />
            <div class="flex gap-1.5">
                <input
                    v-model="lat"
                    type="number" step="any"
                    placeholder="Latitud"
                    class="w-1/2 rounded border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-800 outline-none focus:ring-1 focus:ring-blue-400"
                />
                <input
                    v-model="lng"
                    type="number" step="any"
                    placeholder="Longitud"
                    class="w-1/2 rounded border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-800 outline-none focus:ring-1 focus:ring-blue-400"
                />
            </div>
            <p v-if="error" class="text-[10px] text-red-500">{{ error }}</p>
            <div class="flex justify-end gap-1 pt-0.5">
                <button @click="cancel" class="rounded p-1 text-slate-400 hover:text-slate-600">
                    <X class="h-3.5 w-3.5" />
                </button>
                <button @click="save" :disabled="saving" class="rounded bg-blue-600 p-1 text-white hover:bg-blue-700 disabled:opacity-50">
                    <Check class="h-3.5 w-3.5" />
                </button>
            </div>
        </PopoverContent>
    </Popover>
</template>
