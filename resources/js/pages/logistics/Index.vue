<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Headquarter } from '@/types';
import { Head } from '@inertiajs/vue3';
import { GoogleMap, Marker } from 'vue3-google-map';
import { ref, computed } from 'vue';
import { 
    Search, 
    Plus, 
    Truck, 
    Package, 
    Phone, 
    MessageSquare, 
    MoreVertical
} from 'lucide-vue-next';

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
            "featureType": "poi",
            "elementType": "labels",
            "stylers": [{ "visibility": "off" }]
        }
    ]
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
            online: true
        },
        shipping: {
            courier: 'DHL Express',
            type: 'Furniture',
            qty: '10 Package',
            weight: '55 kg',
            price: '$550.99'
        },
        timeline: [
            { status: 'Pick Up', time: '08:00 AM', date: 'Dec 9, 2023', address: '206 Beach Blvd, Miami, FL, 32104', completed: true },
            { status: 'In Transit', time: '10:50 AM', date: 'Dec 10, 2023', address: 'NW Ave, Coral Gables, FL', completed: true },
            { status: 'In Sorting Centre', time: '06:50 PM', date: 'Dec 11, 2023', address: '2711 Haskell Ave, Dallas, TX', completed: true },
            { status: 'On The Way', time: '11:25 AM', date: 'Dec 12, 2023', address: '150 Travis St, Chicago, IL, 20185', completed: false, current: true },
            { status: 'Delivered', time: '03:50 PM', date: 'Dec 12, 2023', address: '102 Collins Ave, Chicago, IL, 20090', completed: false },
        ],
        active: true
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
            online: false
        },
        shipping: {
            courier: 'FedEx',
            type: 'Electronics',
            qty: '5 Package',
            weight: '12 kg',
            price: '$120.00'
        },
        timeline: [],
        active: false
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
            online: false
        },
        shipping: {
            courier: 'UPS',
            type: 'Clothing',
            qty: '20 Package',
            weight: '100 kg',
            price: '$800.00'
        },
        timeline: [],
        active: false
    }
]);

const activeLoad = computed(() => loads.value.find(l => l.active) || loads.value[0]);

const activateLoad = (id: string) => {
    loads.value.forEach(l => l.active = (l.id === id));
};

const statusColors: Record<string, string> = {
    'En camino': 'text-orange-500 bg-orange-50',
    'En centro de clasificación': 'text-purple-500 bg-purple-50',
    'En tránsito': 'text-blue-500 bg-blue-50',
    'Entregado': 'text-green-500 bg-green-50'
};
</script>

