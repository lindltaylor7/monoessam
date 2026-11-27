<script setup lang="ts">
import { computed, useSlots, type Component } from 'vue';

type Variant = 'default' | 'primary' | 'success' | 'warning' | 'info' | 'purple';

// Acepta las dos formas en que se usa en el proyecto:
//   label / subtext / variant / :icon   y   title / subtitle / color / <template #icon>
// Antes solo leía la primera, y las pantallas que usaban la segunda (Personal, Usuarios, Roles,
// Comensales…) mostraban únicamente el número.
interface Props {
    label?: string;
    title?: string;
    value: string | number;
    subtext?: string;
    subtitle?: string;
    icon?: Component;
    variant?: Variant;
    color?: Variant;
    trend?: {
        value: string | number;
        isPositive?: boolean;
    };
}

const props = defineProps<Props>();
const slots = useSlots();

const heading = computed(() => props.label ?? props.title ?? '');
const caption = computed(() => props.subtext ?? props.subtitle ?? '');
const tone = computed<Variant>(() => props.variant ?? props.color ?? 'default');
const hasIcon = computed(() => !!props.icon || !!slots.icon);

const displayValue = computed(() => (typeof props.value === 'number' ? props.value.toLocaleString('es-PE') : props.value));

const tones: Record<Variant, { accent: string; iconBg: string; hover: string }> = {
    default: {
        accent: 'bg-zinc-300 dark:bg-zinc-700',
        iconBg: 'bg-muted text-muted-foreground',
        hover: 'hover:border-zinc-300 dark:hover:border-zinc-700',
    },
    primary: {
        accent: 'bg-red-500',
        iconBg: 'bg-red-50 text-red-600 ring-red-100 dark:bg-red-950/40 dark:text-red-400 dark:ring-red-900/40',
        hover: 'hover:border-red-200 dark:hover:border-red-900/50',
    },
    success: {
        accent: 'bg-emerald-500',
        iconBg: 'bg-emerald-50 text-emerald-600 ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-400 dark:ring-emerald-900/40',
        hover: 'hover:border-emerald-200 dark:hover:border-emerald-900/50',
    },
    warning: {
        accent: 'bg-amber-500',
        iconBg: 'bg-amber-50 text-amber-600 ring-amber-100 dark:bg-amber-950/40 dark:text-amber-400 dark:ring-amber-900/40',
        hover: 'hover:border-amber-200 dark:hover:border-amber-900/50',
    },
    info: {
        accent: 'bg-blue-500',
        iconBg: 'bg-blue-50 text-blue-600 ring-blue-100 dark:bg-blue-950/40 dark:text-blue-400 dark:ring-blue-900/40',
        hover: 'hover:border-blue-200 dark:hover:border-blue-900/50',
    },
    purple: {
        accent: 'bg-violet-500',
        iconBg: 'bg-violet-50 text-violet-600 ring-violet-100 dark:bg-violet-950/40 dark:text-violet-400 dark:ring-violet-900/40',
        hover: 'hover:border-violet-200 dark:hover:border-violet-900/50',
    },
};
</script>

<template>
    <div
        class="group relative flex flex-col gap-3 overflow-hidden rounded-xl border border-border bg-card p-4 pl-5 shadow-xs transition-all duration-200 hover:shadow-sm"
        :class="tones[tone].hover"
    >
        <!-- Franja de color: identifica la métrica de un vistazo -->
        <span class="absolute inset-y-0 left-0 w-1" :class="tones[tone].accent" aria-hidden="true" />

        <div class="flex items-start justify-between gap-3">
            <p class="pt-0.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">{{ heading }}</p>
            <div
                v-if="hasIcon"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset transition-transform duration-200 group-hover:scale-105"
                :class="tones[tone].iconBg"
            >
                <slot name="icon">
                    <component :is="icon" class="h-4.5 w-4.5" />
                </slot>
            </div>
        </div>

        <div class="space-y-0.5">
            <div class="flex items-baseline gap-2">
                <span class="text-3xl leading-none font-bold tracking-tight text-foreground tabular-nums">{{ displayValue }}</span>
                <span v-if="trend" class="text-xs font-semibold" :class="trend.isPositive ? 'text-emerald-600' : 'text-rose-600'">
                    {{ trend.isPositive ? '↑' : '↓' }} {{ trend.value }}
                </span>
            </div>
            <p v-if="caption" class="text-xs text-muted-foreground">{{ caption }}</p>
        </div>
    </div>
</template>
