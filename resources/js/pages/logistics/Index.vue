<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Headquarter } from '@/types';
import { Head } from '@inertiajs/vue3';
import { MessageSquare, MoreVertical, Package, Phone, Plus, Search, Truck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { GoogleMap, Marker } from 'vue3-google-map';

// API Key setup
const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

const props = defineProps<{
    headquarters: Headquarter[];
}>();

// Map Config
const center = ref({ lat: -12.05166, lng: -75.21871 }); // Lima, Peru coordinates (example)
const zoom = ref(13);
const mapOptions = {
    disableDefaultUI: true,
    styles: [
        {
            featureType: 'poi',
            elementType: 'labels',
            stylers: [{ visibility: 'off' }],
        },
    ],
};

// Mock Data Structure for UI Demo
interface Load {
    id: string;
    status: 'On The Way' | 'In Sorting Centre' | 'In Transit' | 'Delivered' | 'Pick Up';
    date: string;
    eta: string;
    from: string;
    to: string;
    driver: {
        name: string;
        avatar: string;
        truckNo: string;
        truckType: string;
        online: boolean;
    };
    shipping: {
        courier: string;
        type: string;
        qty: string;
        weight: string;
        price: string;
    };
    timeline: {
        status: string;
        time: string;
        date: string;
        address: string;
        completed: boolean;
        current?: boolean;
    }[];
    active: boolean;
}

const loads = ref<Load[]>([
    {
        id: '#657890',
        status: 'En camino',
        date: 'Dec 12, 2023',
        eta: '03:50 PM',
        from: '206 Beach Blvd, Miami',
        to: '102 Collins Ave, Chicago',
        driver: {
            name: 'Muhammad Ali',
            avatar: 'https://i.pravatar.cc/150?u=a042581f4e29026024d',
            truckNo: 'B 3129 KVK',
            truckType: 'Trailer Truck',
            online: true,
        },
        shipping: {
            courier: 'DHL Express',
            type: 'Furniture',
            qty: '10 Package',
            weight: '55 kg',
            price: '$550.99',
        },
        timeline: [
            { status: 'Pick Up', time: '08:00 AM', date: 'Dec 9, 2023', address: '206 Beach Blvd, Miami, FL, 32104', completed: true },
            { status: 'In Transit', time: '10:50 AM', date: 'Dec 10, 2023', address: 'NW Ave, Coral Gables, FL', completed: true },
            { status: 'In Sorting Centre', time: '06:50 PM', date: 'Dec 11, 2023', address: '2711 Haskell Ave, Dallas, TX', completed: true },
            {
                status: 'On The Way',
                time: '11:25 AM',
                date: 'Dec 12, 2023',
                address: '150 Travis St, Chicago, IL, 20185',
                completed: false,
                current: true,
            },
            { status: 'Delivered', time: '03:50 PM', date: 'Dec 12, 2023', address: '102 Collins Ave, Chicago, IL, 20090', completed: false },
        ],
        active: true,
    },
    {
        id: '#540775',
        status: 'In Sorting Centre',
        date: 'Dec 14, 2023',
        eta: 'Pending',
        from: 'Dallas, TX',
        to: 'Houston, TX',
        driver: {
            name: 'John Doe',
            avatar: 'https://i.pravatar.cc/150?u=fake',
            truckNo: '--',
            truckType: '--',
            online: false,
        },
        shipping: {
            courier: 'FedEx',
            type: 'Electronics',
            qty: '5 Package',
            weight: '12 kg',
            price: '$120.00',
        },
        timeline: [],
        active: false,
    },
    {
        id: '#201998',
        status: 'In Transit',
        date: 'Dec 15, 2023',
        eta: 'Pending',
        from: 'New York, NY',
        to: 'Boston, MA',
        driver: {
            name: 'Jane Smith',
            avatar: 'https://i.pravatar.cc/150?u=fake2',
            truckNo: '--',
            truckType: '--',
            online: false,
        },
        shipping: {
            courier: 'UPS',
            type: 'Clothing',
            qty: '20 Package',
            weight: '100 kg',
            price: '$800.00',
        },
        timeline: [],
        active: false,
    },
]);

const activeLoad = computed(() => loads.value.find((l) => l.active) || loads.value[0]);

const activateLoad = (id: string) => {
    loads.value.forEach((l) => (l.active = l.id === id));
};

const statusColors: Record<string, string> = {
    'En camino': 'text-orange-500 bg-orange-50',
    'En centro de clasificación': 'text-purple-500 bg-purple-50',
    'En tránsito': 'text-blue-500 bg-blue-50',
    Entregado: 'text-green-500 bg-green-50',
};
</script>

<template>
    <Head title="Logística" />
    <AppLayout>
        <!-- Layout Container -->
        <div class="flex h-full w-full gap-6 bg-[#F5F6FA] p-6">
            <!-- LEFT SIDEBAR: LIST -->
            <div class="flex w-96 shrink-0 flex-col gap-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-black tracking-tighter text-gray-900">Logística</h2>
                    <button class="text-gray-400 transition-colors hover:text-gray-600">
                        <MoreVertical class="h-5 w-5" />
                    </button>
                </div>

                <!-- Search -->
                <div>
                    <div class="relative mb-4">
                        <Search class="absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Buscar número de seguimiento"
                            class="w-full rounded-2xl border-none bg-white py-3 pr-4 pl-10 text-sm placeholder-gray-400 shadow-sm outline-none focus:ring-2 focus:ring-red-400"
                        />
                    </div>
                    <button
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-red-600 py-3 font-bold text-white shadow-lg shadow-red-200/50 transition-all hover:bg-red-700 active:bg-red-800"
                    >
                        <Plus class="h-5 w-5" />
                        Añadir Carga
                    </button>
                </div>

                <!-- Card List -->
                <div class="custom-scroll flex flex-1 flex-col gap-4 overflow-y-auto pr-1">
                    <div
                        v-for="load in loads"
                        :key="load.id"
                        @click="activateLoad(load.id)"
                        class="group relative cursor-pointer overflow-hidden rounded-[1.5rem] p-5 transition-all duration-200"
                        :class="
                            load.active
                                ? 'border-2 border-red-600 bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)]'
                                : 'border bg-white text-gray-400 hover:border-gray-200'
                        "
                    >
                        <!-- Status Badge -->
                        <div class="relative z-10 mb-4 flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-full transition-colors"
                                    :class="load.active ? 'bg-gray-100 text-gray-800' : 'bg-gray-50 text-gray-300'"
                                >
                                    <Package class="h-5 w-5" />
                                </div>
                                <span class="text-lg font-bold" :class="load.active ? 'text-gray-900' : 'text-gray-500'">{{ load.id }}</span>
                            </div>
                            <span
                                class="rounded-full px-3 py-1.5 text-[10px] font-bold tracking-wider uppercase"
                                :class="statusColors[load.status] || 'bg-gray-100 text-gray-400'"
                            >
                                {{ load.status }}
                            </span>
                        </div>

                        <!-- Active State Content -->
                        <template v-if="load.active">
                            <div class="relative z-10 mb-4">
                                <div class="mb-1 flex items-end justify-between">
                                    <span class="text-xs text-gray-400">Tiempo estimado</span>
                                    <span class="text-xs font-bold text-gray-700">{{ load.date }}</span>
                                </div>
                                <div class="text-3xl font-black tracking-tight text-gray-900">{{ load.eta }}</div>
                            </div>

                            <!-- Tracking Bar -->
                            <div class="relative z-10 mb-6 flex items-center gap-3">
                                <span class="h-3 w-3 rounded-full bg-[#84CC16]"></span>
                                <div class="h-3 flex-1 rounded-full bg-gray-100 p-0.5">
                                    <div class="relative h-full w-1/2 rounded-full bg-[#84CC16]">
                                        <Truck class="absolute top-1/2 right-0 h-4 w-4 translate-x-1/2 -translate-y-1/2 fill-white text-gray-800" />
                                    </div>
                                </div>
                                <MapPin class="h-4 w-4 text-gray-400" />
                            </div>

                            <div class="relative z-10 mb-6 flex justify-between text-[11px] text-gray-500">
                                <div class="flex flex-col">
                                    <span class="max-w-[100px] truncate font-bold text-gray-900">Miami, FL</span>
                                    <span class="max-w-[100px] truncate">{{ load.from }}</span>
                                </div>
                                <div class="flex flex-col text-right">
                                    <span class="max-w-[100px] truncate font-bold text-gray-900">Chicago, IL</span>
                                    <span class="max-w-[100px] truncate">{{ load.to }}</span>
                                </div>
                            </div>

                            <!-- Driver Info Mini -->
                            <div class="relative z-10 flex items-center justify-between border-t border-gray-100 pt-4">
                                <div class="flex items-center gap-3">
                                    <img :src="load.driver.avatar" class="h-10 w-10 rounded-full object-cover" />
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-gray-900">{{ load.driver.name }}</span>
                                        <span class="text-[10px] font-bold text-gray-400 uppercase">Conductor</span>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        class="flex h-9 w-9 items-center justify-center rounded-full bg-[#D9F99D] text-[#365314] transition-transform hover:scale-110"
                                    >
                                        <Phone class="h-4 w-4" />
                                    </button>
                                    <button
                                        class="flex h-9 w-9 items-center justify-center rounded-full bg-[#84CC16] text-white transition-transform hover:scale-110"
                                    >
                                        <MessageSquare class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </template>
                        <!-- Inactive State Content -->
                        <template v-else>
                            <div class="flex items-center justify-between border-t border-gray-50 pt-2 text-xs text-gray-400">
                                <span>{{ load.from.split(',')[0] }}</span>
                                <Truck class="h-4 w-4 text-gray-300" />
                                <span>{{ load.to.split(',')[0] }}</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- RIGHT MAIN PANEL -->
            <div class="flex flex-1 flex-col gap-6 overflow-hidden">
                <!-- Map -->
                <div class="group relative h-[55%] shrink-0 flex-grow overflow-hidden rounded-[2rem] border border-gray-200 shadow-sm">
                    <GoogleMap :api-key="GOOGLE_MAPS_API_KEY" style="width: 100%; height: 100%" :center="center" :zoom="zoom" :options="mapOptions">
                        <Marker :options="{ position: center }" />
                    </GoogleMap>

                    <!-- Overlay Buttons on Map (Optional) -->
                    <div class="absolute right-4 bottom-4 flex flex-col gap-2">
                        <button class="rounded-xl bg-white p-2 text-gray-600 shadow-md hover:text-black">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M5 12l14 0" />
                                <path d="M12 5l0 14" />
                            </svg>
                        </button>
                        <button class="rounded-xl bg-white p-2 text-gray-600 shadow-md hover:text-black">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M5 12l14 0" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Info Grid -->
                <div v-if="activeLoad" class="flex h-[40%] min-h-[300px] gap-6">
                    <!-- Shipping Info -->
                    <div class="flex flex-1 flex-col justify-between rounded-[2rem] border border-gray-100 bg-white p-8 shadow-sm">
                        <h3 class="text-lg font-bold text-gray-900">Información del envío</h3>
                        <div class="grid grid-cols-2 gap-x-2 gap-y-6">
                            <div>
                                <div class="mb-1 text-[11px] font-bold text-gray-400 uppercase">Número de seguimiento</div>
                                <div class="flex items-center gap-2 font-bold text-gray-900">
                                    {{ activeLoad.id }}
                                    <Package class="h-3 w-3 text-gray-400" />
                                </div>
                            </div>
                            <div>
                                <div class="mb-1 text-[11px] font-bold text-gray-400 uppercase">Transportista</div>
                                <div class="font-bold text-gray-900">{{ activeLoad.shipping.courier }}</div>
                            </div>
                            <div>
                                <div class="mb-1 text-[11px] font-bold text-gray-400 uppercase">Cantidad</div>
                                <div class="font-bold text-gray-900">{{ activeLoad.shipping.qty }}</div>
                            </div>
                            <div>
                                <div class="mb-1 text-[11px] font-bold text-gray-400 uppercase">Peso</div>
                                <div class="font-bold text-gray-900">{{ activeLoad.shipping.weight }}</div>
                            </div>
                        </div>
                        <div class="flex items-end justify-between border-t border-gray-50 pt-4">
                            <div class="text-[11px] font-bold text-gray-400 uppercase">Precio total</div>
                            <div class="text-xl font-black text-gray-900">{{ activeLoad.shipping.price }}</div>
                        </div>
                    </div>

                    <!-- Driver Full Info -->
                    <div class="flex flex-1 flex-col rounded-[2rem] border border-gray-100 bg-white p-8 shadow-sm">
                        <h3 class="mb-6 text-lg font-bold text-gray-900">Información del conductor</h3>

                        <div class="mb-6 flex items-center gap-4">
                            <img :src="activeLoad.driver.avatar" class="h-16 w-16 rounded-full bg-gray-100" />
                            <div>
                                <div class="text-xl font-bold text-gray-900">{{ activeLoad.driver.name }}</div>
                                <div class="flex items-center gap-1.5 text-xs font-medium text-[#84CC16]">
                                    <span class="h-2 w-2 rounded-full bg-[#84CC16]"></span> {{ activeLoad.driver.online ? 'Online' : 'Offline' }}
                                </div>
                            </div>
                        </div>

                        <div class="mb-6 grid grid-cols-2 gap-4">
                            <div>
                                <div class="mb-1 text-[11px] font-bold text-gray-400 uppercase">Número de camión</div>
                                <div class="font-bold text-gray-900">{{ activeLoad.driver.truckNo }}</div>
                            </div>
                            <div>
                                <div class="mb-1 text-[11px] font-bold text-gray-400 uppercase">Tipo de camión</div>
                                <div class="font-bold text-gray-900">{{ activeLoad.driver.truckType }}</div>
                            </div>
                        </div>

                        <div class="mt-auto flex gap-3">
                            <button
                                class="flex h-12 flex-1 items-center justify-center rounded-2xl border border-gray-200 font-bold text-gray-700 transition-all hover:border-gray-300 hover:bg-gray-50"
                            >
                                <Phone class="h-5 w-5" />
                            </button>
                            <button
                                class="flex h-12 flex-1 items-center justify-center rounded-2xl bg-black font-bold text-white transition-all hover:bg-gray-800"
                            >
                                <MessageSquare class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    <!-- Route Timeline -->
                    <div class="flex w-96 flex-col overflow-hidden rounded-[2rem] border border-gray-100 bg-white p-8 shadow-sm">
                        <h3 class="mb-6 text-lg font-bold text-gray-900">Route Details</h3>
                        <div class="relative -ml-2 overflow-y-auto pr-2 pl-2">
                            <!-- Dashed Line -->
                            <div class="absolute top-2 bottom-0 left-[15px] w-px border-l-2 border-dashed border-gray-200"></div>

                            <div v-for="(stop, i) in activeLoad.timeline" :key="i" class="relative pb-8 pl-10 last:pb-0">
                                <!-- Status Marker -->
                                <div
                                    class="absolute top-0 left-1 z-10 h-3.5 w-3.5 rounded-full border-[3px] border-white ring-1"
                                    :class="stop.completed || stop.current ? 'bg-[#84CC16] ring-[#84CC16]' : 'bg-gray-300 ring-gray-300'"
                                ></div>

                                <div class="flex items-start justify-between">
                                    <div class="flex flex-col gap-0.5">
                                        <div class="text-xs font-bold text-gray-900">{{ stop.date }}</div>
                                        <div class="text-[10px] font-medium text-gray-400">{{ stop.time }}</div>
                                    </div>
                                    <div class="flex max-w-[140px] flex-col items-end gap-0.5">
                                        <div class="text-right text-xs font-bold" :class="stop.current ? 'text-[#84CC16]' : 'text-gray-900'">
                                            {{ stop.status }}
                                        </div>
                                        <div class="text-right text-[10px] leading-tight text-gray-500">{{ stop.address }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scroll::-webkit-scrollbar {
    width: 0px;
    background: transparent;
}
</style>
