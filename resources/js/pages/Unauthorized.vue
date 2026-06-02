<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ShieldOff } from 'lucide-vue-next';

defineProps<{
    routeName?: string;
}>();
</script>

<template>
    <AppLayout>
        <Head title="Acceso Denegado" />
        <div class="flex min-h-[60vh] flex-col items-center justify-center gap-6 text-center">
            <div class="flex h-24 w-24 items-center justify-center rounded-full bg-red-100">
                <ShieldOff class="h-12 w-12 text-red-600" />
            </div>
            <div class="space-y-2">
                <h1 class="text-3xl font-bold text-gray-900">Acceso Denegado</h1>
                <p class="max-w-md text-gray-500">
                    No tienes los permisos necesarios para acceder a esta sección.
                    Contacta con tu administrador para solicitar acceso.
                </p>
            </div>
            <button
                @click="router.visit('/dashboard')"
                class="rounded-md bg-red-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-red-700 focus:outline-none"
            >
                Volver al inicio
            </button>
        </div>
    </AppLayout>
</template>
