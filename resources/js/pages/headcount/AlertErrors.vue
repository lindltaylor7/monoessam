<script setup lang="ts">
import { computed } from 'vue';

// Definimos la interfaz del objeto de errores que esperamos recibir
interface Errors {
    [key: string]: string | string[];
}

const props = defineProps<{
    errors: Errors;
    show: {
        type: Boolean;
        default: true;
    };
}>();

// Función para traducir las claves técnicas de Laravel a nombres amigables
const translateKey = (key: string): string => {
    const translations: { [key: string]: string } = {
        dni: 'DNI / Documento',
        cell: 'Teléfono / Celular',
        unit_id: 'Sede / Unidad',
        // Puedes añadir más traducciones aquí según las claves que uses
    };
    return translations[key] || key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
};

// Computa la lista final de mensajes de error formateados para la UI
const formattedErrors = computed(() => {
    if (!props.errors || Object.keys(props.errors).length === 0) {
        return [];
    }

    const messages: string[] = [];

    for (const key in props.errors) {
        if (Object.prototype.hasOwnProperty.call(props.errors, key)) {
            const errorMessages = Array.isArray(props.errors[key]) ? props.errors[key] : [props.errors[key]];
            const friendlyKey = translateKey(key);

            errorMessages.forEach((msg) => {
                // Formateamos el mensaje para que sea más claro: "Campo: Mensaje"
                messages.push(`**${friendlyKey}**: ${msg}`);
            });
        }
    }
    return messages;
});

// Comprueba si hay errores para mostrar
const hasErrors = computed(() => formattedErrors.value.length > 0 && props.show);
</script>

<template>
    <div v-if="hasErrors" class="space-y-4">
        <div class="rounded-lg border border-red-400 bg-red-100 p-4 shadow-md transition-all duration-300 ease-in-out">
            <!-- Encabezado del mensaje -->
            <div class="mb-2 flex items-start space-x-3">
                <!-- Icono de Advertencia (Puedes usar lucide-react o cualquier ícono SVG) -->
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="h-6 w-6 flex-shrink-0 text-red-600"
                >
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>

                <div class="flex flex-col">
                    <h3 class="text-lg font-bold text-red-800">No se pudo completar el registro.</h3>
                    <p class="text-sm text-red-700">Por favor, revise y corrija los siguientes campos:</p>
                </div>
            </div>

            <!-- Lista de errores formateados -->
            <ul class="ml-8 list-disc space-y-1 text-sm text-red-900">
                <li v-for="(error, index) in formattedErrors" :key="index" v-html="error"></li>
            </ul>

            <!-- Pie de página amigable (opcional) -->
            <p class="mt-3 text-xs text-red-600 italic">Si el problema persiste, contacte con soporte técnico.</p>
        </div>
    </div>
</template>
