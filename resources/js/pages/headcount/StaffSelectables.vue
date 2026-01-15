<script lang="ts" setup>
import Draggable from './Draggable.vue';

interface Props {
    users: Array<any>;
}

const props = defineProps<Props>();
</script>
<template>
    <div>
        <p class="font-bold">Personal</p>
        <div class="h-[65vh] overflow-y-auto">
            <Draggable v-for="user in users" :user="user" :key="user.id" :showButtonDelete="false" />
        </div>
    </div>
</template>
