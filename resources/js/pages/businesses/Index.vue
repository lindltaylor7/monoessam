<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Business, Headquarter } from '@/types';
import BusinessTable from './BusinessTable.vue';
import HeadquartersTable from './HeadquartersTable.vue';
import InsertModal from './InsertBusinessModal.vue';
import ServiceDialog from './ServiceDialog.vue';
import ServicesTable from './ServicesTable.vue';

interface Props {
    businesses: Business[];
    headquarters: Headquarter[];
}

defineProps<Props>();
</script>

<template>
    <Head title="Usuarios" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex h-[40px] w-full items-center justify-start gap-1">
                <InsertModal />
                <ServiceDialog />
            </div>
            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                <BusinessTable :businesses="businesses" />
                <HeadquartersTable :headquarters="headquarters" />
                <ServicesTable />
            </div>
        </div>
    </AppLayout>
</template>
