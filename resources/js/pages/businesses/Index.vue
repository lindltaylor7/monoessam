<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Business, Headquarter } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Network } from 'lucide-vue-next';
import BusinessOrgTree from './BusinessOrgTree.vue';
import InsertModal from './InsertBusinessModal.vue';

interface Props {
    businesses: Business[];
    headquarters: Headquarter[];
    services: any[];
    dealerships: any[];
    subdealerships: any[];
}

defineProps<Props>();
</script>

<template>
    <Head title="Empresas" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-4 pb-8">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <Network class="h-6 w-6 text-indigo-600" />
                        Estructura Organizacional
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Empresas → Sedes → Áreas. {{ businesses.length }} empresa{{ businesses.length !== 1 ? 's' : '' }} registrada{{
                            businesses.length !== 1 ? 's' : ''
                        }}.
                    </p>
                </div>
                <InsertModal />
            </div>

            <!-- Org chart canvas -->
            <div class="bg-card flex flex-col divide-y divide-dashed divide-zinc-200 overflow-hidden rounded-xl border shadow-sm dark:divide-gray-700">
                <div v-for="business in businesses" :key="business.id" class="overflow-x-auto">
                    <BusinessOrgTree :business="business" :headquarters="headquarters" :all-headquarters="headquarters" :services="services" />
                </div>

                <div v-if="businesses.length === 0" class="flex flex-col items-center gap-3 p-12 text-center text-zinc-400">
                    <Network class="h-10 w-10 opacity-30" />
                    <p class="font-medium">Sin empresas registradas</p>
                    <p class="text-sm">Crea la primera empresa con el botón de arriba.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
