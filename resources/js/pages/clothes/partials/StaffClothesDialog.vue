<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import { router } from '@inertiajs/vue3';
import { Plus, Trash2, Search, Loader2, Package, Check } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import axios from 'axios';

interface StaffCloth {
    id: number;
    cloth_id: number | null;
    epp_id: number | null;
    clothe_name?: string;
    clothing_size: string;
    status?: string | null;
    color_id: number | null;
    cloth?: { name: string };
    epp?: { name: string };
}

const props = defineProps<{
    open: boolean;
    staff: {
        id: number;
        name: string;
        staff_clothes: StaffCloth[];
    } | null; 
    colors: Array<{ id: number, name: string }>;
}>();

const emit = defineEmits(['update:open']);

const isAssigning = ref(false);
const eppSearch = ref('');
const searchResults = ref<any[]>([]);
const isSearching = ref(false);
const pendingAssignments = ref<any[]>([]);

const newAssignment = ref({
    epp_id: '',
    epp_name: '',
    size: '',
    color_id: '',
    quantity: 1
});

const searchEpps = async (query: string) => {
    if (!query || query.length < 2) {
        searchResults.value = [];
        return;
    }
    // Explicitly clear previous results to avoid showing stale data
    if (query.length === 2) searchResults.value = [];
    
    isSearching.value = true;
    try {
        const response = await axios.get(route('inventory.items.search'), {
            params: { type: 'epp', query }
        });
        searchResults.value = response.data;
    } catch (e) {
        console.error(e);
    } finally {
        isSearching.value = false;
    }
};

let timeout: any = null;
watch(eppSearch, (val) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => searchEpps(val), 300);
});

const selectEpp = (epp: any) => {
    newAssignment.value.epp_id = String(epp.id);
    newAssignment.value.epp_name = epp.name;
    eppSearch.value = '';
    searchResults.value = [];
};

const addPending = () => {
    if (!newAssignment.value.epp_id || !newAssignment.value.size || !newAssignment.value.color_id) return;
    pendingAssignments.value.push({ ...newAssignment.value });
    newAssignment.value = {
        epp_id: '',
        epp_name: '',
        size: '',
        color_id: '',
        quantity: 1
    };
};

const removePending = (index: number) => {
    pendingAssignments.value.splice(index, 1);
};

const submitAssignments = () => {
    if (!props.staff) return;
    router.post(route('inventory.assign-clothes'), {
        staff_id: props.staff.id,
        items: pendingAssignments.value
    }, {
        onSuccess: () => {
            isAssigning.value = false;
            pendingAssignments.value = [];
        },
        preserveScroll: true
    });
};

const updateStatus = (clothEntryId: number, status: string, colorId?: number | null) => {
    router.post(route('clothes.status'), {
        id: clothEntryId,
        status: status,
        color_id: colorId
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['staff'] 
    });
}

const getStatusColor = (status: string) => {
    switch (status) {
        case 'Entregado': return 'bg-red-100 text-red-700 border-red-200';
        case 'Devuelto': return 'bg-green-100 text-green-700 border-green-200';
        default: return 'bg-yellow-100 text-yellow-700 border-yellow-200';
    }
};

