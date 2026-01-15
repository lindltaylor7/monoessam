<script lang="ts" setup>
import { useDraggable, useDroppable } from '@vue-dnd-kit/core';
import { ref } from 'vue';

const message = ref('Drag the item to the drop zone');
const isDropped = ref(false);

interface Props {
    users: Array<any>;
}

const props = defineProps<Props>();

const {
    elementRef: draggableRef,
    isDragging,
    handleDragStart,
} = useDraggable({
    id: 'my-draggable',
});

const { elementRef: dropzoneRef, isOvered } = useDroppable({
    id: 'my-dropzone',
    events: {
        onDrop: () => {
            message.value = 'Item dropped successfully!';
            isDropped.value = true;
            setTimeout(() => {
                message.value = 'Drag the item to the drop zone again';
                isDropped.value = false;
            }, 2000);
        },
    },
});
</script>

<template>
    <div class="container">
        <div class="" v-for="user in users" :key="user.id"></div>
        <p>{{ message }}</p>

        <div class="content">
            <div ref="draggableRef" class="draggable" :class="{ dragging: isDragging, dropped: isDropped }" @pointerdown="handleDragStart">
                {{ isDropped ? '✓' : 'Drag me!' }}
            </div>

            <div ref="dropzoneRef" :class="{ over: isOvered, dropped: isDropped }" class="dropzone">Drop here</div>
        </div>
    </div>
</template>

<style scoped>
.container {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
}

.content {
    display: flex;
    gap: 30px;
    margin-top: 20px;
}

p {
    font-size: 16px;
    color: #333;
    margin-bottom: 20px;
}

.draggable {
    padding: 20px;
    background-color: #4caf50;
    color: white;
    border-radius: 4px;
    cursor: grab;
    user-select: none;
    width: 100px;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.dragging {
    opacity: 0.8;
    cursor: grabbing;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

.dropzone {
    padding: 20px;
    background-color: #f5f5f5;
    border: 2px dashed #ccc;
    border-radius: 4px;
    width: 150px;
    height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.over {
    background-color: #e0f7fa;
    border-color: #00bcd4;
}

.dropped {
    background-color: #e8f5e9;
    border-color: #4caf50;
    color: #4caf50;
    font-weight: bold;
}

.dropped {
    background-color: #4caf50;
    color: white;
    font-size: 24px;
}
</style>
