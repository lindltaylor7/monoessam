<script setup lang="ts">
import { ref, computed, watch, reactive } from 'vue';
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
import { Plus, Trash2, Search, Loader2, Package, Check, AlertTriangle, Camera } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Checkbox } from '@/components/ui/checkbox';
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
    quantity: number;
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
        clothes_histories?: Array<{
            id: number;
            user_id: number;
            reason: string;
            assigned_at: string;
            user?: { name: string };
            items: any;
            created_at: string;
            evidence_image?: string;
        }>;
    } | null; 
    colors: Array<{ id: number, name: string }>;
    headquarters?: Array<{ id: number, name: string, business?: { name: string } }>;
    roleClothes?: Record<number, Record<string, Array<{ id: number; name: string }>>>;
    roleEpps?: Record<number, Record<string, Array<{ id: number; name: string; sizes: Array<{ id: number, size: string }>; pivot?: { quantity: number; color_id: number | null } }>>>;
}>();

const emit = defineEmits(['update:open']);

const isAssigning = ref(false);
const assignReason = ref('Renovación'); // Default to Regular
const selectedKeys = ref<string[]>([]);
const selectionMode = ref(false);

const toggleSelection = (item: any) => {
    if (!item.epp_id) return;
    const key = getRowKey(item);
    const idx = selectedKeys.value.indexOf(key);
    if (idx > -1) {
        selectedKeys.value.splice(idx, 1);
    } else {
        selectedKeys.value.push(key);
    }
};

const getRowKey = (item: any) => {
    return `${item.id || 'null'}-${item.epp_id || 'no-epp'}`;
};

const isSelected = (item: any) => {
    return selectedKeys.value.includes(getRowKey(item));
};

const selectedKeysCount = computed(() => {
    return selectedKeys.value.length;
});

const getSelectedItems = () => {
    return mergedClothes.value.filter(i => selectedKeys.value.includes(getRowKey(i)));
};

const selectAll = (checked: boolean) => {
    if (checked) {
        selectedKeys.value = mergedClothes.value
            .filter((i: any) => i.epp_id)
            .map(i => getRowKey(i));
    } else {
        selectedKeys.value = [];
    }
};

const multiDeliveryModal = ref({
    open: false,
    items: [] as any[],
    headquarters: {} as Record<string, string>,
    stocks: {} as Record<string, any[]>,
    isLoading: false
});

const openMultiDeliveryModal = async () => {
    const selectedItems = getSelectedItems();
    const errorItems = selectedItems.filter(i => !getDraftValue(i.epp_id, 'clothing_size', i.clothing_size) || !getDraftValue(i.epp_id, 'color_id', i.color_id));
    
    if (errorItems.length > 0) {
        Swal.fire({
            icon: 'error',
            title: 'Faltan Datos',
            text: 'Asegúrate de seleccionar Talla y Color para todos los items seleccionados.',
            confirmButtonColor: '#4f46e5'
        });
        return;
    }
    
    multiDeliveryModal.value.items = selectedItems;
    multiDeliveryModal.value.headquarters = {};
    multiDeliveryModal.value.stocks = {};
    multiDeliveryModal.value.open = true;
    multiDeliveryModal.value.isLoading = true;
    
    try {
        const promises = selectedItems.map(async (item) => {
            const itemId = item.epp_id || item.cloth_id;
            const type = item.epp_id ? 'epp' : 'cloth';
            if (itemId) {
                const response = await axios.get(route('inventory.items.stock', itemId), { params: { type } });
                multiDeliveryModal.value.stocks[getRowKey(item)] = response.data;
            }
        });
        await Promise.all(promises);
    } catch (e) {
        console.error("Error cargando stocks múltiples", e);
    } finally {
        multiDeliveryModal.value.isLoading = false;
    }
};

