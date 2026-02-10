<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

const props = defineProps({
    showAlert: {
        type: Boolean,
        default: false,
    },
    description: {
        type: String,
        default: 'This is an alert description.',
    },
    type: {
        type: String,
        default: 'success', // 'success' or 'error'
        validator: (value: string) => ['success', 'error'].includes(value),
    },
    title: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <Alert
        v-if="showAlert"
        :variant="type === 'error' ? 'destructive' : 'default'"
        :class="[
            type === 'error'
                ? 'm-3 border-red-600 bg-red-50 dark:border-red-900 dark:bg-red-950'
                : 'm-3 border-green-600 bg-green-50 dark:border-green-900 dark:bg-green-950',
        ]"
    >
        <AlertTitle :class="type === 'error' ? 'text-red-900 dark:text-red-100' : 'text-green-900 dark:text-green-100'">
            {{ title || (type === 'error' ? 'Error!' : 'Éxito!') }}
        </AlertTitle>
        <AlertDescription :class="type === 'error' ? 'text-red-800 dark:text-red-200' : 'text-green-800 dark:text-green-200'">
            {{ description }}
        </AlertDescription>
    </Alert>
</template>
