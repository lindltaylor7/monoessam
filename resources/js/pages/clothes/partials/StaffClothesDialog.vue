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
import { router, usePage } from '@inertiajs/vue3';
import { Plus, Trash2, Search, Loader2, Package, Check, AlertTriangle } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import axios from 'axios';
import Swal from 'sweetalert2';

interface StaffCloth {
    id: number;
    cloth_id: number | null;
    epp_id: number | null;
    clothe_name?: string;
    clothing_size: string;
    status?: string | null;
    color_id: number | null;
    cloth?: { name: string };
    epp?: { name: string, sizes: Array<{ id: number, size: string }> };
}

const props = defineProps<{
    open: boolean;
    staff: {
        id: number;
        name: string;
        role_id?: number | null;
        cafe_id?: number | null;
        role?: { name: string };
        staffable?: { name: string };
        staff_clothes: StaffCloth[];
    } | null; 
    colors: Array<{ id: number, name: string }>;
    headquarters?: Array<{ id: number, name: string, business?: { name: string } }>;
    roleClothes?: Record<number, Record<string, Array<{ id: number; name: string }>>>;
    roleEpps?: Record<number, Record<string, Array<{ id: number; name: string; sizes: Array<{ id: number, size: string }>; pivot?: { quantity: number; color_id: number | null } }>>>;
}>();

const emit = defineEmits(['update:open']);

const isAssigning = ref(false);
const eppSearch = ref('');
const searchResults = ref<any[]>([]);
const isSearching = ref(false);
const pendingAssignments = ref<any[]>([]);
const requirementDrafts = ref<Record<number, any>>({}); // key: epp_id

const deliveryModal = ref({
    open: false,
    item: null as any,
    headquarter_id: '',
    stocks: [] as any[]
});

const page = usePage();
watch(() => (page.props as any).flash?.error, (newError) => {
    if (newError) {
        Swal.fire({
            icon: 'error',
            title: 'Error de Inventario',
            text: newError,
            confirmButtonColor: '#e11d48', // rose-600
        });
        if (page.props.flash) (page.props.flash as any).error = null;
    }
}, { immediate: true });

watch(() => (page.props as any).flash?.success, (newSuccess) => {
    if (newSuccess) {
        Swal.fire({
            icon: 'success',
            title: 'Éxito',
            text: newSuccess,
            timer: 2000,
            showConfirmButton: false
        });
        if (page.props.flash) (page.props.flash as any).success = null;
    }
}, { immediate: true });

const getDraftValue = (eppId: number, field: string, originalValue: any) => {
    if (requirementDrafts.value[eppId] && requirementDrafts.value[eppId][field] !== undefined) {
        return requirementDrafts.value[eppId][field];
    }
    return originalValue;
};

