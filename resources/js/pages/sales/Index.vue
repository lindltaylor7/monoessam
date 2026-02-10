<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Cafe, Dinner, Service, Unit } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, watch, computed } from 'vue';
import SubdealershipsDialog from '../businesses/SubdealershipsDialog.vue';
import Alert from './Alert.vue';
import OtherUnitDialog from './OtherUnitDialog.vue';
import SalesCard from './SalesCard.vue';
import SalesHeader from './SalesHeader.vue';
import SalesTable from './SalesTable.vue';
import Icon from '@/components/Icon.vue';
import { Card, CardContent } from '@/components/ui/card';
import { ShoppingCart } from 'lucide-vue-next';

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

const page = usePage<any>();
const permissions = page.props.auth.permissions;
const props = defineProps<Props>();

const servicesSelectedToSale = ref<any[]>([]);

const verifyServiceSelected = (service: Service) => {
    return servicesSelectedToSale.value.some((s) => s.serviceID === service.id);
};

const addServiceSelected = (service: any) => {
    if (verifyServiceSelected(service)) {
        servicesSelectedToSale.value = servicesSelectedToSale.value.filter((s) => s.serviceID !== service.id);
        return;
    }

    servicesSelectedToSale.value.push({
        serviceID: service.id,
        quantity: service.quantity || 1,
        price: (service.quantity || 1) * (service.pivot?.price || 0),
        code: service.code,
        name: service.name,
        unit_price: service.pivot?.price || 0,
        service_type: service.type,
    });
};

const showServicesFromCafeSelected = (services: any[], cafeId: number) => {
    servicesSelected.value = services;
    const salesByCafe = props.todaySales.data.filter((sale: any) => sale.cafe_id == cafeId);
    localSales.value = salesByCafe;
};

const localSales = ref<any[]>([...(props.todaySales?.data || [])]);
const servicesSelected = ref<any[]>([]);
const showAlert = ref(false);
const textAlert = ref('');
const typeAlert = ref('');
const dateSelected = ref<string>('');
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

const updateDate = (date: string) => {
    dateSelected.value = date;
};

const handleDniUpdate = (dni: string) => {
    if (!dni.trim() || dni.length != 8) {
        handleShowAlert('error', 'Por favor, ingrese un DNI válido (8 dígitos).');
        return;
    }
    saveSale(dni);
};

const cafeSelected = ref(0);
const saletypeSelected = ref(0);
const receiptType = ref(0);

const handleFormDataUpdate = (formData: any) => {
    cafeSelected.value = formData.cafe_id;
    saletypeSelected.value = formData.sale_type_id;
    receiptType.value = formData.receipt_type_id;
};

const handleDoublePriceSave = (dni: string) => {
    doublePrice.value = true;
    saveSale(dniDinnerSearched.value);
};

const dinnerFound = ref<any>({});
const subdealership = ref<any>({});

const saveSale = (dni: string) => {
    dniDinnerSearched.value = dni;

    const fd = new FormData();
    fd.append('cafe_id', cafeSelected.value.toString());
    fd.append('sale_type_id', saletypeSelected.value.toString());
    fd.append('receipt_type', receiptType.value.toString());
    fd.append('services', JSON.stringify(servicesSelectedToSale.value));
    fd.append('dni', dni.toString());
    fd.append('date', dateSelected.value);
    fd.append('double_price', doublePrice.value.toString());

    if (!dateSelected.value) {
        handleShowAlert('error', 'Por favor, seleccione una fecha válida.');
        return;
    }

    if (servicesSelectedToSale.value.length === 0) {
        handleShowAlert('error', 'Debe seleccionar al menos un servicio.');
        return;
    }

    axios
        .post('/sales', fd)
        .then((response) => {
            if (response.data.dinner) {
                dinnerFound.value = response.data.dinner;
                subdealership.value = response.data.dinner.subdealership;
                handleShowAlert('success', response.data.message || 'Venta registrada exitosamente.');
                salesCardRef.value?.cleanInput();
                localSales.value = response.data.sales || [];
            }

            if (response.data.otherCafe) {
                showDialog();
            }
        })
        .catch((error) => {
            console.error('Error recording sale:', error);
            handleShowAlert('error', error);
            salesCardRef.value?.cleanInput();
        });
};

const todayTotal = computed(() => {
    return localSales.value.reduce((acc, sale) => acc + parseFloat(sale.total || 0), 0);
});
</script>

<template>
    <Head title="Punto de Venta - COMENSALS" />
    <AppLayout>
        <Alert :showAlert="showAlert" :type="typeAlert" :description="textAlert" @close="showAlert = false" />
        
        <div class="p-4 md:p-8 space-y-6 bg-slate-50/50 min-h-screen">
            <!-- Professional Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="flex items-center gap-4">
                    <div class="h-14 w-14 rounded-2xl bg-primary flex items-center justify-center text-primary-foreground shadow-lg shadow-primary/20 rotate-3">
                        <ShoppingCart/>
                    </div>
                    <div>
                        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 uppercase">Punto de Venta</h1>
                        <p class="text-slate-500 font-medium text-sm flex items-center gap-2">
                            <span class="flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Sistema de Registro de Servicios en Tiempo Real
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Card class="bg-white border-none shadow-sm h-16 flex items-center px-6 py-3 gap-8">
                        <div class="flex flex-col items-end">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest leading-none mb-1">Atendidos</span>
                            <span class="text-2xl font-black text-slate-700 leading-none">{{ localSales.length }}</span>
                        </div>
                        <div class="w-px h-8 bg-slate-100"></div>
                        <div class="flex flex-col items-end">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest leading-none mb-1">Total de Hoy</span>
                            <span class="text-2xl font-black text-primary leading-none">S/ {{ todayTotal.toFixed(2) }}</span>
                        </div>
                    </Card>
                    <SubdealershipsDialog :dealerships="dealerships" v-if="permissions.find((p: any) => p.id == 14)" />
                </div>
            </div>

            <!-- Main POS Layout -->
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
                <!-- Left Sidebar: Configurations & Service Selection -->
                <div class="xl:col-span-4 space-y-6">
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
                </div>

                <!-- Main Section: Recording & Recent Table -->
                <div class="xl:col-span-8 space-y-6">
                    <div class="grid grid-cols-1 gap-6">
                        <!-- Verification & Recording Card -->
                        <SalesCard
                            :dinnerFound="dinnerFound"
                            :subdealership="subdealership"
                            :servicesSelectedToSale="servicesSelectedToSale"
                            :services="servicesSelected"
                            :cafeSelected="cafeSelected"
                            :saletypeSelected="saletypeSelected"
                            :receiptType="receiptType"
                            @handleShowAlert="handleShowAlert"
                            @showDialog="showDialog"
                            @saveSale="saveSale"
                            @updateDni="handleDniUpdate"
                            ref="salesCardRef"
                        />

                        <!-- Recent Transactions -->
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/60 overflow-hidden">
                            <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                                <div class="flex items-center gap-2">
                                    <Icon name="history" class="text-slate-400" />
                                    <h3 class="font-bold text-slate-700">Ventas Recientes</h3>
                                </div>
                                <Badge variant="outline" class="bg-white font-bold text-xs">{{ localSales.length }} registros</Badge>
                            </div>
                            <SalesTable :sales="localSales" :paginateData="props.todaySales" :cafeId="cafeSelected" />
                        </div>
                    </div>
                </div>
            </div>

            <OtherUnitDialog :showOtherUnitDialog="showOtherUnitDialog" @hideDialog="hideDialog" @handleDoublePriceSave="handleDoublePriceSave" />
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
