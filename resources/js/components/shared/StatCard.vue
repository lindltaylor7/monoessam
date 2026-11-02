<script setup lang="ts">
import { type Component } from 'vue';

interface Props {
    label: string;
    value: string | number;
    subtext?: string;
    icon?: Component;
    variant?: 'default' | 'primary' | 'success' | 'warning' | 'info' | 'purple';
    trend?: {
        value: string | number;
        isPositive?: boolean;
    };
}

const props = withDefaults(defineProps<Props>(), {
    subtext: '',
    icon: undefined,
    variant: 'default',
    trend: undefined,
});

const variantStyles: Record<string, { bg: string; iconBg: string; iconColor: string; valueColor: string }> = {
    default: {
        bg: 'bg-card border-border',
        iconBg: 'bg-muted text-muted-foreground',
        iconColor: 'text-muted-foreground',
        valueColor: 'text-foreground',
    },
    primary: {
        bg: 'bg-card border-border hover:border-red-200 dark:hover:border-red-900/50',
        iconBg: 'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400',
        iconColor: 'text-red-600 dark:text-red-400',
        valueColor: 'text-foreground',
    },
    success: {
        bg: 'bg-card border-border hover:border-emerald-200 dark:hover:border-emerald-900/50',
        iconBg: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400',
        iconColor: 'text-emerald-600 dark:text-emerald-400',
        valueColor: 'text-emerald-700 dark:text-emerald-300',
    },
    warning: {
        bg: 'bg-card border-border hover:border-amber-200 dark:hover:border-amber-900/50',
        iconBg: 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400',
        iconColor: 'text-amber-600 dark:text-amber-400',
        valueColor: 'text-amber-700 dark:text-amber-300',
    },
    info: {
        bg: 'bg-card border-border hover:border-blue-200 dark:hover:border-blue-900/50',
        iconBg: 'bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400',
        iconColor: 'text-blue-600 dark:text-blue-400',
        valueColor: 'text-blue-700 dark:text-blue-300',
    },
    purple: {
        bg: 'bg-card border-border hover:border-violet-200 dark:hover:border-violet-900/50',
        iconBg: 'bg-violet-50 text-violet-600 dark:bg-violet-950/40 dark:text-violet-400',
        iconColor: 'text-violet-600 dark:text-violet-400',
        valueColor: 'text-violet-700 dark:text-violet-300',
    },
};
</script>

<template>
    <div
        class="group relative flex items-center justify-between overflow-hidden rounded-xl border p-4.5 shadow-xs transition-all duration-200 hover:shadow-sm"
        :class="variantStyles[variant].bg"
    >
        <div class="space-y-1">
            <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">{{ label }}</p>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold tracking-tight" :class="variantStyles[variant].valueColor">
                    {{ value }}
                </span>
                <span
                    v-if="trend"
                    class="text-xs font-semibold"
                    :class="trend.isPositive ? 'text-emerald-600' : 'text-rose-600'"
                >
                    {{ trend.isPositive ? '↑' : '↓' }} {{ trend.value }}
                </span>
            </div>
            <p v-if="subtext" class="text-xs text-muted-foreground">{{ subtext }}</p>
        </div>

        <div
            v-if="icon"
            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition-transform duration-200 group-hover:scale-105"
            :class="variantStyles[variant].iconBg"
        >
            <component :is="icon" class="h-5 w-5" />
        </div>
    </div>
</template>
