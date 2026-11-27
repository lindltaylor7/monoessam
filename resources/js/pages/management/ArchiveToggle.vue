<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Archive, ArchiveRestore } from 'lucide-vue-next';
import { ref } from 'vue';

// Archivar = deja de operar (se oculta en POS, inventario, personal, logística…) pero conserva
// su historia y sigue visible en los módulos de alimentación y reportes. No borra nada.
// Archivar baja en cascada a unidades/comedores; reactivar también reactiva los padres.
const props = defineProps<{
    archived: boolean;
    patchUrl: string;
    label: string;
}>();

const saving = ref(false);
const error = ref('');

async function toggle() {
    const archive = !props.archived;
    const msg = archive
        ? `¿Archivar "${props.label}"? Dejará de aparecer en los módulos operativos (también sus unidades/comedores). No se borra nada y seguirá disponible en alimentación y reportes.`
        : `¿Reactivar "${props.label}"? Volverá a aparecer en todos los módulos.`;
    if (!window.confirm(msg)) return;

    saving.value = true;
    error.value = '';
    try {
        await axios.patch(props.patchUrl, { archived: archive });
        router.reload({ only: ['mines'] });
    } catch {
        error.value = 'Error al guardar';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <!-- @click.stop: no debe seleccionar/deseleccionar la tarjeta contenedora. -->
    <span class="mt-1 inline-flex items-center gap-1.5 text-xs" @click.stop>
        <button
            type="button"
            :disabled="saving"
            class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[11px] disabled:opacity-50"
            :class="archived ? 'border-emerald-300 text-emerald-700 hover:bg-emerald-50' : 'border-slate-200 text-slate-500 hover:bg-slate-100'"
            :title="archived ? 'Reactivar' : 'Archivar (solo alimentación)'"
            @click="toggle"
        >
            <ArchiveRestore v-if="archived" class="h-3 w-3" />
            <Archive v-else class="h-3 w-3" />
            {{ archived ? 'Reactivar' : 'Archivar' }}
        </button>
        <span v-if="error" class="text-[10px] text-red-500">{{ error }}</span>
    </span>
</template>
