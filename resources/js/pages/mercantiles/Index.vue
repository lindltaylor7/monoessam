<script setup lang="ts">
import AdminMercantil from './AdminMercantil.vue';

defineProps<{
    mercantiles: any[];
    units: any[];
    mines?: any[];
    globalStats?: any;
}>();
</script>

<template>
    <AdminMercantil :mercantiles="mercantiles" :units="units" :mines="mines" :globalStats="globalStats" />
</template>

