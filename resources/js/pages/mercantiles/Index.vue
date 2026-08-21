<script setup lang="ts">
import AdminMercantil from './AdminMercantil.vue';

defineProps<{
    mercantiles: any[];
    units: any[];
    globalStats?: any;
}>();
</script>

<template>
    <AdminMercantil :mercantiles="mercantiles" :units="units" :globalStats="globalStats" />
</template>
