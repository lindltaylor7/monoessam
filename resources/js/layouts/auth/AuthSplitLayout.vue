<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const name = page.props.name;
const quote = page.props.quote;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
        <div class="bg-muted relative hidden h-full flex-col p-10 text-white lg:flex dark:border-r">
            <!-- Imagen de fondo que ocupa todo el espacio -->
            <div class="absolute inset-0 overflow-hidden">
                <img src="/kitchen.jpg" alt="Imagen descriptiva" class="h-full w-full object-cover object-center" />
                <!-- Capa de superposición oscura para mejorar legibilidad del texto (opcional) -->
                <div class="absolute inset-0 bg-black/30"></div>
            </div>

            <Link :href="route('home')" class="relative z-20 flex items-center text-lg font-medium">
                {{ name }}
            </Link>
        </div>
        <div class="lg:p-8">
            <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-xl font-medium tracking-tight" v-if="title">{{ title }}</h1>
                    <p class="text-muted-foreground text-sm" v-if="description">{{ description }}</p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