const newAssignment = ref({
    epp_id: '',
    epp_name: '',
    size: '',
    color_id: '',
    quantity: 1,
    available_sizes: [] as Array<{ id: number, size: string }>
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
    newAssignment.value.available_sizes = epp.sizes || [];
    newAssignment.value.size = ''; // Reset size when EPP changes
    eppSearch.value = '';
    searchResults.value = [];
};

const addPending = () => {
    if (!newAssignment.value.epp_id || !newAssignment.value.size || !newAssignment.value.color_id || !newAssignment.value.quantity) return;
    pendingAssignments.value.push({ ...newAssignment.value });
    newAssignment.value = {
        epp_id: '',
        epp_name: '',
        size: '',
        color_id: '',
        quantity: 1,
        available_sizes: []
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

const getStatusColorMapped = (status: string) => {
    switch (status) {
        case 'Entregado': return 'bg-green-100 text-green-700 border-green-200';
        case 'En Proceso': return 'bg-blue-100 text-blue-700 border-blue-200';
        case 'Devuelto': return 'bg-red-100 text-red-700 border-red-200';
        default: return 'bg-yellow-100 text-yellow-700 border-yellow-200';
    }
};

const staffRequirements = computed(() => {
    if (!props.staff || !props.staff.role_id || !props.roleEpps) return [];
    const roleId = props.staff.role_id;
    const cafeId = props.staff.cafe_id || (props.staff as any).staffable_id;
    const roleMap = props.roleEpps[roleId];
    if (!roleMap) return [];
    
    const specific = cafeId ? (roleMap[String(cafeId)] || []) : [];
    const common = roleMap['all'] || [];
    
    const merged = [...specific];
    common.forEach(c => {
        if (!merged.find(m => m.id === c.id)) merged.push(c);
    });
    return merged;
});

const mergedClothes = computed(() => {
    if (!props.staff) return [];
    
    const assignments = [...(props.staff.staff_clothes || [])];
    const eppRequirements = staffRequirements.value;
    
    const result: any[] = [];
    
    // Process EPP requirements first
    eppRequirements.forEach(req => {
        // Find if this EPP requirement is already satisfied
        const existing = assignments.find(a => a.epp_id === req.id);
        if (existing) {
            result.push({
                ...existing,
                required_name: req.name,
                sizes: req.sizes || [],
                is_requirement: true
            });
            // Remove from assignments so we don't double count
            const idx = assignments.findIndex(a => a.id === existing.id);
            if (idx > -1) assignments.splice(idx, 1);
        } else {
            // New entry for EPP requirement
            result.push({
                id: null,
                cloth_id: null,
                required_name: req.name,
                epp_id: req.id,
                sizes: req.sizes || [],
                clothing_size: '',
                quantity: req.pivot?.quantity || 1,
                status: 'Pendiente',
                color_id: req.pivot?.color_id || null,
                is_requirement: true
            });
        }
    });
    
    // Add remaining assignments (manual EPPs or clothes)
    assignments.forEach(a => {
        if (!a.cloth_id && !a.epp_id) return; // Skip profile items (managed in other section)
        result.push({
            ...a,
            required_name: a.epp?.name || a.cloth?.name || a.clothe_name || 'Item Extra',
            quantity: (a as any).quantity || 1,
            sizes: a.epp?.sizes || [],
            is_requirement: false
        });
    });
    
    return result;
});

const eppOptions = ref<any[]>([]);
const loadEppOptions = async () => {
    try {
        const response = await axios.get(route('inventory.items.search'), {
            params: { type: 'epp', query: '' } // Get some default or just search as needed
        });
        eppOptions.value = response.data;
    } catch (e) {
        console.error(e);
    }
};

const updateAssignment = (item: any, field: string, value: any) => {
    if (item.id) {
        // Existing assignment
        const payload: any = { 
            id: item.id, 
            status: item.status, 
            color_id: item.color_id,
            clothing_size: item.clothing_size,
            epp_id: item.epp_id,
            quantity: item.quantity
        };
        payload[field] = value;
        
        if (field === 'status' && value === 'Entregado') {
            openDeliveryModal(item);
        } else {
            updateStatus(item.id, payload.status, payload.color_id, payload.clothing_size, payload.epp_id, payload.quantity);
        }
    } else {
        // Draft for requirement
        if (!requirementDrafts.value[item.epp_id]) {
            requirementDrafts.value[item.epp_id] = {
                epp_id: item.epp_id,
                clothing_size: item.clothing_size,
                color_id: item.color_id,
                quantity: item.quantity || 1,
                status: item.status || 'Pendiente'
            };
        }
        requirementDrafts.value[item.epp_id][field] = value;
    }
};

const openDeliveryModal = async (item: any) => {
    deliveryModal.value.item = item;
    deliveryModal.value.open = true;
    deliveryModal.value.stocks = [];
    
    try {
        const itemId = item.epp_id || item.cloth_id;
        const type = item.epp_id ? 'epp' : 'cloth';
        if (itemId) {
            const response = await axios.get(route('inventory.items.stock', itemId), {
                params: { type }
            });
            deliveryModal.value.stocks = response.data;
        }
    } catch (error) {
        console.error("Error al cargar stock:", error);
    }
};

const getStockForHq = (hqId: number) => {
    const size = deliveryModal.value.item.epp_id 
        ? getDraftValue(deliveryModal.value.item.epp_id, 'clothing_size', deliveryModal.value.item.clothing_size)
        : deliveryModal.value.item.clothing_size;

    const colorId = deliveryModal.value.item.epp_id 
        ? getDraftValue(deliveryModal.value.item.epp_id, 'color_id', deliveryModal.value.item.color_id)
        : deliveryModal.value.item.color_id;

    const stock = deliveryModal.value.stocks.find(s => 
        s.headquarter_id === hqId && 
        String(s.size) === String(size) &&
        String(s.color_id) === String(colorId)
    );
    return stock ? stock.quantity : 0;
};

const saveRequirement = (eppId: number) => {
    const draft = requirementDrafts.value[eppId];
    if (!draft || !draft.clothing_size || !draft.color_id) return;
    
    // Al hacer clic en "Confirmar Entrega", asumimos que se entrega en este momento.
    // Abrimos el modal para seleccionar el origen del stock.
    openDeliveryModal({ 
        ...draft, 
        status: 'Entregado',
        required_name: props.staff?.staff_clothes.find(a => a.epp_id === eppId)?.epp?.name || 'Requerimiento' 
    });
};

const confirmAssignment = (draft: any, headquarterId: string | null = null) => {
    router.post(route('inventory.assign-clothes'), {
        staff_id: props.staff?.id,
        items: [{
            epp_id: draft.epp_id,
            size: draft.clothing_size || draft.size,
            color_id: draft.color_id,
            quantity: draft.quantity || 1,
            status: draft.status || 'Entregado',
            headquarter_id: headquarterId
        }]
    }, {
        onSuccess: () => {
            if (draft.epp_id) delete requirementDrafts.value[draft.epp_id];
            deliveryModal.value.open = false;
        },
        preserveScroll: true,
        preserveState: true
    });
};

const updateStatus = (clothEntryId: number, status: string, colorId?: number | null, size?: string, eppId?: number | null, quantity?: number, headquarterId?: string | null) => {
    router.post(route('clothes.status'), {
        id: clothEntryId,
        status: status,
        color_id: colorId,
        clothing_size: size,
        epp_id: eppId,
        quantity: quantity,
        headquarter_id: headquarterId
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['staff'] 
    });
}


watch(() => props.open, (val) => {
    if (val) {
        loadEppOptions();
    }
    if (!val) {
        isAssigning.value = false;
        pendingAssignments.value = [];
        searchResults.value = [];
        eppSearch.value = '';
        requirementDrafts.value = {};
    }
});
</script>

<template>
    <div class="contents">
        <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-[750px] max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <div class="flex justify-between items-start">
                    <div>
                        <DialogTitle class="text-xl font-black">Control de EPPs</DialogTitle>
                        <DialogDescription>
                            Gestión de tallas y asignación de EPP para {{ staff?.name }}
                            <span v-if="staff?.role || staff?.staffable" class="block text-[10px] mt-1 text-indigo-500 font-bold uppercase tracking-widest">
                                {{ staff?.role?.name || 'Sin cargo' }} • {{ staff?.staffable?.name || 'Sin café' }}
                            </span>
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
                                    <Select v-model="newAssignment.size">
                                        <SelectTrigger class="bg-white border-none shadow-sm h-10">
                                            <SelectValue :placeholder="newAssignment.available_sizes.length ? 'Seleccionar...' : '-'" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="size in newAssignment.available_sizes" :key="size.id" :value="size.size">
                                                {{ size.size }}
                                            </SelectItem>
                                            <div v-if="newAssignment.available_sizes.length === 0" class="p-2 text-xs text-slate-400 italic">No hay tallas</div>
                                        </SelectContent>
                                    </Select>
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

                        <Button @click="addPending" :disabled="!newAssignment.epp_id || !newAssignment.size || !newAssignment.color_id || !newAssignment.quantity" class="w-full bg-slate-900 text-white font-black uppercase text-[10px] tracking-widest h-10">
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
                        <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 px-1 border-l-2 border-indigo-400 pl-3">EPPs y Requerimientos</h3>
                        <div v-if="mergedClothes.length > 0" class="border rounded-2xl overflow-hidden shadow-sm">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 border-b">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-[10px] font-black uppercase text-slate-400">Requerimiento / Item</th>
                                        <th class="px-4 py-2 text-center text-[10px] font-black uppercase text-slate-400">Cant</th>
                                        <th class="px-4 py-2 text-center text-[10px] font-black uppercase text-slate-400">Talla</th>
                                        <th class="px-4 py-2 text-left text-[10px] font-black uppercase text-slate-400">Color</th>
                                        <th class="px-4 py-2 text-left text-[10px] font-black uppercase text-slate-400">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="item in mergedClothes" :key="item.id || item.cloth_id" class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-4 py-3">
                                            <div class="flex flex-col gap-1">
                                                <div class="font-bold text-slate-900 text-xs">
                                                    {{ item.required_name }}
                                                </div>
                                                <div v-if="!item.id" class="flex flex-col gap-2">
                                                    <div class="text-[10px] font-black text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full inline-block w-fit">
                                                        REQUERIDO
                                                    </div>
                                                    <Button 
                                                        v-if="getDraftValue(item.epp_id, 'clothing_size', '') && getDraftValue(item.epp_id, 'color_id', '')"
                                                        @click="saveRequirement(item.epp_id)"
                                                        size="sm"
                                                        class="h-7 text-[9px] bg-green-600 hover:bg-green-700 font-bold uppercase"
                                                    >
                                                        Confirmar Entrega
                                                    </Button>
                                                </div>
                                                <div v-else-if="!item.is_requirement" class="text-[8px] font-black text-slate-400 uppercase tracking-widest">
                                                    Asignación Extra
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <Input 
                                                type="number"
                                                :model-value="getDraftValue(item.epp_id, 'quantity', item.quantity)" 
                                                @change="(e: Event) => updateAssignment(item, 'quantity', parseInt((e.target as HTMLInputElement).value))"
                                                class="h-8 w-12 text-center font-black text-slate-900 border-none bg-slate-50" 
                                            />
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <Select 
                                                :model-value="getDraftValue(item.epp_id, 'clothing_size', item.clothing_size)" 
                                                @update:model-value="(val: any) => updateAssignment(item, 'clothing_size', val)"
                                            >
                                                <SelectTrigger class="h-8 w-16 text-center font-black text-slate-900 border-none bg-slate-50 p-0 pr-1 flex justify-center">
                                                    <SelectValue placeholder="-" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="size in item.sizes" :key="size.id" :value="size.size">
                                                        {{ size.size }}
                                                    </SelectItem>
                                                    <div v-if="!item.sizes || item.sizes.length === 0" class="p-2 text-[10px] text-slate-400 italic">No hay tallas</div>
                                                </SelectContent>
                                            </Select>
                                        </td>
                                        <td class="px-4 py-3">
                                            <Select :model-value="String(getDraftValue(item.epp_id, 'color_id', item.color_id || ''))" @update:model-value="(val: any) => updateAssignment(item, 'color_id', parseInt(val))">
                                                <SelectTrigger class="h-8 w-[120px] bg-white border-slate-200 text-xs">
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
                                            <Select :model-value="getDraftValue(item.epp_id, 'status', item.status || 'Pendiente')" @update:model-value="(val: any) => updateAssignment(item, 'status', val)">
                                                <SelectTrigger :class="['h-8 w-[120px] border-none font-bold uppercase text-[10px] tracking-tighter', getStatusColorMapped(getDraftValue(item.epp_id, 'status', item.status || 'Pendiente'))]">
                                                    <SelectValue placeholder="Estado" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="Pendiente">Pendiente</SelectItem>
                                                    <SelectItem value="En Proceso">En Proceso</SelectItem>
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

        <!-- Delivery Location Modal -->
        <Dialog :open="deliveryModal.open" @update:open="deliveryModal.open = $event">
            <DialogContent class="sm:max-w-[400px]">
                <DialogHeader>
                    <DialogTitle class="text-lg font-black uppercase tracking-tight">Confirmar Entrega de Stock</DialogTitle>
                    <DialogDescription class="text-xs">
                        Selecciona desde qué almacén/ciudad se está retirando el EPP: 
                        <span class="font-bold text-slate-900">{{ deliveryModal.item?.required_name || deliveryModal.item?.epp_name }}</span>
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4 space-y-4">
                    <div class="space-y-2">
                        <Label class="text-[10px] font-black uppercase text-slate-500">Almacén de Origen (Sede)</Label>
                        <Select v-model="deliveryModal.headquarter_id">
                            <SelectTrigger class="w-full bg-slate-50 border-none h-11">
                                <SelectValue placeholder="Seleccionar sede..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="hq in headquarters" :key="hq.id" :value="String(hq.id)">
                                    <div class="flex justify-between w-64 items-center">
                                        <span>{{ hq.name }} <span class="text-[9px] opacity-50 ml-1">({{ hq.business?.name }})</span></span>
                                        <span :class="['text-[10px] font-black px-2 py-0.5 rounded-full', getStockForHq(hq.id) > 0 ? 'bg-green-100 text-green-700' : 'bg-rose-100 text-rose-700']">
                                            Stock: {{ getStockForHq(hq.id) }}
                                        </span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="p-3 bg-indigo-50 rounded-xl border border-indigo-100">
                        <div class="flex justify-between items-center text-[10px] font-bold">
                            <span class="text-indigo-600">Cant. a Descontar:</span>
                            <span class="bg-indigo-600 text-white px-2 py-0.5 rounded-full">{{ deliveryModal.item?.quantity }}</span>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="deliveryModal.open = false" class="text-[10px] font-bold uppercase">Cancelar</Button>
                    <Button 
                        @click="deliveryModal.item?.id 
                            ? updateStatus(deliveryModal.item.id, 'Entregado', deliveryModal.item.color_id, deliveryModal.item.clothing_size, deliveryModal.item.epp_id, deliveryModal.item.quantity, deliveryModal.headquarter_id)
                            : confirmAssignment(deliveryModal.item, deliveryModal.headquarter_id)"
                        :disabled="!deliveryModal.headquarter_id"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-black uppercase"
                    >
                        Confirmar y Descontar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