watch(() => props.open, (val) => {
    if (!val) {
        isAssigning.value = false;
        pendingAssignments.value = [];
        searchResults.value = [];
        eppSearch.value = '';
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-[750px] max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <div class="flex justify-between items-start">
                    <div>
                        <DialogTitle class="text-xl font-black">Control de EPPs</DialogTitle>
                        <DialogDescription>
                            Gestión de tallas y asignación de EPP para {{ staff?.name }}
                        </DialogDescription>
                    </div>
                    <Button 
                        v-if="!isAssigning"
                        @click="isAssigning = true" 
                        size="sm" 
                        class="bg-indigo-600 hover:bg-indigo-700 text-white gap-2 shadow-lg"
                    >
                        <Plus class="h-4 w-4" /> Nueva Asignación EPP
                    </Button>
                    <Button 
                        v-else
                        @click="isAssigning = false" 
                        size="sm" 
                        variant="ghost" 
                        class="text-slate-500 font-bold uppercase text-[10px]"
                    >
                        Cancelar
                    </Button>
                </div>
            </DialogHeader>
            
            <div class="grid gap-6 py-4" v-if="staff">
                <!-- Assignment Mode -->
                <div v-if="isAssigning" class="space-y-6 animate-in fade-in slide-in-from-top-4 duration-300">
                    <div class="p-4 bg-indigo-50/50 rounded-2xl border border-indigo-100 space-y-4">
                        <h3 class="text-xs font-black uppercase text-indigo-600 tracking-widest flex items-center gap-2">
                            <Plus class="h-4 w-4" /> Agregar EPP al Listado
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2 relative">
                                <Label class="text-[10px] font-bold uppercase text-slate-500">Buscar EPP</Label>
                                <div class="relative">
                                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                    <Input v-model="eppSearch" placeholder="Casco, Guantes..." class="pl-9 bg-white border-none shadow-sm h-10" />
                                    <div v-if="isSearching" class="absolute right-3 top-2.5">
                                        <Loader2 class="h-4 w-4 animate-spin text-indigo-500" />
                                    </div>
                                </div>
                                
                                <div v-if="searchResults.length > 0" class="absolute z-50 w-full mt-1 bg-white border rounded-xl shadow-2xl max-h-48 overflow-y-auto border-slate-100">
                                    <div 
                                        v-for="epp in searchResults" 
                                        :key="epp.id"
                                        @click="selectEpp(epp)"
                                        class="p-3 hover:bg-indigo-50 cursor-pointer flex items-center justify-between text-sm transition-colors border-b last:border-none"
                                    >
                                        <span class="font-bold text-slate-700">{{ epp.name }}</span>
                                        <Check v-if="newAssignment.epp_id === String(epp.id)" class="h-4 w-4 text-indigo-500" />
                                    </div>
                                </div>
                                <div v-if="newAssignment.epp_name" class="text-[10px] text-indigo-600 font-black mt-1 uppercase flex items-center gap-1">
                                    <Check class="h-3 w-3" /> Seleccionado: {{ newAssignment.epp_name }}
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-2">
                                    <Label class="text-[10px] font-bold uppercase text-slate-500">Talla</Label>
                                    <Input v-model="newAssignment.size" placeholder="XL, 32..." class="bg-white border-none shadow-sm h-10" />
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-[10px] font-bold uppercase text-slate-500">Color</Label>
                                    <Select v-model="newAssignment.color_id">
                                        <SelectTrigger class="bg-white border-none shadow-sm h-10">
                                            <SelectValue placeholder="-" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="color in colors" :key="color.id" :value="String(color.id)">
                                                {{ color.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>

                        <Button @click="addPending" :disabled="!newAssignment.epp_id || !newAssignment.size || !newAssignment.color_id" class="w-full bg-slate-900 text-white font-black uppercase text-[10px] tracking-widest h-10">
                            Añadir a la Lista
                        </Button>
                    </div>

                    <!-- Pending List -->
                    <div v-if="pendingAssignments.length > 0" class="space-y-4">
                        <div class="rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 border-b">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-[10px] font-black uppercase text-slate-400">EPP</th>
                                        <th class="px-4 py-2 text-center text-[10px] font-black uppercase text-slate-400">Talla</th>
                                        <th class="px-4 py-2 text-left text-[10px] font-black uppercase text-slate-400">Color</th>
                                        <th class="px-4 py-2 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="(item, idx) in pendingAssignments" :key="idx" class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-4 py-3 font-bold text-slate-700">{{ item.epp_name }}</td>
                                        <td class="px-4 py-3 text-center font-black text-indigo-600">{{ item.size }}</td>
                                        <td class="px-4 py-3 text-slate-500">{{ colors.find(c => String(c.id) === item.color_id)?.name }}</td>
                                        <td class="px-4 py-3">
                                            <Button variant="ghost" size="icon" @click="removePending(idx)" class="h-8 w-8 text-slate-300 hover:text-rose-500">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <Button @click="submitAssignments" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black uppercase text-[11px] tracking-widest h-12 shadow-xl shadow-indigo-200">
                            Confirmar Asignación y Descontar Stock
                        </Button>
                    </div>
                </div>

                <!-- Normal View (Summary) -->
                <div v-else class="space-y-6">
                    <!-- Perfil de Tallas (Reference) -->
                    <div v-if="staff.staff_clothes && staff.staff_clothes.filter((c: StaffCloth) => !c.cloth_id && !c.epp_id).length > 0" class="space-y-3">
                        <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 px-1 border-l-2 border-slate-200 pl-3">Perfil de Referencia</h3>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div 
                                v-for="item in staff.staff_clothes.filter((c: StaffCloth) => !c.cloth_id && !c.epp_id)" 
                                :key="item.id"
                                class="flex flex-col p-3 rounded-2xl bg-slate-50 border border-slate-100 shadow-sm"
                            >
                                <span class="text-[10px] font-bold text-slate-500 uppercase truncate">{{ item.clothe_name }}</span>
                                <span class="text-sm font-black text-slate-900 uppercase mt-1">{{ item.clothing_size || '-' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- EPP Asignaciones (Actual tracking) -->
                    <div class="space-y-3">
                        <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 px-1 border-l-2 border-indigo-400 pl-3">EPPs Entregados</h3>
                        <div v-if="staff.staff_clothes && staff.staff_clothes.filter((c: StaffCloth) => c.cloth_id || c.epp_id).length > 0" class="border rounded-2xl overflow-hidden shadow-sm">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 border-b">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-[10px] font-black uppercase text-slate-400">Prenda / EPP</th>
                                        <th class="px-4 py-2 text-center text-[10px] font-black uppercase text-slate-400">Talla</th>
                                        <th class="px-4 py-2 text-left text-[10px] font-black uppercase text-slate-400">Color</th>
                                        <th class="px-4 py-2 text-left text-[10px] font-black uppercase text-slate-400">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="item in staff.staff_clothes.filter((c: StaffCloth) => c.cloth_id || c.epp_id)" :key="item.id" class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-4 py-3 font-bold text-slate-700">
                                            {{ item.epp ? item.epp.name : (item.cloth ? item.cloth.name : (item.clothe_name || 'Item Desconocido')) }}
                                        </td>
                                        <td class="px-4 py-3 text-center font-black text-slate-900 uppercase">
                                            {{ item.clothing_size || '-' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <Select :model-value="String(item.color_id || '')" @update:model-value="(val: any) => updateStatus(item.id, item.status || 'Pendiente', parseInt(val))">
                                                <SelectTrigger class="h-8 w-[140px] bg-white border-slate-200">
                                                    <SelectValue placeholder="Color" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="color in colors" :key="color.id" :value="String(color.id)">
                                                        {{ color.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </td>
                                        <td class="px-4 py-3">
                                            <Select :model-value="item.status || 'Pendiente'" @update:model-value="(val: any) => updateStatus(item.id, val, item.color_id)">
                                                <SelectTrigger :class="['h-8 w-[140px] border-none font-bold uppercase text-[10px] tracking-tighter', getStatusColor(item.status || 'Pendiente')]">
                                                    <SelectValue placeholder="Estado" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="Pendiente">Pendiente</SelectItem>
                                                    <SelectItem value="Entregado">Entregado</SelectItem>
                                                    <SelectItem value="Devuelto">Devuelto</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-10 text-slate-400 bg-slate-50/50 rounded-2xl border-2 border-dashed border-slate-200">
                            <Package class="h-8 w-8 mx-auto mb-2 opacity-20" />
                            <p class="text-[11px] font-black uppercase tracking-widest">No hay EPPs asignados todavía.</p>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="bg-slate-50/50 border-t p-4 mt-6">
                <Button variant="outline" @click="$emit('update:open', false)" class="font-bold uppercase text-[10px] tracking-widest text-slate-500">
                    Cerrar Panel
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
