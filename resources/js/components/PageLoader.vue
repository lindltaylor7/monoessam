<script setup lang="ts">
import type { Visit } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Loader de pantalla para la navegación entre páginas.
 *
 * Se monta junto a la app (fuera de las páginas) para no desmontarse cuando cambia el layout.
 * Solo aparece en visitas GET que cambian de ruta: no en filtros que recargan la misma URL, ni en
 * recargas parciales (`only`/`except`), ni en prefetch, ni en envíos de formularios.
 *
 * - SHOW_DELAY: las navegaciones rápidas no lo muestran (evita el parpadeo).
 * - MIN_VISIBLE: una vez visible se mantiene un mínimo para que no "salte".
 */
const SHOW_DELAY = 250;
const MIN_VISIBLE = 350;

const visible = ref(false);

let currentVisit: Visit | null = null;
let showTimer: ReturnType<typeof setTimeout> | null = null;
let hideTimer: ReturnType<typeof setTimeout> | null = null;
let shownAt = 0;

const isPageNavigation = (visit: Visit): boolean => {
    if (visit.method !== 'get' || visit.prefetch) return false;
    if (visit.only.length > 0 || visit.except.length > 0) return false;
    return visit.url.pathname !== window.location.pathname;
};

const clearTimers = () => {
    if (showTimer) clearTimeout(showTimer);
    if (hideTimer) clearTimeout(hideTimer);
    showTimer = hideTimer = null;
};

const start = (visit: Visit) => {
    if (!isPageNavigation(visit)) return;
    currentVisit = visit;
    clearTimers();
    if (visible.value) return;
    showTimer = setTimeout(() => {
        visible.value = true;
        shownAt = Date.now();
    }, SHOW_DELAY);
};

const finish = (visit: Visit) => {
    // Una visita cancelada por otra más nueva también emite "finish": se ignora.
    if (visit !== currentVisit) return;
    currentVisit = null;
    clearTimers();
    if (!visible.value) return;
    const remaining = Math.max(0, MIN_VISIBLE - (Date.now() - shownAt));
    hideTimer = setTimeout(() => (visible.value = false), remaining);
};

let offStart: (() => void) | null = null;
let offFinish: (() => void) | null = null;

onMounted(() => {
    offStart = router.on('start', (event) => start(event.detail.visit));
    offFinish = router.on('finish', (event) => finish(event.detail.visit));
});

onBeforeUnmount(() => {
    offStart?.();
    offFinish?.();
    clearTimers();
});
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-200 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-150 ease-in"
        leave-to-class="opacity-0"
    >
        <div
            v-if="visible"
            class="fixed inset-0 z-[60] flex cursor-wait items-center justify-center bg-white/55 backdrop-blur-[2px] dark:bg-zinc-950/55"
            role="status"
            aria-live="polite"
            aria-busy="true"
        >
            <div
                class="flex items-center gap-3 rounded-2xl border border-zinc-200/80 bg-white px-5 py-3.5 shadow-lg shadow-red-950/10 dark:border-zinc-800 dark:bg-zinc-900"
            >
                <span class="relative grid h-9 w-9 place-items-center">
                    <span class="absolute inset-0 rounded-full border-[3px] border-red-100 dark:border-red-950"></span>
                    <span
                        class="absolute inset-0 rounded-full border-[3px] border-transparent border-t-red-700 motion-safe:animate-spin dark:border-t-red-500"
                    ></span>
                </span>
                <div class="flex flex-col leading-tight">
                    <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">Cargando…</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">Preparando la página</span>
                </div>
            </div>
        </div>
    </Transition>
</template>
