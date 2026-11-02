<script setup lang="ts">
import { type Component } from 'vue';
import { Inbox } from 'lucide-vue-next';

interface Props {
    title: string;
    description?: string;
    icon?: Component;
}

withDefaults(defineProps<Props>(), {
    description: '',
    icon: Inbox,
});
</script>

<template>
    <div class="flex flex-col items-center justify-center p-10 text-center">
        <div class="mb-3.5 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted/60 text-muted-foreground/70 ring-1 ring-border/50">
            <component :is="icon" class="h-7 w-7 stroke-[1.5]" />
        </div>
        <h3 class="text-base font-semibold text-foreground">{{ title }}</h3>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-muted-foreground">{{ description }}</p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
