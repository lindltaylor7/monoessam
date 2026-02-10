<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Business, Headquarter } from '@/types';
import { ref } from 'vue';
import AreasColumn from './AreasColumn.vue';
import BusinessTable from './BusinessTable.vue';
import InsertModal from './InsertBusinessModal.vue';
import { Head } from '@inertiajs/vue3';

interface Props {
    businesses: Business[];
    headquarters: Headquarter[];
    services: any[];
    dealerships: any[];
    subdealerships: any[];
}

const areasSelected = ref([]);

const selectAreasFromHeadquarter = (headquarter: Headquarter) => {
    areasSelected.value = headquarter.areas;
};

defineProps<Props>();
</script>

<template>
    <Head title="Empresas" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex h-[40px] w-full items-center justify-start gap-1">
                <InsertModal />
            </div>
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <BusinessTable :businesses="businesses" :headquarters="headquarters" :services="services" @selectAreas="selectAreasFromHeadquarter" />
                <AreasColumn :areas="areasSelected" />
            </div>
        </div>
    </AppLayout>
</template>
