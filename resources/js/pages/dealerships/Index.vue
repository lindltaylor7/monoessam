<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Business, Headquarter } from '@/types';
import DealershipsDialog from '../businesses/DealershipsDialog.vue';
import SubdealershipsDialog from '../businesses/SubdealershipsDialog.vue';
import SubdealershipsTable from '../businesses/SubdealershipsTable.vue';
import { Head } from '@inertiajs/vue3';

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
    <Head title="Concesionarias" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex h-[40px] w-full items-center justify-start gap-1">
                <!-- <InsertModal />
                <ServiceDialog /> -->
                <SubdealershipsDialog :dealerships="dealerships" />
                <DealershipsDialog />
            </div>
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <SubdealershipsTable :subdealerships="subdealerships" :dealerships="dealerships" />
            </div>
        </div>
    </AppLayout>
</template>
