<script setup lang="ts">
import { useDroppable } from '@vue-dnd-kit/core';

const emit = defineEmits<{
    (e: 'drop'): void;
}>();

const { elementRef, isOvered } = useDroppable({
    events: { onDrop: () => emit('drop') },
});
</script>

<template>
    <div
        ref="elementRef"
        :class="{
            droppable: true,
            'is-overed': isOvered,
        }"
        role="region"
        aria-dropeffect="move"
        class="rounded"
    >
        GUARDIA 1
        <slot />
    </div>
</template>

<style scoped>
.droppable {
    width: 300px;
    height: 100%;
    border: 1px solid rgb(206, 206, 206);
}
.is-overed {
    background-color: #f0f0f0;
    border: 1px dashed red;
}
</style>
