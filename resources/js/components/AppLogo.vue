<script setup lang="ts">
import { UtensilsCrossed } from 'lucide-vue-next';
</script>

<template>
    <div class="flex items-center gap-3 py-1">
        <div
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/15 text-white shadow-inner ring-1 ring-white/20 transition-transform group-hover:scale-105"
        >
            <UtensilsCrossed class="h-4.5 w-4.5" />
        </div>
        <div class="grid flex-1 text-left text-sm leading-none">
            <span class="font-bold tracking-tight text-white text-base">ESSAM</span>
            <span class="text-[10px] font-medium text-white/70 tracking-wider uppercase mt-0.5">ERP Operacional</span>
        </div>
    </div>
</template>