<template>
    <Head title="Logística" />
    <AppLayout>
        <!-- Layout Container -->
        <div class="flex h-full w-full gap-6 p-6 bg-[#F5F6FA]">
            
            <!-- LEFT SIDEBAR: LIST -->
            <div class="flex flex-col w-96 shrink-0 gap-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-black tracking-tighter text-gray-900">Logística</h2>
                    <button class="text-gray-400 hover:text-gray-600 transition-colors">
                        <MoreVertical class="w-5 h-5" />
                    </button>
                </div>

                <!-- Search -->
                <div>
                     <div class="relative mb-4">
                        <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input 
                            type="text" 
                            placeholder="Buscar número de seguimiento" 
                            class="w-full pl-10 pr-4 py-3 rounded-2xl border-none bg-white shadow-sm text-sm focus:ring-2 focus:ring-red-400 outline-none placeholder-gray-400"
                        >
                    </div>
                    <button class="w-full py-3 bg-red-600 hover:bg-red-700 active:bg-red-800 text-white font-bold rounded-2xl shadow-lg shadow-red-200/50 flex items-center justify-center gap-2 transition-all">
                        <Plus class="w-5 h-5" />
                        Añadir Carga
                    </button>
                </div>

                <!-- Card List -->
                <div class="flex flex-col gap-4 overflow-y-auto flex-1 pr-1 custom-scroll">
                    <div 
                        v-for="load in loads" 
                        :key="load.id"
                        @click="activateLoad(load.id)"
                        class="p-5 rounded-[1.5rem] cursor-pointer transition-all duration-200 relative group overflow-hidden"
                        :class="load.active ? 'bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] border-2 border-red-600' : 'bg-white border text-gray-400 hover:border-gray-200'"
                    >
                         <!-- Status Badge -->
                        <div class="flex justify-between items-start mb-4 relative z-10">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center transition-colors"
                                     :class="load.active ? 'bg-gray-100 text-gray-800' : 'bg-gray-50 text-gray-300'">
                                    <Package class="w-5 h-5" />
                                </div>
                                <span class="font-bold text-lg" :class="load.active ? 'text-gray-900' : 'text-gray-500'">{{ load.id }}</span>
                            </div>
                            <span class="text-[10px] font-bold px-3 py-1.5 rounded-full uppercase tracking-wider" 
                                  :class="statusColors[load.status] || 'bg-gray-100 text-gray-400'">
                                {{ load.status }}
                            </span>
                        </div>

                        <!-- Active State Content -->
                        <template v-if="load.active">
                            <div class="mb-4 relative z-10">
                                <div class="flex justify-between items-end mb-1">
                                    <span class="text-xs text-gray-400">Tiempo estimado</span>
                                    <span class="text-xs font-bold text-gray-700">{{ load.date }}</span>
                                </div>
                                <div class="text-3xl font-black text-gray-900 tracking-tight">{{ load.eta }}</div>
                            </div>
                            
                            <!-- Tracking Bar -->
                            <div class="flex items-center gap-3 mb-6 relative z-10">
                                <span class="w-3 h-3 rounded-full bg-[#84CC16]"></span>
                                <div class="flex-1 h-3 p-0.5 bg-gray-100 rounded-full">
                                     <div class="h-full w-1/2 bg-[#84CC16] rounded-full relative">
                                        <Truck class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2 w-4 h-4 text-gray-800 fill-white" />
                                     </div>
                                </div>
                                <MapPin class="w-4 h-4 text-gray-400" />
                            </div>
                            
                             <div class="flex justify-between text-[11px] text-gray-500 mb-6 relative z-10">
                                <div class="flex flex-col">
                                    <span class="font-bold text-gray-900 truncate max-w-[100px]">Miami, FL</span>
                                    <span class="truncate max-w-[100px]">{{ load.from }}</span>
                                </div>
                                <div class="flex flex-col text-right">
                                    <span class="font-bold text-gray-900 truncate max-w-[100px]">Chicago, IL</span>
                                    <span class="truncate max-w-[100px]">{{ load.to }}</span>
                                </div>
                            </div>

                            <!-- Driver Info Mini -->
                            <div class="flex items-center justify-between pt-4 border-t border-gray-100 relative z-10">
                                <div class="flex items-center gap-3">
                                    <img :src="load.driver.avatar" class="w-10 h-10 rounded-full object-cover">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-gray-900">{{ load.driver.name }}</span>
                                        <span class="text-[10px] text-gray-400 uppercase font-bold">Conductor</span>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button class="w-9 h-9 rounded-full bg-[#D9F99D] flex items-center justify-center text-[#365314] hover:scale-110 transition-transform">
                                        <Phone class="w-4 h-4" />
                                    </button>
                                    <button class="w-9 h-9 rounded-full bg-[#84CC16] flex items-center justify-center text-white hover:scale-110 transition-transform">
                                        <MessageSquare class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </template>
                        <!-- Inactive State Content -->
                        <template v-else>
                            <div class="flex justify-between items-center text-xs text-gray-400 pt-2 border-t border-gray-50">
                                <span>{{ load.from.split(',')[0] }}</span>
                                <Truck class="w-4 h-4 text-gray-300" />
                                <span>{{ load.to.split(',')[0] }}</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- RIGHT MAIN PANEL -->
            <div class="flex-1 flex flex-col gap-6 overflow-hidden">
                <!-- Map -->
                <div class="flex-grow shrink-0 h-[55%] relative rounded-[2rem] overflow-hidden shadow-sm border border-gray-200 group">
                     <GoogleMap
                        :api-key="GOOGLE_MAPS_API_KEY"
                        style="width: 100%; height: 100%"
                        :center="center"
                        :zoom="zoom"
                        :options="mapOptions"
                    >
                        <Marker :options="{ position: center }" />
                    </GoogleMap>
                    
                    <!-- Overlay Buttons on Map (Optional) -->
                    <div class="absolute right-4 bottom-4 flex flex-col gap-2">
                         <button class="bg-white p-2 rounded-xl shadow-md text-gray-600 hover:text-black">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l14 0"/><path d="M12 5l0 14"/></svg>
                         </button>
                         <button class="bg-white p-2 rounded-xl shadow-md text-gray-600 hover:text-black">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l14 0"/></svg>
                         </button>
                    </div>
                </div>

                <!-- Info Grid -->
                <div v-if="activeLoad" class="h-[40%] min-h-[300px] flex gap-6">
                    <!-- Shipping Info -->
                    <div class="flex-1 bg-white rounded-[2rem] p-8 shadow-sm border border-gray-100 flex flex-col justify-between">
                        <h3 class="font-bold text-lg text-gray-900">Información del envío</h3>
                        <div class="grid grid-cols-2 gap-y-6 gap-x-2">
                            <div>
                                <div class="text-[11px] font-bold text-gray-400 uppercase mb-1">Número de seguimiento</div>
                                <div class="flex items-center gap-2 font-bold text-gray-900">
                                    {{ activeLoad.id }} 
                                    <Package class="w-3 h-3 text-gray-400" />
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] font-bold text-gray-400 uppercase mb-1">Transportista</div>
                                <div class="font-bold text-gray-900">{{ activeLoad.shipping.courier }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-bold text-gray-400 uppercase mb-1">Cantidad</div>
                                <div class="font-bold text-gray-900">{{ activeLoad.shipping.qty }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-bold text-gray-400 uppercase mb-1">Peso</div>
                                <div class="font-bold text-gray-900">{{ activeLoad.shipping.weight }}</div>
                            </div>
                        </div>
                        <div class="pt-4 border-t border-gray-50 flex justify-between items-end">
                             <div class="text-[11px] font-bold text-gray-400 uppercase">Precio total</div>
                             <div class="text-xl font-black text-gray-900">{{ activeLoad.shipping.price }}</div>
                        </div>
                    </div>

                    <!-- Driver Full Info -->
                    <div class="flex-1 bg-white rounded-[2rem] p-8 shadow-sm border border-gray-100 flex flex-col">
                        <h3 class="font-bold text-lg text-gray-900 mb-6">Información del conductor</h3>
                        
                        <div class="flex items-center gap-4 mb-6">
                            <img :src="activeLoad.driver.avatar" class="w-16 h-16 rounded-full bg-gray-100">
                            <div>
                                <div class="text-xl font-bold text-gray-900">{{ activeLoad.driver.name }}</div>
                                <div class="flex items-center gap-1.5 text-xs font-medium text-[#84CC16]">
                                    <span class="w-2 h-2 rounded-full bg-[#84CC16]"></span> {{ activeLoad.driver.online ? 'Online' : 'Offline' }}
                                </div>
                            </div>
                        </div>

                         <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <div class="text-[11px] font-bold text-gray-400 uppercase mb-1">Número de camión</div>
                                <div class="font-bold text-gray-900">{{ activeLoad.driver.truckNo }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-bold text-gray-400 uppercase mb-1">Tipo de camión</div>
                                <div class="font-bold text-gray-900">{{ activeLoad.driver.truckType }}</div>
                            </div>
                        </div>

                        <div class="flex gap-3 mt-auto">
                            <button class="flex-1 h-12 rounded-2xl border border-gray-200 hover:border-gray-300 hover:bg-gray-50 font-bold text-gray-700 flex items-center justify-center transition-all">
                                <Phone class="w-5 h-5" />
                            </button>
                            <button class="flex-1 h-12 rounded-2xl bg-black text-white hover:bg-gray-800 font-bold flex items-center justify-center transition-all">
                                <MessageSquare class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    <!-- Route Timeline -->
                    <div class="w-96 bg-white rounded-[2rem] p-8 shadow-sm border border-gray-100 flex flex-col overflow-hidden">
                        <h3 class="font-bold text-lg text-gray-900 mb-6">Route Details</h3>
                        <div class="overflow-y-auto pr-2 relative -ml-2 pl-2">
                             <!-- Dashed Line -->
                             <div class="absolute left-[15px] top-2 bottom-0 w-px border-l-2 border-dashed border-gray-200"></div>

                             <div v-for="(stop, i) in activeLoad.timeline" :key="i" class="relative pl-10 pb-8 last:pb-0">
                                <!-- Status Marker -->
                                <div 
                                    class="absolute left-1 top-0 w-3.5 h-3.5 rounded-full border-[3px] border-white ring-1 z-10"
                                    :class="stop.completed || stop.current ? 'bg-[#84CC16] ring-[#84CC16]' : 'bg-gray-300 ring-gray-300'"
                                ></div>
                                
                                <div class="flex justify-between items-start">
                                    <div class="flex flex-col gap-0.5">
                                        <div class="font-bold text-xs text-gray-900">{{ stop.date }}</div>
                                        <div class="text-[10px] text-gray-400 font-medium">{{ stop.time }}</div>
                                    </div>
                                    <div class="flex flex-col items-end gap-0.5 max-w-[140px]">
                                        <div class="font-bold text-xs text-right" :class="stop.current ? 'text-[#84CC16]' : 'text-gray-900'">{{ stop.status }}</div>
                                        <div class="text-[10px] text-gray-500 text-right leading-tight">{{ stop.address }}</div>
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
