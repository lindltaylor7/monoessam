<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Cafe, Dinner, Service, Unit } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ShoppingCart } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref } from 'vue';
import SubdealershipsDialog from '../businesses/SubdealershipsDialog.vue';
import DuplicateServiceModal from './DuplicateServiceModal.vue';
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
const dateSelected = ref<string>('');
const showOtherUnitDialog = ref(false);
const doublePrice = ref(false);

const showError = (message: string) =>
    Swal.fire({ icon: 'error', title: 'Error', text: message, confirmButtonColor: '#ef4444' });

const showSuccess = (message: string) =>
    Swal.fire({ icon: 'success', title: '¡Éxito!', text: message, confirmButtonColor: '#6366f1', timer: 3000, timerProgressBar: true, showConfirmButton: false });

const dniDinnerSearched = ref('');

const showDuplicateModal = ref(false);
const duplicateData = ref<any>(null);

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
        showError('Por favor, ingrese un DNI válido (8 dígitos).');
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

const saveSale = (dni: string, force = false) => {
    dniDinnerSearched.value = dni;

    if (!dateSelected.value) {
        showError('Por favor, seleccione una fecha válida.');
        return;
    }

    if (servicesSelectedToSale.value.length === 0) {
        showError('Debe seleccionar al menos un servicio.');
        return;
    }

    const fd = new FormData();
    fd.append('cafe_id', cafeSelected.value.toString());
    fd.append('sale_type_id', saletypeSelected.value.toString());
    fd.append('receipt_type', receiptType.value.toString());
    fd.append('services', JSON.stringify(servicesSelectedToSale.value));
    fd.append('dni', dni.toString());
    fd.append('date', dateSelected.value);
    fd.append('double_price', doublePrice.value.toString());
    fd.append('force', force.toString());

    axios
        .post('/sales', fd)
        .then((response) => {
            if (response.data.dinner) {
                dinnerFound.value = response.data.dinner;
                subdealership.value = response.data.dinner.subdealership;
                showSuccess(response.data.message || 'Venta registrada exitosamente.');
                salesCardRef.value?.cleanInput();
                localSales.value = response.data.sales || [];
            }

            if (response.data.otherCafe) {
                showDialog();
            }
        })
        .catch((error) => {
            if (error.response?.status === 409 && error.response.data.duplicate) {
                duplicateData.value = error.response.data;
                showDuplicateModal.value = true;
                return;
            }

            if (error.response?.status === 403) {
                const data = error.response.data;
                Swal.fire({
                    icon: 'warning',
                    title: 'Acceso restringido',
                    html: `<p class="text-sm text-slate-600">${data.message}</p>${data.dinner ? `<p class="mt-2 text-xs text-slate-400">Comensal: <strong>${data.dinner.name}</strong> · DNI ${data.dinner.dni}</p>` : ''}`,
                    confirmButtonColor: '#f59e0b',
                    confirmButtonText: 'Entendido',
                });
                salesCardRef.value?.cleanInput();
                return;
            }

            const message = error.response?.data?.message || 'Ha ocurrido un error inesperado.';
            showError(message);
            salesCardRef.value?.cleanInput();
        });
};

const confirmForceSale = () => {
    showDuplicateModal.value = false;
    saveSale(dniDinnerSearched.value, true);
};

const todayTotal = computed(() => {
    return localSales.value.reduce((acc, sale) => acc + parseFloat(sale.total || 0), 0);
});
</script>

<template>
    <Head title="Punto de Venta - COMENSALS" />
    <AppLayout>
        <div class="min-h-screen space-y-6 bg-slate-50/50 p-4 md:p-8">
            <!-- Professional Header -->
            <div class="flex flex-col items-start justify-between gap-6 md:flex-row md:items-center">
                <div class="flex items-center gap-4">
                    <div
                        class="bg-primary text-primary-foreground shadow-primary/20 flex h-14 w-14 rotate-3 items-center justify-center rounded-2xl shadow-lg"
                    >
                        <ShoppingCart />
                    </div>
                    <div>
                        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 uppercase">Punto de Venta</h1>
                        <p class="flex items-center gap-2 text-sm font-medium text-slate-500">
                            <span class="flex h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                            Sistema de Registro de Servicios en Tiempo Real
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Card class="flex h-16 items-center gap-8 border-none bg-white px-6 py-3 shadow-sm">
                        <div class="flex flex-col items-end">
                            <span class="mb-1 text-[10px] leading-none font-bold tracking-widest text-slate-400 uppercase">Atendidos</span>
                            <span class="text-2xl leading-none font-black text-slate-700">{{ localSales.length }}</span>
                        </div>
                        <div class="h-8 w-px bg-slate-100"></div>
                        <div class="flex flex-col items-end">
                            <span class="mb-1 text-[10px] leading-none font-bold tracking-widest text-slate-400 uppercase">Total de Hoy</span>
                            <span class="text-primary text-2xl leading-none font-black">S/ {{ todayTotal.toFixed(2) }}</span>
                        </div>
                    </Card>
                    <SubdealershipsDialog :dealerships="dealerships" v-if="permissions.find((p: any) => p.id == 14)" />
                </div>
            </div>

            <!-- Main POS Layout -->
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                <!-- Left Sidebar: Configurations & Service Selection -->
                <div class="space-y-6 xl:col-span-4">
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
                <div class="space-y-6 xl:col-span-8">
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
                            @showDialog="showDialog"
                            @saveSale="saveSale"
                            @updateDni="handleDniUpdate"
                            ref="salesCardRef"
                        />

                        <!-- Recent Transactions -->
                        <div class="overflow-hidden rounded-2xl border border-slate-200/60 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 p-4">
                                <div class="flex items-center gap-2">
                                    <Icon name="history" class="text-slate-400" />
                                    <h3 class="font-bold text-slate-700">Ventas Recientes</h3>
                                </div>
                                <Badge variant="outline" class="bg-white text-xs font-bold">{{ localSales.length }} registros</Badge>
                            </div>
                            <SalesTable :sales="localSales" :paginateData="props.todaySales" :cafeId="cafeSelected" />
                        </div>
                    </div>
                </div>
            </div>

            <OtherUnitDialog :showOtherUnitDialog="showOtherUnitDialog" @hideDialog="hideDialog" @handleDoublePriceSave="handleDoublePriceSave" />

            <DuplicateServiceModal
                :show="showDuplicateModal"
                :data="duplicateData"
                @cancel="showDuplicateModal = false"
                @confirm="confirmForceSale"
            />
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
