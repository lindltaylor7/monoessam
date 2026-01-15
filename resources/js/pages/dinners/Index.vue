<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Cafe, Dinner, Service, Unit } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, watch } from 'vue';
import SubdealershipsDialog from '../businesses/SubdealershipsDialog.vue';
import Alert from './Alert.vue';
import OtherUnitDialog from './OtherUnitDialog.vue';
import SalesCard from './SalesCard.vue';
import SalesHeader from './SalesHeader.vue';
import SalesTable from './SalesTable.vue';

const salesCardRef = ref<InstanceType<typeof SalesCard> | null>(null);

interface Props {
    dinners: Dinner[];
    services: Service[];
    units: Unit[];
    cafes: Cafe[];
    sale_types: any[];
    receipt_types: any[];
    todaySales: any;
    subdealerships: any[];
    dealerships: any[];
}


const page = usePage();

const permissions = page.props.auth.permissions;

const props = defineProps<Props>();

const servicesSelectedToSale = ref([]);

const verifyServiceSelected = (service: Service) => {
    return servicesSelectedToSale.value.some((s) => s.serviceID === service.id);
};

const addServiceSelected = (service: Service) => {
    if (verifyServiceSelected(service)) {
        servicesSelectedToSale.value = servicesSelectedToSale.value.filter((s) => s.serviceID !== service.id);
        return;
    }

    servicesSelectedToSale.value.push({
        serviceID: service.id,
        quantity: service.quantity || 1,
        price: service.quantity * service.pivot.price,
        code: service.code,
        name: service.name,
        unit_price: service.pivot.price,
        service_type: service.type,
    });
};

const showServicesFromCafeSelected = (services, sales) => {
    servicesSelected.value = services;
    localSales.value = sales;
};

const localSales = ref([...props.todaySales.data]);


const servicesSelected = ref([]);
const showAlert = ref(false);
const textAlert = ref('');
const typeAlert = ref('');
const dateSelected = ref('');

const showOtherUnitDialog = ref(false);
const doublePrice = ref(false);

const handleShowAlert = (typeAlertComing: string, payload: any) => {
    textAlert.value = payload.response?.data.message || payload || 'Ha ocurrido un error inesperado.';
    typeAlert.value = typeAlertComing;
    showAlert.value = true;
};

const dniDinnerSearched = ref('');

const showDialog = () => {
    showOtherUnitDialog.value = true;
};
const hideDialog = () => {
    showOtherUnitDialog.value = false;
    doublePrice.value = false;
};

const updateDate = (date: String) => {
    dateSelected.value = date;
};

const handleDniUpdate = (dni: string) => {
    if (!dni.trim() || dni.length != 8) {
        alert('Por favor, ingrese un DNI válido.');
        return;
    } else {
        saveSale(dni);
    }
};

const cafeSelected = ref(0);
const saletypeSelected = ref(0);
const receiptType = ref(0);

const handleFormDataUpdate = (formData: SaleFormData) => {
    cafeSelected.value = formData.cafe_id;
    saletypeSelected.value = formData.sale_type_id;
    receiptType.value = formData.receipt_type_id;
};

const handleDoublePriceSave = (dni: string) => {
    doublePrice.value = true;
    saveSale(dniDinnerSearched.value);
};

const dinnerFound = ref({});
const subdealership = ref({});

const saveSale = (dni: String) => {
    dniDinnerSearched.value = dni;

    const fd = new FormData();
    fd.append('cafe_id', cafeSelected.value);
    fd.append('sale_type_id', saletypeSelected.value);
    fd.append('receipt_type', receiptType.value);
    fd.append('services', JSON.stringify(servicesSelectedToSale.value));
    fd.append('dni', dni);
    fd.append('date', dateSelected.value);
    fd.append('double_price', doublePrice.value);

    if (dateSelected.value == null || dateSelected.value == '') {
        handleShowAlert('error', 'Por favor, seleccione una fecha válida.');
        return;
    }

    axios
        .post('./sales', fd)
        .then((response) => {
            if (response.data.dinner) {
                dinnerFound.value = response.data.dinner;
                subdealership.value = response.data.dinner.subdealership;
                handleShowAlert('success', response.data.message || 'Venta registrada exitosamente.');
                salesCardRef.value?.cleanInput();
                localSales.value = response.data.sales || [];
            }

            if (response.data.otherCafe) {
                console.log(dniDinnerSearched.value);
                showDialog();
            }
        })
        .catch((error) => {
            console.error('Error fetching dinners:', error);
            handleShowAlert('error', error);
            salesCardRef.value?.cleanInput();
        });
};
</script>
<template>
    <Head title="Comensales" />
    <AppLayout>
        <Alert :showAlert="showAlert" :type="typeAlert" :description="textAlert" />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h2 class="text-center text-2xl font-semibold">Punto de Venta</h2>
            <SubdealershipsDialog :dealerships="dealerships" v-if="permissions.find((p) => p.id == 14)" />
            <SalesHeader
            :units="units"
                :cafes="cafes"
                :subdealerships="subdealerships"
                :services="services"
                :receipt_types="receipt_types"
                :sale_types="sale_types"
                @showServicesFromCafeSelected="showServicesFromCafeSelected"
                @updateDate="updateDate"
                @updateFormData="handleFormDataUpdate"
                @addServiceSelected="addServiceSelected"
            />

            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                <SalesCard
                    :dinnerFound="dinnerFound"
                    :subdealership="subdealership"
                    @handleShowAlert="handleShowAlert"
                    @showDialog="showDialog"
                    @saveSale="saveSale"
                    @updateDni="handleDniUpdate"
                    ref="salesCardRef"
                />
                <SalesTable :sales="localSales" :paginateData="props.todaySales" :cafeId="cafeSelected"/>
                <OtherUnitDialog :showOtherUnitDialog="showOtherUnitDialog" @hideDialog="hideDialog" @handleDoublePriceSave="handleDoublePriceSave" />
            </div>
        </div>
    </AppLayout>
</template>
