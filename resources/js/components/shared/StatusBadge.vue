<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

interface Props {
    label: string;
    colorClass?: string;
}

defineProps<Props>();
defineEmits(['click']);
</script>

<template>
    <Badge :class="[colorClass, 'cursor-pointer rounded-sm transition-opacity hover:opacity-80']" @click="$emit('click')">
        {{ label }}
    </Badge>
</template>