const confirmMultiAssignment = () => {
    const selectedItems = multiDeliveryModal.value.items;
    
    for (const item of selectedItems) {
        const key = getRowKey(item);
        const hqId = multiDeliveryModal.value.headquarters[key];
        if (!hqId) {
            Swal.fire('Atención', 'Selecciona el almacén de origen para todos los items.', 'warning');
            return;
        }
        
        const qty = getDraftValue(item.epp_id, 'quantity', item.quantity) || 1;
        const stock = getMultiStockForHq(item, Number(hqId));
        if (stock < qty) {
            Swal.fire({
                icon: 'error',
                title: 'Stock Insuficiente',
                text: `No hay stock suficiente para ${item.required_name || item.epp_name} en la sede seleccionada.`,
                confirmButtonColor: '#e11d48'
            });
            return;
        }
    }

    const itemsPayload = selectedItems.map(draft => {
        const key = getRowKey(draft);
        return {
            id: draft.id || null, 
            epp_id: draft.epp_id,
            epp_name: draft.required_name || draft.epp_name || (props.staff?.staff_clothes.find(a => a.epp_id === draft.epp_id)?.epp?.name),
            size: getDraftValue(draft.epp_id, 'clothing_size', draft.clothing_size) || draft.size,
            color_id: getDraftValue(draft.epp_id, 'color_id', draft.color_id),
            quantity: getDraftValue(draft.epp_id, 'quantity', draft.quantity) || 1,
            status: getDraftValue(draft.epp_id, 'status', draft.status) || 'Entregado',
            headquarter_id: multiDeliveryModal.value.headquarters[key]
        };
    });

    router.post(route('inventory.assign-clothes'), {
        staff_id: props.staff?.id,
        reason: assignReason.value,
        create_history: true,
        items: itemsPayload
    }, {
        onSuccess: () => {
            selectedItems.forEach(draft => {
                if (draft.epp_id) delete requirementDrafts.value[draft.epp_id];
            });
            selectedKeys.value = [];
            multiDeliveryModal.value.open = false;
        },
        preserveScroll: true,
        preserveState: true,
        only: ['staff', 'flash']
    });
};

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
        reason: assignReason.value,
        create_history: true,
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
    
    const assignmentsDraft = [...(props.staff.staff_clothes || [])];
    const eppRequirements = staffRequirements.value;
    
    // Stage 1: Aggregate identical assignments (optional but highly recommended for cleanliness)
    const aggregatedAssignments: any[] = [];
    assignmentsDraft.forEach(a => {
        const existing = aggregatedAssignments.find(x => 
            x.epp_id === a.epp_id && 
            x.cloth_id === a.cloth_id && 
            x.color_id == a.color_id && 
            x.clothing_size === a.clothing_size && 
            x.status === a.status
        );
        if (existing) {
            existing.quantity = (existing.quantity || 1) + (a.quantity || 1);
            // Keep track of IDs for updating (we might need a list of IDs if we want to update all, but for now we take the first)
            if (!existing.original_ids) existing.original_ids = [existing.id];
            existing.original_ids.push(a.id);
        } else {
            aggregatedAssignments.push({ ...a, quantity: a.quantity || 1, original_ids: [a.id] });
        }
    });

    const result: any[] = [];
    
    // Process EPP requirements first
    eppRequirements.forEach(req => {
        // Find ALL assignments for this EPP ID
        const matches = aggregatedAssignments.filter(a => a.epp_id === req.id);
        
        if (matches.length > 0) {
            // Use the first match as the primary row (keeps its id, color, size for editing)
            const primary = matches[0];
            // Sum the total quantity across ALL matches for this EPP
            const totalQuantity = matches.reduce((sum, m) => sum + (m.quantity || 1), 0);
            
            result.push({
                ...primary,
                quantity: totalQuantity,
                required_name: req.name,
                sizes: req.sizes || [],
                is_requirement: true,
                expected_quantity: req.pivot?.quantity || 1
            });
            
            // Remove ALL matches from local list so they don't appear as extras
            matches.forEach(match => {
                const idx = aggregatedAssignments.findIndex(a => a.id === match.id);
                if (idx > -1) aggregatedAssignments.splice(idx, 1);
            });
        } else {
            // New entry for EPP requirement (no assignments yet)
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
    aggregatedAssignments.forEach(a => {
        if (!a.cloth_id && !a.epp_id) return; // Skip profile items
        result.push({
            ...a,
            required_name: a.epp?.name || a.cloth?.name || a.clothe_name || 'Item Extra',
            quantity: a.quantity || 1,
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
        console.log(eppOptions.value);
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

const getMultiStockForHq = (item: any, hqId: number) => {
    const key = getRowKey(item);
    const stocks = multiDeliveryModal.value.stocks[key] || [];
    
    const size = item.epp_id 
        ? getDraftValue(item.epp_id, 'clothing_size', item.clothing_size)
        : item.clothing_size;

    const colorId = item.epp_id 
        ? getDraftValue(item.epp_id, 'color_id', item.color_id)
        : item.color_id;

    const stock = stocks.find((s: any) => 
        s.headquarter_id === hqId && 
        String(s.size) === String(size) &&
        String(s.color_id) === String(colorId)
    );
    return stock ? stock.quantity : 0;
};

const updateStatus = (clothEntryId: number, status: string, colorId?: number | null, size?: string, eppId?: number | null, quantity?: number, headquarterId?: string | null) => {
    // Stock validation for delivery
    if (status === 'Entregado' && headquarterId && quantity !== undefined) {
        const stock = getStockForHq(Number(headquarterId));
        if (stock < quantity) {
            Swal.fire({
                icon: 'error',
                title: 'Stock Insuficiente',
                text: `No hay suficiente stock en esta sede (${stock} disponibles).`,
                confirmButtonColor: '#e11d48'
            });
            return;
        }
    }

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
        only: ['staff', 'flash'] 
    });
}

const confirmAssignment = (draft: any, headquarterId: string | null = null) => {
    // Stock validation
    if (headquarterId && draft.quantity !== undefined) {
        const stock = getStockForHq(Number(headquarterId));
        if (stock < draft.quantity) {
            Swal.fire({
                icon: 'error',
                title: 'Stock Insuficiente',
                text: `No hay suficiente stock en esta sede (${stock} disponibles).`,
                confirmButtonColor: '#e11d48'
            });
            return;
        }
    }

    router.post(route('inventory.assign-clothes'), {
        staff_id: props.staff?.id,
        reason: assignReason.value,
        create_history: true,
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
        preserveState: true,
        only: ['staff', 'flash']
    });
};


const evidenceFile = ref<File | null>(null);
const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        evidenceFile.value = target.files[0];
    } else {
        evidenceFile.value = null;
    }
};

const uploadEvidence = (histId: number, file: File) => {
    router.post(route('inventory.history.evidence', histId), {
        evidence_image: file
    }, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: 'Evidencia subida correctamente',
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
};

watch(() => props.open, (val) => {
    if (val) {
        loadEppOptions();
    }
    if (!val) {
        isAssigning.value = false;
        pendingAssignments.value = [];
        searchResults.value = [];
        requirementDrafts.value = {};
        selectedKeys.value = [];
        multiDeliveryModal.value.open = false;
        evidenceFile.value = null;
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
                                {{ staff?.role?.name || 'Sin cargo' }} • {{ staff?.staffable?.name || 'Sin comedor' }}
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
                        
                        <div class="bg-indigo-50 p-4 rounded-2xl flex flex-col sm:flex-row gap-4 items-center justify-between border border-indigo-100">
                            <div class="flex items-center gap-3 w-full sm:w-auto">
                                <Label class="text-[10px] font-black uppercase text-indigo-700 whitespace-nowrap">Motivo:</Label>
                                <Select v-model="assignReason">
                                    <SelectTrigger class="bg-white border-none shadow-sm h-10 w-full sm:w-40 text-xs">
                                        <SelectValue placeholder="Motivo..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Nuevo">Nuevo Ingreso</SelectItem>
                                        <SelectItem value="Renovación">Renovación regular</SelectItem>
                                        <SelectItem value="Reposición">Reposición</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button @click="submitAssignments" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-black uppercase text-[11px] tracking-widest h-10 shadow-lg shadow-indigo-200">
                                Confirmar Asignación y Descontar
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Normal View (Summary) -->
                <div v-else class="space-y-6">
                    <Tabs defaultValue="assignments" class="w-full">
                        <TabsList class="w-full grid grid-cols-2 mb-4 bg-slate-100 h-12 rounded-xl p-1">
                            <TabsTrigger value="assignments" class="text-xs font-bold uppercase tracking-widest rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm">Estado Actual</TabsTrigger>
                            <TabsTrigger value="history" class="text-xs font-bold uppercase tracking-widest rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm">Historial de Entregas</TabsTrigger>
                        </TabsList>
                        
                        <TabsContent value="assignments" class="space-y-6 animate-in fade-in zoom-in-95 duration-200">
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
                                        <th class="px-4 py-2 w-10">
                                            <Checkbox 
                                                :checked="selectedKeysCount === mergedClothes.filter((i: any) => i.epp_id).length && selectedKeysCount > 0" 
                                                @update:checked="selectAll" 
                                            />
                                        </th>
                                        <th class="px-4 py-2 text-left text-[10px] font-black uppercase text-slate-400">Requerimiento / Item</th>
                                        <th class="px-4 py-2 text-center text-[10px] font-black uppercase text-slate-400">Cant</th>
                                        <th class="px-4 py-2 text-center text-[10px] font-black uppercase text-slate-400">Talla</th>
                                        <th class="px-4 py-2 text-left text-[10px] font-black uppercase text-slate-400">Color</th>
                                        <th class="px-4 py-2 text-left text-[10px] font-black uppercase text-slate-400">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr 
                                        v-for="(item, idx) in mergedClothes" 
                                        :key="(item.id ? 'id-'+item.id : 'idx-'+idx) + (item.epp_id ? '-epp-'+item.epp_id : '')" 
                                        class="transition-all duration-200 cursor-pointer"
                                        :class="[
                                            isSelected(item) ? 'bg-indigo-50/80 border-l-4 border-l-indigo-500' : 'hover:bg-slate-50/50',
                                            selectionMode && item.epp_id ? 'ring-1 ring-indigo-100' : ''
                                        ]"
                                        @click="selectionMode ? toggleSelection(item) : null"
                                    >
                                        <td class="px-4 py-3">
                                            <div 
                                                v-if="item.epp_id"
                                                class="h-5 w-5 rounded-md border-2 flex items-center justify-center transition-all"
                                                :class="isSelected(item) ? 'bg-indigo-600 border-indigo-600 text-white shadow-sm' : 'border-slate-300 bg-white'"
                                                @click.stop="toggleSelection(item)"
                                            >
                                                <Check v-if="isSelected(item)" class="h-3.5 w-3.5 stroke-[3]" />
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-col gap-1">
                                                <div class="font-bold text-slate-900 text-xs">
                                                    {{ item.required_name }}
                                                </div>
                                                <div v-if="!item.id" class="flex flex-col gap-2">
                                                    <div class="text-[10px] font-black text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full inline-block w-fit">
                                                        REQUERIDO
                                                    </div>
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

                        <!-- Action Bar -->
                        <div class="sticky bottom-0 bg-slate-50/90 backdrop-blur-md p-4 rounded-b-2xl flex flex-col sm:flex-row gap-4 items-center justify-between border-t border-slate-200 shadow-xl z-20">
                            <div class="flex items-center gap-4">
                                <Button 
                                    @click="selectionMode = !selectionMode" 
                                    size="sm"
                                    variant="outline"
                                    :class="selectionMode ? 'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700' : 'bg-white border-slate-200'"
                                    class="h-10 text-[10px] font-black uppercase tracking-widest px-4 shadow-sm transition-all"
                                >
                                    {{ selectionMode ? 'Finalizar Selección' : 'Modo Selección' }}
                                </Button>
                                
                                <div class="flex flex-col">
                                    <span class="text-[11px] font-black uppercase" :class="selectedKeysCount > 0 ? 'text-indigo-700' : 'text-slate-400'">
                                        {{ selectedKeysCount }} items seleccionados
                                    </span>
                                    <span v-if="selectionMode" class="text-[9px] font-bold text-indigo-400 uppercase leading-none">Haz click en las filas</span>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3 w-full sm:w-auto">
                                <Select v-model="assignReason" v-if="selectedKeysCount > 0">
                                    <SelectTrigger class="bg-white border-slate-200 shadow-sm h-10 w-full sm:w-48 text-xs font-bold">
                                        <SelectValue placeholder="Motivo..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Nuevo">Nuevo Ingreso</SelectItem>
                                        <SelectItem value="Renovación">Renovación regular</SelectItem>
                                        <SelectItem value="Reposición">Reposición</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button 
                                    @click="openMultiDeliveryModal" 
                                    :disabled="selectedKeysCount === 0"
                                    class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-black uppercase text-[10px] tracking-widest h-10 px-6 shadow-lg shadow-indigo-200 disabled:opacity-50 disabled:shadow-none transition-all active:scale-95"
                                >
                                    Registrar Entrega Múltiple
                                </Button>
                            </div>
                        </div>
                    </div>
                </TabsContent>

                        <TabsContent value="history" class="animate-in fade-in zoom-in-95 duration-200">
                            <div v-if="staff.clothes_histories && staff.clothes_histories.length > 0" class="space-y-4">
                                <div v-for="hist in staff.clothes_histories.slice().reverse()" :key="hist.id" class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm flex flex-col gap-3">
                                    <div class="flex justify-between items-start border-b border-slate-50 pb-3">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[10px] font-black uppercase text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md">{{ hist.reason }}</span>
                                                <span class="text-xs font-medium text-slate-500">{{ new Date(hist.created_at).toLocaleString() }}</span>
                                            </div>
                                            <div class="text-[10px] font-medium text-slate-400">Asignado por: <span class="font-bold text-slate-700">{{ hist.user?.name || 'Sistema' }}</span></div>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-1">
                                        <div v-for="(item, idx) in hist.items" :key="idx" class="flex flex-col p-2 bg-slate-50 rounded-xl">
                                            <span class="text-[11px] font-bold text-slate-800">{{ eppOptions.find(e => String(e.id) === String(item.epp_id))?.name || item.epp_name || `EPP #${item.epp_id}` }}</span>
                                            <div class="flex gap-3 text-[10px] text-slate-500 mt-1">
                                                <span class="font-medium">Cant: <strong class="text-indigo-600">{{ item.quantity }}</strong></span>
                                                <span class="font-medium">Talla: <strong class="text-slate-700">{{ item.size || '-' }}</strong></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="hist.evidence_image" class="mt-2">
                                        <div class="text-[10px] font-bold uppercase text-slate-400 mb-2">Evidencia Fotográfica:</div>
                                        <a :href="hist.evidence_image" target="_blank" class="block w-fit rounded-xl overflow-hidden border border-slate-200">
                                            <img :src="hist.evidence_image" class="h-20 w-auto object-cover hover:scale-105 transition-transform" />
                                        </a>
                                    </div>
                                    <div v-else class="mt-2 pt-2 border-t border-slate-50">
                                        <Label class="text-[9px] font-black uppercase text-indigo-400 mb-1 flex items-center gap-1">
                                            <Camera class="h-3 w-3" /> Subir Evidencia
                                        </Label>
                                        <Input 
                                            type="file" 
                                            accept="image/*" 
                                            class="h-8 text-[10px] border-dashed border-slate-200 bg-slate-50/50" 
                                            @change="(e: any) => e.target.files?.[0] && uploadEvidence(hist.id, e.target.files[0])"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-10 text-slate-400 bg-slate-50/50 rounded-2xl border-2 border-dashed border-slate-200">
                                <Package class="h-8 w-8 mx-auto mb-2 opacity-20" />
                                <p class="text-[11px] font-black uppercase tracking-widest">No hay historial de asignaciones.</p>
                            </div>
                        </TabsContent>
                    </Tabs>
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
        <!-- Multi Delivery Location Modal -->
        <Dialog :open="multiDeliveryModal.open" @update:open="multiDeliveryModal.open = $event">
            <DialogContent class="sm:max-w-[600px] max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="text-lg font-black uppercase tracking-tight">Seleccionar Origen del Stock</DialogTitle>
                    <DialogDescription class="text-xs">
                        Confirma desde qué almacén se descontará cada uno de los <span class="font-bold text-slate-900">{{ multiDeliveryModal.items.length }}</span> items seleccionados.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4 space-y-4">
                    <div v-if="multiDeliveryModal.isLoading" class="flex justify-center py-6">
                        <Loader2 class="h-6 w-6 animate-spin text-indigo-500" />
                    </div>
                    <div v-else class="space-y-4 flex flex-col gap-2">
                        <div v-for="item in multiDeliveryModal.items" :key="getRowKey(item)" class="p-4 bg-slate-50 border border-slate-100 rounded-xl flex flex-col gap-3">
                            <div class="flex justify-between items-start border-b border-slate-200 pb-2">
                                <div class="font-bold text-slate-800 text-sm truncate max-w-[200px]">{{ item.required_name || item.epp_name }}</div>
                                <div class="flex gap-2">
                                    <span class="text-[10px] font-black uppercase text-indigo-700 bg-indigo-100 px-2 py-0.5 rounded-full flex gap-1 items-center">
                                        {{ getDraftValue(item.epp_id, 'clothing_size', item.clothing_size) }}
                                    </span>
                                    <span class="text-[10px] font-black bg-slate-200 px-2 py-0.5 rounded-full flex gap-1 items-center text-slate-600">
                                        Cant: {{ getDraftValue(item.epp_id, 'quantity', item.quantity) || 1 }}
                                    </span>
                                </div>
                            </div>
                            
                            <Select v-model="multiDeliveryModal.headquarters[getRowKey(item)]">
                                <SelectTrigger class="w-full bg-white border border-slate-200 shadow-sm h-11 text-xs">
                                    <SelectValue placeholder="Seleccionar sede de origen..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="hq in headquarters" :key="hq.id" :value="String(hq.id)">
                                        <div class="flex justify-between w-64 items-center">
                                            <span>{{ hq.name }} <span class="text-[9px] opacity-50 ml-1">({{ hq.business?.name }})</span></span>
                                            <span :class="['text-[10px] font-black px-2 py-0.5 rounded-full', getMultiStockForHq(item, hq.id) >= (getDraftValue(item.epp_id, 'quantity', item.quantity) || 1) ? 'bg-green-100 text-green-700' : 'bg-rose-100 text-rose-700']">
                                                Stock: {{ getMultiStockForHq(item, hq.id) }}
                                            </span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="multiDeliveryModal.open = false" class="text-[10px] font-bold uppercase">Cancelar</Button>
                    <Button 
                        @click="confirmMultiAssignment()"
                        :disabled="multiDeliveryModal.isLoading || Object.keys(multiDeliveryModal.headquarters).length !== multiDeliveryModal.items.length"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-black uppercase"
                    >
                        Confirmar y Procesar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
