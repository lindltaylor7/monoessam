<script setup lang="ts">
import { type Component } from 'vue';

interface Props {
    title: string;
    description?: string;
    icon?: Component;
    iconColor?: string;
    badge?: string;
    badgeVariant?: 'default' | 'secondary' | 'outline' | 'success' | 'warning';
}

withDefaults(defineProps<Props>(), {
    description: '',
    icon: undefined,
    iconColor: 'from-red-600 to-rose-700 text-white',
    badge: '',
    badgeVariant: 'secondary',
});
</script>

<template>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3.5">
            <div
                v-if="icon"
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br shadow-sm transition-transform duration-200"
                :class="iconColor"
            >
                <component :is="icon" class="h-6 w-6 stroke-[2]" />
            </div>

            <div class="space-y-0.5">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ title }}</h1>
                    <span
                        v-if="badge"
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="[
                            badgeVariant === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300' :
                            badgeVariant === 'warning' ? 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300' :
                            'bg-muted text-muted-foreground border border-border'
                        ]"
                    >
                        {{ badge }}
                    </span>
                </div>
                <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
            </div>
        </div>

        <div v-if="$slots.actions || $slots.default" class="flex flex-wrap items-center gap-2.5">
            <slot name="actions">
                <slot />
            </slot>
        </div>
    </div>
</template>
