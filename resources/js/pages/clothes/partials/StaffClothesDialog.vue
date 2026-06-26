<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Camera, Check, FileText, Loader2, Package, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';

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
    epp?: { name: string; sizes: Array<{ id: number; size: string }> };
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
    colors: Array<{ id: number; name: string }>;
    headquarters?: Array<{ id: number; name: string; business?: { name: string } }>;
    roleClothes?: Record<number, Record<string, Array<{ id: number; name: string }>>>;
    roleEpps?: Record<
        number,
        Record<
            string,
            Array<{ id: number; name: string; sizes: Array<{ id: number; size: string }>; pivot?: { quantity: number; color_id: number | null } }>
        >
    >;
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
    return mergedClothes.value.filter((i) => selectedKeys.value.includes(getRowKey(i)));
};

const selectAll = (checked: boolean) => {
    if (checked) {
        selectedKeys.value = mergedClothes.value.filter((i: any) => i.epp_id).map((i) => getRowKey(i));
    } else {
        selectedKeys.value = [];
    }
};

const multiDeliveryModal = ref({
    open: false,
    items: [] as any[],
    headquarters: {} as Record<string, string>,
    stocks: {} as Record<string, any[]>,
    isLoading: false,
});

const openMultiDeliveryModal = async () => {
    const selectedItems = getSelectedItems();
    const errorItems = selectedItems.filter(
        (i) => !getDraftValue(i.epp_id, 'clothing_size', i.clothing_size) || !getDraftValue(i.epp_id, 'color_id', i.color_id),
    );

    if (errorItems.length > 0) {
        Swal.fire({
            icon: 'error',
            title: 'Faltan Datos',
            text: 'Asegúrate de seleccionar Talla y Color para todos los items seleccionados.',
            confirmButtonColor: '#4f46e5',
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
        console.error('Error cargando stocks múltiples', e);
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
                confirmButtonColor: '#e11d48',
            });
            return;
        }
    }

    const itemsPayload = selectedItems.map((draft) => {
        const key = getRowKey(draft);
        return {
            id: draft.id || null,
            epp_id: draft.epp_id,
            epp_name: draft.required_name || draft.epp_name || props.staff?.staff_clothes.find((a) => a.epp_id === draft.epp_id)?.epp?.name,
            size: getDraftValue(draft.epp_id, 'clothing_size', draft.clothing_size) || draft.size,
            color_id: getDraftValue(draft.epp_id, 'color_id', draft.color_id),
            quantity: getDraftValue(draft.epp_id, 'quantity', draft.quantity) || 1,
            status: getDraftValue(draft.epp_id, 'status', draft.status) || 'Entregado',
            headquarter_id: multiDeliveryModal.value.headquarters[key],
        };
    });

    router.post(
        route('inventory.assign-clothes'),
        {
            staff_id: props.staff?.id,
            reason: assignReason.value,
            create_history: true,
            items: itemsPayload,
        },
        {
            onSuccess: () => {
                selectedItems.forEach((draft) => {
                    if (draft.epp_id) delete requirementDrafts.value[draft.epp_id];
                });
                selectedKeys.value = [];
                multiDeliveryModal.value.open = false;
            },
            preserveScroll: true,
            preserveState: true,
            only: ['staff', 'flash'],
        },
    );
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
    stocks: [] as any[],
});

const page = usePage();
watch(
    () => (page.props as any).flash?.error,
    (newError) => {
        if (newError) {
            Swal.fire({
                icon: 'error',
                title: 'Error de Inventario',
                text: newError,
                confirmButtonColor: '#e11d48', // rose-600
            });
            if (page.props.flash) (page.props.flash as any).error = null;
        }
    },
    { immediate: true },
);

watch(
    () => (page.props as any).flash?.success,
    (newSuccess) => {
        if (newSuccess) {
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: newSuccess,
                timer: 2000,
                showConfirmButton: false,
            });
            if (page.props.flash) (page.props.flash as any).success = null;
        }
    },
    { immediate: true },
);

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
    available_sizes: [] as Array<{ id: number; size: string }>,
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
            params: { type: 'epp', query },
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
        available_sizes: [],
    };
};

const removePending = (index: number) => {
    pendingAssignments.value.splice(index, 1);
};

const submitAssignments = () => {
    if (!props.staff) return;
    router.post(
        route('inventory.assign-clothes'),
        {
            staff_id: props.staff.id,
            reason: assignReason.value,
            create_history: true,
            items: pendingAssignments.value,
        },
        {
            onSuccess: () => {
                isAssigning.value = false;
                pendingAssignments.value = [];
            },
            preserveScroll: true,
        },
    );
};

const getStatusColorMapped = (status: string) => {
    switch (status) {
        case 'Entregado':
            return 'bg-green-100 text-green-700 border-green-200';
        case 'En Proceso':
            return 'bg-blue-100 text-blue-700 border-blue-200';
        case 'Devuelto':
            return 'bg-red-100 text-red-700 border-red-200';
        default:
            return 'bg-yellow-100 text-yellow-700 border-yellow-200';
    }
};

const staffRequirements = computed(() => {
    if (!props.staff || !props.staff.role_id || !props.roleEpps) return [];
    const roleId = props.staff.role_id;
    const cafeId = props.staff.cafe_id || (props.staff as any).staffable_id;
    const roleMap = props.roleEpps[roleId];
    if (!roleMap) return [];

    const specific = cafeId ? roleMap[String(cafeId)] || [] : [];
    const common = roleMap['all'] || [];

    const merged = [...specific];
    common.forEach((c) => {
        if (!merged.find((m) => m.id === c.id)) merged.push(c);
    });
    return merged;
});

const mergedClothes = computed(() => {
    if (!props.staff) return [];

    const assignmentsDraft = [...(props.staff.staff_clothes || [])];
    const eppRequirements = staffRequirements.value;

    // Stage 1: Aggregate identical assignments (optional but highly recommended for cleanliness)
    const aggregatedAssignments: any[] = [];
    assignmentsDraft.forEach((a) => {
        const existing = aggregatedAssignments.find(
            (x) =>
                x.epp_id === a.epp_id &&
                x.cloth_id === a.cloth_id &&
                x.color_id == a.color_id &&
                x.clothing_size === a.clothing_size &&
                x.status === a.status,
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
    eppRequirements.forEach((req) => {
        // Find ALL assignments for this EPP ID
        const matches = aggregatedAssignments.filter((a) => a.epp_id === req.id);

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
                expected_quantity: req.pivot?.quantity || 1,
            });

            // Remove ALL matches from local list so they don't appear as extras
            matches.forEach((match) => {
                const idx = aggregatedAssignments.findIndex((a) => a.id === match.id);
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
                is_requirement: true,
            });
        }
    });

    // Add remaining assignments (manual EPPs or clothes)
    aggregatedAssignments.forEach((a) => {
        if (!a.cloth_id && !a.epp_id) return; // Skip profile items
        result.push({
            ...a,
            required_name: a.epp?.name || a.cloth?.name || a.clothe_name || 'Item Extra',
            quantity: a.quantity || 1,
            sizes: a.epp?.sizes || [],
            is_requirement: false,
        });
    });

    return result;
});

const eppOptions = ref<any[]>([]);
const loadEppOptions = async () => {
    try {
        const response = await axios.get(route('inventory.items.search'), {
            params: { type: 'epp', query: '' }, // Get some default or just search as needed
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
            quantity: item.quantity,
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
                status: item.status || 'Pendiente',
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
                params: { type },
            });
            deliveryModal.value.stocks = response.data;
        }
    } catch (error) {
        console.error('Error al cargar stock:', error);
    }
};

const getStockForHq = (hqId: number) => {
    const size = deliveryModal.value.item.epp_id
        ? getDraftValue(deliveryModal.value.item.epp_id, 'clothing_size', deliveryModal.value.item.clothing_size)
        : deliveryModal.value.item.clothing_size;

    const colorId = deliveryModal.value.item.epp_id
        ? getDraftValue(deliveryModal.value.item.epp_id, 'color_id', deliveryModal.value.item.color_id)
        : deliveryModal.value.item.color_id;

    const stock = deliveryModal.value.stocks.find(
        (s) => s.headquarter_id === hqId && String(s.size) === String(size) && String(s.color_id) === String(colorId),
    );
    return stock ? stock.quantity : 0;
};

const getMultiStockForHq = (item: any, hqId: number) => {
    const key = getRowKey(item);
    const stocks = multiDeliveryModal.value.stocks[key] || [];

    const size = item.epp_id ? getDraftValue(item.epp_id, 'clothing_size', item.clothing_size) : item.clothing_size;

    const colorId = item.epp_id ? getDraftValue(item.epp_id, 'color_id', item.color_id) : item.color_id;

    const stock = stocks.find((s: any) => s.headquarter_id === hqId && String(s.size) === String(size) && String(s.color_id) === String(colorId));
    return stock ? stock.quantity : 0;
};

const updateStatus = (
    clothEntryId: number,
    status: string,
    colorId?: number | null,
    size?: string,
    eppId?: number | null,
    quantity?: number,
    headquarterId?: string | null,
) => {
    // Stock validation for delivery
    if (status === 'Entregado' && headquarterId && quantity !== undefined) {
        const stock = getStockForHq(Number(headquarterId));
        if (stock < quantity) {
            Swal.fire({
                icon: 'error',
                title: 'Stock Insuficiente',
                text: `No hay suficiente stock en esta sede (${stock} disponibles).`,
                confirmButtonColor: '#e11d48',
            });
            return;
        }
    }

    router.post(
        route('clothes.status'),
        {
            id: clothEntryId,
            status: status,
            color_id: colorId,
            clothing_size: size,
            epp_id: eppId,
            quantity: quantity,
            headquarter_id: headquarterId,
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['staff', 'flash'],
        },
    );
};

const confirmAssignment = (draft: any, headquarterId: string | null = null) => {
    // Stock validation
    if (headquarterId && draft.quantity !== undefined) {
        const stock = getStockForHq(Number(headquarterId));
        if (stock < draft.quantity) {
            Swal.fire({
                icon: 'error',
                title: 'Stock Insuficiente',
                text: `No hay suficiente stock en esta sede (${stock} disponibles).`,
                confirmButtonColor: '#e11d48',
            });
            return;
        }
    }

    router.post(
        route('inventory.assign-clothes'),
        {
            staff_id: props.staff?.id,
            reason: assignReason.value,
            create_history: true,
            items: [
                {
                    epp_id: draft.epp_id,
                    size: draft.clothing_size || draft.size,
                    color_id: draft.color_id,
                    quantity: draft.quantity || 1,
                    status: draft.status || 'Entregado',
                    headquarter_id: headquarterId,
                },
            ],
        },
        {
            onSuccess: () => {
                if (draft.epp_id) delete requirementDrafts.value[draft.epp_id];
                deliveryModal.value.open = false;
            },
            preserveScroll: true,
            preserveState: true,
            only: ['staff', 'flash'],
        },
    );
};

// ── Perfil de Referencia (profile items: !cloth_id && !epp_id) ────────────
const isAddingProfile = ref(false);
const newProfileItem = ref({ clothe_name: '', clothing_size: '' });
const profileEdits = ref<Record<number, { clothe_name: string; clothing_size: string }>>({});

const profileItems = computed(() =>
    props.staff?.staff_clothes.filter((c: StaffCloth) => !c.cloth_id && !c.epp_id) ?? [],
);

function startEditProfile(item: StaffCloth) {
    profileEdits.value[item.id] = {
        clothe_name: item.clothe_name ?? '',
        clothing_size: item.clothing_size ?? '',
    };
}

function cancelEditProfile(id: number) {
    delete profileEdits.value[id];
}

function saveProfileItem(item: StaffCloth) {
    const draft = profileEdits.value[item.id];
    if (!draft) return;
    router.put(
        route('clothes.profile.update', item.id),
        { clothe_name: draft.clothe_name, clothing_size: draft.clothing_size },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['staff', 'flash'],
            onSuccess: () => delete profileEdits.value[item.id],
        },
    );
}

function addProfileItem() {
    if (!props.staff || !newProfileItem.value.clothe_name.trim()) return;
    router.post(
        route('clothes.profile.store'),
        { staff_id: props.staff.id, ...newProfileItem.value },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['staff', 'flash'],
            onSuccess: () => {
                newProfileItem.value = { clothe_name: '', clothing_size: '' };
                isAddingProfile.value = false;
            },
        },
    );
}

function deleteProfileItem(id: number) {
    Swal.fire({
        icon: 'warning',
        title: '¿Eliminar prenda?',
        text: 'Esta acción no se puede deshacer.',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Eliminar',
    }).then((result) => {
        if (!result.isConfirmed) return;
        router.delete(route('clothes.profile.destroy', id), {
            preserveScroll: true,
            preserveState: true,
            only: ['staff', 'flash'],
        });
    });
}

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
    router.post(
        route('inventory.history.evidence', histId),
        {
            evidence_image: file,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'Éxito',
                    text: 'Evidencia subida correctamente',
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
        },
    );
};

watch(
    () => props.open,
    (val) => {
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
    },
);
</script>

<template>
    <div class="contents">
        <Dialog :open="open" @update:open="$emit('update:open', $event)">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[850px]">
                <DialogHeader>
                    <div class="flex items-start justify-between">
                        <div>
                            <DialogTitle class="text-xl font-black">Control de EPPs</DialogTitle>
                            <DialogDescription>
                                Gestión de tallas y asignación de EPP para {{ staff?.name }}
                                <span
                                    v-if="staff?.role || staff?.staffable"
                                    class="mt-1 block text-[10px] font-bold tracking-widest text-indigo-500 uppercase"
                                >
                                    {{ staff?.role?.name || 'Sin cargo' }} • {{ staff?.staffable?.name || 'Sin comedor' }}
                                </span>
                            </DialogDescription>
                        </div>
                        <Button
                            v-if="!isAssigning"
                            @click="isAssigning = true"
                            size="sm"
                            class="gap-2 bg-indigo-600 text-white shadow-lg hover:bg-indigo-700"
                        >
                            <Plus class="h-4 w-4" /> Nueva Asignación EPP
                        </Button>
                        <Button v-else @click="isAssigning = false" size="sm" variant="ghost" class="text-[10px] font-bold text-slate-500 uppercase">
                            Cancelar
                        </Button>
                    </div>
                </DialogHeader>

                <div class="grid gap-6 py-4" v-if="staff">
                    <!-- Assignment Mode -->
                    <div v-if="isAssigning" class="animate-in fade-in slide-in-from-top-4 space-y-6 duration-300">
                        <div class="space-y-4 rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4">
                            <h3 class="flex items-center gap-2 text-xs font-black tracking-widest text-indigo-600 uppercase">
                                <Plus class="h-4 w-4" /> Agregar EPP al Listado
                            </h3>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="relative space-y-2">
                                    <Label class="text-[10px] font-bold text-slate-500 uppercase">Buscar EPP</Label>
                                    <div class="relative">
                                        <Search class="absolute top-2.5 left-3 h-4 w-4 text-slate-400" />
                                        <Input v-model="eppSearch" placeholder="Casco, Guantes..." class="h-10 border-none bg-white pl-9 shadow-sm" />
                                        <div v-if="isSearching" class="absolute top-2.5 right-3">
                                            <Loader2 class="h-4 w-4 animate-spin text-indigo-500" />
                                        </div>
                                    </div>

                                    <div
                                        v-if="searchResults.length > 0"
                                        class="absolute z-50 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-slate-100 bg-white shadow-2xl"
                                    >
                                        <div
                                            v-for="epp in searchResults"
                                            :key="epp.id"
                                            @click="selectEpp(epp)"
                                            class="flex cursor-pointer items-center justify-between border-b p-3 text-sm transition-colors last:border-none hover:bg-indigo-50"
                                        >
                                            <span class="font-bold text-slate-700">{{ epp.name }}</span>
                                            <Check v-if="newAssignment.epp_id === String(epp.id)" class="h-4 w-4 text-indigo-500" />
                                        </div>
                                    </div>
                                    <div
                                        v-if="newAssignment.epp_name"
                                        class="mt-1 flex items-center gap-1 text-[10px] font-black text-indigo-600 uppercase"
                                    >
                                        <Check class="h-3 w-3" /> Seleccionado: {{ newAssignment.epp_name }}
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="space-y-2">
                                        <Label class="text-[10px] font-bold text-slate-500 uppercase">Talla</Label>
                                        <Select v-model="newAssignment.size">
                                            <SelectTrigger class="h-10 border-none bg-white shadow-sm">
                                                <SelectValue :placeholder="newAssignment.available_sizes.length ? 'Seleccionar...' : '-'" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="size in newAssignment.available_sizes" :key="size.id" :value="size.size">
                                                    {{ size.size }}
                                                </SelectItem>
                                                <div v-if="newAssignment.available_sizes.length === 0" class="p-2 text-xs text-slate-400 italic">
                                                    No hay tallas
                                                </div>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="space-y-2">
                                        <Label class="text-[10px] font-bold text-slate-500 uppercase">Color</Label>
                                        <Select v-model="newAssignment.color_id">
                                            <SelectTrigger class="h-10 border-none bg-white shadow-sm">
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

                            <Button
                                @click="addPending"
                                :disabled="!newAssignment.epp_id || !newAssignment.size || !newAssignment.color_id || !newAssignment.quantity"
                                class="h-10 w-full bg-slate-900 text-[10px] font-black tracking-widest text-white uppercase"
                            >
                                Añadir a la Lista
                            </Button>
                        </div>

                        <!-- Pending List -->
                        <div v-if="pendingAssignments.length > 0" class="space-y-4">
                            <div class="overflow-hidden rounded-2xl border border-slate-100 shadow-sm">
                                <table class="w-full text-sm">
                                    <thead class="border-b bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-[10px] font-black text-slate-400 uppercase">EPP</th>
                                            <th class="px-4 py-2 text-center text-[10px] font-black text-slate-400 uppercase">Talla</th>
                                            <th class="px-4 py-2 text-left text-[10px] font-black text-slate-400 uppercase">Color</th>
                                            <th class="w-10 px-4 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <tr v-for="(item, idx) in pendingAssignments" :key="idx" class="transition-colors hover:bg-slate-50/50">
                                            <td class="px-4 py-3 font-bold text-slate-700">{{ item.epp_name }}</td>
                                            <td class="px-4 py-3 text-center font-black text-indigo-600">{{ item.size }}</td>
                                            <td class="px-4 py-3 text-slate-500">{{ colors.find((c) => String(c.id) === item.color_id)?.name }}</td>
                                            <td class="px-4 py-3">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    @click="removePending(idx)"
                                                    class="h-8 w-8 text-slate-300 hover:text-rose-500"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div
                                class="flex flex-col items-center justify-between gap-4 rounded-2xl border border-indigo-100 bg-indigo-50 p-4 sm:flex-row"
                            >
                                <div class="flex w-full items-center gap-3 sm:w-auto">
                                    <Label class="text-[10px] font-black whitespace-nowrap text-indigo-700 uppercase">Motivo:</Label>
                                    <Select v-model="assignReason">
                                        <SelectTrigger class="h-10 w-full border-none bg-white text-xs shadow-sm sm:w-40">
                                            <SelectValue placeholder="Motivo..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Nuevo">Nuevo Ingreso</SelectItem>
                                            <SelectItem value="Renovación">Renovación regular</SelectItem>
                                            <SelectItem value="Reposición">Reposición</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <Button
                                    @click="submitAssignments"
                                    class="h-10 w-full bg-indigo-600 text-[11px] font-black tracking-widest text-white uppercase shadow-lg shadow-indigo-200 hover:bg-indigo-700 sm:w-auto"
                                >
                                    Confirmar Asignación y Descontar
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Normal View (Summary) -->
                    <div v-else class="space-y-6">
                        <Tabs defaultValue="assignments" class="w-full">
                            <TabsList class="mb-4 grid h-12 w-full grid-cols-2 rounded-xl bg-slate-100 p-1">
                                <TabsTrigger
                                    value="assignments"
                                    class="rounded-lg text-xs font-bold tracking-widest uppercase data-[state=active]:bg-white data-[state=active]:shadow-sm"
                                    >Estado Actual</TabsTrigger
                                >
                                <TabsTrigger
                                    value="history"
                                    class="rounded-lg text-xs font-bold tracking-widest uppercase data-[state=active]:bg-white data-[state=active]:shadow-sm"
                                    >Historial de Entregas</TabsTrigger
                                >
                            </TabsList>

                            <TabsContent value="assignments" class="animate-in fade-in zoom-in-95 space-y-6 duration-200">
                                <!-- Perfil de Referencia (editable) -->
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h3 class="border-l-2 border-slate-200 pl-3 text-xs font-black tracking-widest text-slate-400 uppercase">
                                            Perfil de Referencia
                                        </h3>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="h-7 gap-1.5 rounded-lg px-2 text-[10px] font-black text-indigo-600 uppercase hover:bg-indigo-50"
                                            @click="isAddingProfile = !isAddingProfile"
                                        >
                                            <component :is="isAddingProfile ? X : Plus" class="h-3.5 w-3.5" />
                                            {{ isAddingProfile ? 'Cancelar' : 'Agregar prenda' }}
                                        </Button>
                                    </div>

                                    <!-- Formulario para agregar nueva prenda -->
                                    <div
                                        v-if="isAddingProfile"
                                        class="flex flex-col gap-3 rounded-2xl border border-indigo-100 bg-indigo-50/60 p-3 sm:flex-row sm:items-end"
                                    >
                                        <div class="flex-1 space-y-1">
                                            <Label class="text-[10px] font-bold text-slate-500 uppercase">Nombre</Label>
                                            <Input
                                                v-model="newProfileItem.clothe_name"
                                                placeholder="Talla Camisa, Talla Pantalón..."
                                                class="h-9 border-none bg-white text-xs shadow-sm"
                                            />
                                        </div>
                                        <div class="w-32 space-y-1">
                                            <Label class="text-[10px] font-bold text-slate-500 uppercase">Talla</Label>
                                            <Input
                                                v-model="newProfileItem.clothing_size"
                                                placeholder="M, L, 32..."
                                                class="h-9 border-none bg-white text-xs shadow-sm uppercase"
                                            />
                                        </div>
                                        <Button
                                            :disabled="!newProfileItem.clothe_name.trim()"
                                            class="h-9 shrink-0 bg-indigo-600 px-4 text-[10px] font-black text-white uppercase hover:bg-indigo-700 disabled:opacity-50"
                                            @click="addProfileItem"
                                        >
                                            <Plus class="mr-1 h-3.5 w-3.5" /> Guardar
                                        </Button>
                                    </div>

                                    <!-- Lista de prendas de perfil -->
                                    <div v-if="profileItems.length > 0" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        <div
                                            v-for="item in profileItems"
                                            :key="item.id"
                                            class="group relative flex flex-col rounded-2xl border border-slate-100 bg-slate-50 p-3 shadow-sm transition-all hover:border-indigo-200 hover:bg-indigo-50/30"
                                        >
                                            <!-- Vista normal -->
                                            <template v-if="!profileEdits[item.id]">
                                                <span class="truncate text-[10px] font-bold text-slate-500 uppercase">{{ item.clothe_name }}</span>
                                                <span class="mt-1 text-sm font-black text-slate-900 uppercase">{{ item.clothing_size || '-' }}</span>
                                                <!-- Botones de acción -->
                                                <div class="absolute top-1.5 right-1.5 hidden gap-1 group-hover:flex">
                                                    <button
                                                        class="flex h-6 w-6 items-center justify-center rounded-lg bg-white text-slate-400 shadow-sm hover:text-indigo-600"
                                                        @click.stop="startEditProfile(item)"
                                                    >
                                                        <Pencil class="h-3 w-3" />
                                                    </button>
                                                    <button
                                                        class="flex h-6 w-6 items-center justify-center rounded-lg bg-white text-slate-400 shadow-sm hover:text-rose-500"
                                                        @click.stop="deleteProfileItem(item.id)"
                                                    >
                                                        <Trash2 class="h-3 w-3" />
                                                    </button>
                                                </div>
                                            </template>

                                            <!-- Vista edición inline -->
                                            <template v-else>
                                                <Input
                                                    v-model="profileEdits[item.id].clothe_name"
                                                    class="mb-1 h-7 border-indigo-200 bg-white p-1 text-[10px] font-bold uppercase"
                                                    placeholder="Nombre"
                                                />
                                                <Input
                                                    v-model="profileEdits[item.id].clothing_size"
                                                    class="h-8 border-indigo-200 bg-white p-1 text-sm font-black uppercase"
                                                    placeholder="Talla"
                                                />
                                                <div class="mt-2 flex gap-1">
                                                    <button
                                                        class="flex flex-1 items-center justify-center gap-1 rounded-lg bg-indigo-600 py-1 text-[9px] font-black text-white uppercase hover:bg-indigo-700"
                                                        @click="saveProfileItem(item)"
                                                    >
                                                        <Check class="h-3 w-3" /> Guardar
                                                    </button>
                                                    <button
                                                        class="flex items-center justify-center rounded-lg bg-slate-100 px-2 py-1 text-slate-500 hover:bg-slate-200"
                                                        @click="cancelEditProfile(item.id)"
                                                    >
                                                        <X class="h-3 w-3" />
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <p v-else-if="!isAddingProfile" class="text-[11px] text-slate-400 italic">
                                        Sin prendas de referencia. Usa "Agregar prenda" para añadir.
                                    </p>
                                </div>

                                <!-- EPP Asignaciones (Actual tracking) -->
                                <div class="space-y-3">
                                    <h3 class="border-l-2 border-indigo-400 px-1 pl-3 text-xs font-black tracking-widest text-slate-400 uppercase">
                                        EPPs y Requerimientos
                                    </h3>
                                    <div v-if="mergedClothes.length > 0" class="overflow-hidden rounded-2xl border shadow-sm">
                                        <table class="w-full text-sm">
                                            <thead class="border-b bg-slate-50">
                                                <tr>
                                                    <th class="w-10 px-4 py-2">
                                                        <Checkbox
                                                            :checked="
                                                                selectedKeysCount === mergedClothes.filter((i: any) => i.epp_id).length &&
                                                                selectedKeysCount > 0
                                                            "
                                                            @update:checked="selectAll"
                                                        />
                                                    </th>
                                                    <th class="px-4 py-2 text-left text-[10px] font-black text-slate-400 uppercase">
                                                        Requerimiento / Item
                                                    </th>
                                                    <th class="px-4 py-2 text-center text-[10px] font-black text-slate-400 uppercase">Cant</th>
                                                    <th class="px-4 py-2 text-center text-[10px] font-black text-slate-400 uppercase">Talla</th>
                                                    <th class="px-4 py-2 text-left text-[10px] font-black text-slate-400 uppercase">Color</th>
                                                    <th class="px-4 py-2 text-left text-[10px] font-black text-slate-400 uppercase">Condición</th>
                                                    <th class="px-4 py-2 text-left text-[10px] font-black text-slate-400 uppercase">Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                <tr
                                                    v-for="(item, idx) in mergedClothes"
                                                    :key="(item.id ? 'id-' + item.id : 'idx-' + idx) + (item.epp_id ? '-epp-' + item.epp_id : '')"
                                                    class="cursor-pointer transition-all duration-200"
                                                    :class="[
                                                        isSelected(item) ? 'border-l-4 border-l-indigo-500 bg-indigo-50/80' : 'hover:bg-slate-50/50',
                                                        selectionMode && item.epp_id ? 'ring-1 ring-indigo-100' : '',
                                                    ]"
                                                    @click="selectionMode ? toggleSelection(item) : null"
                                                >
                                                    <td class="px-4 py-3">
                                                        <div
                                                            v-if="item.epp_id"
                                                            class="flex h-5 w-5 items-center justify-center rounded-md border-2 transition-all"
                                                            :class="
                                                                isSelected(item)
                                                                    ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm'
                                                                    : 'border-slate-300 bg-white'
                                                            "
                                                            @click.stop="toggleSelection(item)"
                                                        >
                                                            <Check v-if="isSelected(item)" class="h-3.5 w-3.5 stroke-[3]" />
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="flex flex-col gap-1">
                                                            <div class="text-xs font-bold text-slate-900">
                                                                {{ item.required_name }}
                                                            </div>
                                                            <div v-if="!item.id" class="flex flex-col gap-2">
                                                                <div
                                                                    class="inline-block w-fit rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-black text-indigo-600"
                                                                >
                                                                    REQUERIDO
                                                                </div>
                                                            </div>
                                                            <div
                                                                v-else-if="!item.is_requirement"
                                                                class="text-[8px] font-black tracking-widest text-slate-400 uppercase"
                                                            >
                                                                Asignación Extra
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3 text-center">
                                                        <Input
                                                            type="number"
                                                            :model-value="getDraftValue(item.epp_id, 'quantity', item.quantity)"
                                                            @change="
                                                                (e: Event) =>
                                                                    updateAssignment(item, 'quantity', parseInt((e.target as HTMLInputElement).value))
                                                            "
                                                            class="h-8 w-12 border-none bg-slate-50 text-center font-black text-slate-900"
                                                        />
                                                    </td>
                                                    <td class="px-4 py-3 text-center">
                                                        <Select
                                                            :model-value="getDraftValue(item.epp_id, 'clothing_size', item.clothing_size)"
                                                            @update:model-value="(val: any) => updateAssignment(item, 'clothing_size', val)"
                                                        >
                                                            <SelectTrigger
                                                                class="flex h-8 w-16 justify-center border-none bg-slate-50 p-0 pr-1 text-center font-black text-slate-900"
                                                            >
                                                                <SelectValue placeholder="-" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem v-for="size in item.sizes" :key="size.id" :value="size.size">
                                                                    {{ size.size }}
                                                                </SelectItem>
                                                                <div
                                                                    v-if="!item.sizes || item.sizes.length === 0"
                                                                    class="p-2 text-[10px] text-slate-400 italic"
                                                                >
                                                                    No hay tallas
                                                                </div>
                                                            </SelectContent>
                                                        </Select>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <Select
                                                            :model-value="String(getDraftValue(item.epp_id, 'color_id', item.color_id || ''))"
                                                            @update:model-value="(val: any) => updateAssignment(item, 'color_id', parseInt(val))"
                                                        >
                                                            <SelectTrigger class="h-8 w-[120px] border-slate-200 bg-white text-xs">
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
                                                        <span
                                                            v-if="item.id"
                                                            :class="[
                                                                'rounded-full px-2 py-0.5 text-[9px] font-black uppercase',
                                                                item.condition === 'Nuevo'
                                                                    ? 'bg-emerald-100 text-emerald-700'
                                                                    : 'border border-amber-200 bg-amber-100 text-amber-700',
                                                            ]"
                                                        >
                                                            {{ item.condition || 'Nuevo' }}
                                                        </span>
                                                        <span v-else class="text-[9px] font-bold text-slate-300 italic">Sin asignar</span>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <Select
                                                            :model-value="getDraftValue(item.epp_id, 'status', item.status || 'Pendiente')"
                                                            @update:model-value="(val: any) => updateAssignment(item, 'status', val)"
                                                        >
                                                            <SelectTrigger
                                                                :class="[
                                                                    'h-8 w-[120px] border-none text-[10px] font-bold tracking-tighter uppercase',
                                                                    getStatusColorMapped(
                                                                        getDraftValue(item.epp_id, 'status', item.status || 'Pendiente'),
                                                                    ),
                                                                ]"
                                                            >
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

                                    <div
                                        v-else
                                        class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 py-10 text-center text-slate-400"
                                    >
                                        <Package class="mx-auto mb-2 h-8 w-8 opacity-20" />
                                        <p class="text-[11px] font-black tracking-widest uppercase">No hay EPPs asignados todavía.</p>
                                    </div>

                                    <!-- Action Bar -->
                                    <div
                                        class="sticky bottom-0 z-20 flex flex-col items-center justify-between gap-4 rounded-b-2xl border-t border-slate-200 bg-slate-50/90 p-4 shadow-xl backdrop-blur-md sm:flex-row"
                                    >
                                        <div class="flex items-center gap-4">
                                            <Button
                                                @click="selectionMode = !selectionMode"
                                                size="sm"
                                                variant="outline"
                                                :class="
                                                    selectionMode
                                                        ? 'border-indigo-600 bg-indigo-600 text-white hover:bg-indigo-700'
                                                        : 'border-slate-200 bg-white'
                                                "
                                                class="h-10 px-4 text-[10px] font-black tracking-widest uppercase shadow-sm transition-all"
                                            >
                                                {{ selectionMode ? 'Finalizar Selección' : 'Modo Selección' }}
                                            </Button>

                                            <div class="flex flex-col">
                                                <span
                                                    class="text-[11px] font-black uppercase"
                                                    :class="selectedKeysCount > 0 ? 'text-indigo-700' : 'text-slate-400'"
                                                >
                                                    {{ selectedKeysCount }} items seleccionados
                                                </span>
                                                <span v-if="selectionMode" class="text-[9px] leading-none font-bold text-indigo-400 uppercase"
                                                    >Haz click en las filas</span
                                                >
                                            </div>
                                        </div>

                                        <div class="flex w-full items-center gap-3 sm:w-auto">
                                            <Select v-model="assignReason" v-if="selectedKeysCount > 0">
                                                <SelectTrigger class="h-10 w-full border-slate-200 bg-white text-xs font-bold shadow-sm sm:w-48">
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
                                                class="h-10 w-full bg-indigo-600 px-6 text-[10px] font-black tracking-widest text-white uppercase shadow-lg shadow-indigo-200 transition-all hover:bg-indigo-700 active:scale-95 disabled:opacity-50 disabled:shadow-none sm:w-auto"
                                            >
                                                Registrar Entrega Múltiple
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </TabsContent>

                            <TabsContent value="history" class="animate-in fade-in zoom-in-95 duration-200">
                                <div class="mb-4 flex justify-end">
                                    <a
                                        :href="route('inventory.history.staff.pdf', staff.id)"
                                        target="_blank"
                                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-[10px] font-black tracking-widest text-white uppercase shadow-lg shadow-indigo-200 transition-all hover:bg-indigo-700 active:scale-95"
                                    >
                                        <FileText class="h-4 w-4" />
                                        Descargar Historial Completo PDF
                                    </a>
                                </div>
                                <div v-if="staff.clothes_histories && staff.clothes_histories.length > 0" class="space-y-4">
                                    <div
                                        v-for="hist in staff.clothes_histories.slice().reverse()"
                                        :key="hist.id"
                                        class="flex flex-col gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"
                                    >
                                        <div class="flex items-start justify-between border-b border-slate-50 pb-3">
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-2">
                                                    <span
                                                        class="rounded-md bg-indigo-50 px-2 py-0.5 text-[10px] font-black text-indigo-600 uppercase"
                                                        >{{ hist.reason }}</span
                                                    >
                                                    <span class="text-xs font-medium text-slate-500">{{
                                                        new Date(hist.created_at).toLocaleString()
                                                    }}</span>
                                                </div>
                                                <div class="text-[10px] font-medium text-slate-400">
                                                    Asignado por: <span class="font-bold text-slate-700">{{ hist.user?.name || 'Sistema' }}</span>
                                                </div>
                                            </div>
                                            <a
                                                :href="route('inventory.history.pdf', hist.id)"
                                                target="_blank"
                                                class="flex items-center gap-1.5 rounded-xl border border-slate-100 bg-slate-50 p-2 text-slate-500 transition-all hover:bg-slate-100 hover:text-indigo-600"
                                            >
                                                <FileText class="h-4 w-4" />
                                                <span class="hidden text-[9px] font-black tracking-widest uppercase sm:inline">PDF Entrega</span>
                                            </a>
                                        </div>
                                        <div class="mt-1 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            <div v-for="(item, idx) in hist.items" :key="idx" class="flex flex-col rounded-xl bg-slate-50 p-2">
                                                <div class="flex items-start justify-between">
                                                    <span class="text-[11px] font-bold text-slate-800">{{
                                                        eppOptions.find((e) => String(e.id) === String(item.epp_id))?.name ||
                                                        item.epp_name ||
                                                        `EPP #${item.epp_id}`
                                                    }}</span>
                                                    <span
                                                        v-if="item.condition"
                                                        :class="[
                                                            'rounded-full px-2 py-0.5 text-[8px] font-black uppercase',
                                                            item.condition === 'Nuevo'
                                                                ? 'bg-emerald-100 text-emerald-700'
                                                                : 'bg-amber-100 text-amber-700',
                                                        ]"
                                                    >
                                                        {{ item.condition }}
                                                    </span>
                                                </div>
                                                <div class="mt-1 flex gap-3 text-[10px] text-slate-500">
                                                    <span class="font-medium"
                                                        >Cant: <strong class="text-indigo-600">{{ item.quantity }}</strong></span
                                                    >
                                                    <span class="font-medium"
                                                        >Talla: <strong class="text-slate-700">{{ item.size || '-' }}</strong></span
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="hist.evidence_image" class="mt-2">
                                            <div class="mb-2 text-[10px] font-bold text-slate-400 uppercase">Evidencia Fotográfica:</div>
                                            <a
                                                :href="hist.evidence_image"
                                                target="_blank"
                                                class="block w-fit overflow-hidden rounded-xl border border-slate-200"
                                            >
                                                <img
                                                    :src="hist.evidence_image"
                                                    class="h-20 w-auto object-cover transition-transform hover:scale-105"
                                                />
                                            </a>
                                        </div>
                                        <div v-else class="mt-2 border-t border-slate-50 pt-2">
                                            <Label class="mb-1 flex items-center gap-1 text-[9px] font-black text-indigo-400 uppercase">
                                                <Camera class="h-3 w-3" /> Subir Evidencia
                                            </Label>
                                            <Input
                                                type="file"
                                                accept="image/*"
                                                class="h-8 border-dashed border-slate-200 bg-slate-50/50 text-[10px]"
                                                @change="(e: any) => e.target.files?.[0] && uploadEvidence(hist.id, e.target.files[0])"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 py-10 text-center text-slate-400"
                                >
                                    <Package class="mx-auto mb-2 h-8 w-8 opacity-20" />
                                    <p class="text-[11px] font-black tracking-widest uppercase">No hay historial de asignaciones.</p>
                                </div>
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>

                <DialogFooter class="mt-6 border-t bg-slate-50/50 p-4">
                    <Button
                        variant="outline"
                        @click="$emit('update:open', false)"
                        class="text-[10px] font-bold tracking-widest text-slate-500 uppercase"
                    >
                        Cerrar Panel
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delivery Location Modal -->
        <Dialog :open="deliveryModal.open" @update:open="deliveryModal.open = $event">
            <DialogContent class="sm:max-w-[400px]">
                <DialogHeader>
                    <DialogTitle class="text-lg font-black tracking-tight uppercase">Confirmar Entrega de Stock</DialogTitle>
                    <DialogDescription class="text-xs">
                        Selecciona desde qué almacén/ciudad se está retirando el EPP:
                        <span class="font-bold text-slate-900">{{ deliveryModal.item?.required_name || deliveryModal.item?.epp_name }}</span>
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label class="text-[10px] font-black text-slate-500 uppercase">Almacén de Origen (Sede)</Label>
                        <Select v-model="deliveryModal.headquarter_id">
                            <SelectTrigger class="h-11 w-full border-none bg-slate-50">
                                <SelectValue placeholder="Seleccionar sede..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="hq in headquarters" :key="hq.id" :value="String(hq.id)">
                                    <div class="flex w-64 items-center justify-between">
                                        <span
                                            >{{ hq.name }} <span class="ml-1 text-[9px] opacity-50">({{ hq.business?.name }})</span></span
                                        >
                                        <span
                                            :class="[
                                                'rounded-full px-2 py-0.5 text-[10px] font-black',
                                                getStockForHq(hq.id) > 0 ? 'bg-green-100 text-green-700' : 'bg-rose-100 text-rose-700',
                                            ]"
                                        >
                                            Stock: {{ getStockForHq(hq.id) }}
                                        </span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-3">
                        <div class="flex items-center justify-between text-[10px] font-bold">
                            <span class="text-indigo-600">Cant. a Descontar:</span>
                            <span class="rounded-full bg-indigo-600 px-2 py-0.5 text-white">{{ deliveryModal.item?.quantity }}</span>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="deliveryModal.open = false" class="text-[10px] font-bold uppercase">Cancelar</Button>
                    <Button
                        @click="
                            deliveryModal.item?.id
                                ? updateStatus(
                                      deliveryModal.item.id,
                                      'Entregado',
                                      deliveryModal.item.color_id,
                                      deliveryModal.item.clothing_size,
                                      deliveryModal.item.epp_id,
                                      deliveryModal.item.quantity,
                                      deliveryModal.headquarter_id,
                                  )
                                : confirmAssignment(deliveryModal.item, deliveryModal.headquarter_id)
                        "
                        :disabled="!deliveryModal.headquarter_id"
                        class="bg-indigo-600 text-[10px] font-black text-white uppercase hover:bg-indigo-700"
                    >
                        Confirmar y Descontar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <!-- Multi Delivery Location Modal -->
        <Dialog :open="multiDeliveryModal.open" @update:open="multiDeliveryModal.open = $event">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-[600px]">
                <DialogHeader>
                    <DialogTitle class="text-lg font-black tracking-tight uppercase">Seleccionar Origen del Stock</DialogTitle>
                    <DialogDescription class="text-xs">
                        Confirma desde qué almacén se descontará cada uno de los
                        <span class="font-bold text-slate-900">{{ multiDeliveryModal.items.length }}</span> items seleccionados.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div v-if="multiDeliveryModal.isLoading" class="flex justify-center py-6">
                        <Loader2 class="h-6 w-6 animate-spin text-indigo-500" />
                    </div>
                    <div v-else class="flex flex-col gap-2 space-y-4">
                        <div
                            v-for="item in multiDeliveryModal.items"
                            :key="getRowKey(item)"
                            class="flex flex-col gap-3 rounded-xl border border-slate-100 bg-slate-50 p-4"
                        >
                            <div class="flex items-start justify-between border-b border-slate-200 pb-2">
                                <div class="max-w-[200px] truncate text-sm font-bold text-slate-800">{{ item.required_name || item.epp_name }}</div>
                                <div class="flex gap-2">
                                    <span
                                        class="flex items-center gap-1 rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-black text-indigo-700 uppercase"
                                    >
                                        {{ getDraftValue(item.epp_id, 'clothing_size', item.clothing_size) }}
                                    </span>
                                    <span class="flex items-center gap-1 rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-black text-slate-600">
                                        Cant: {{ getDraftValue(item.epp_id, 'quantity', item.quantity) || 1 }}
                                    </span>
                                </div>
                            </div>

                            <Select v-model="multiDeliveryModal.headquarters[getRowKey(item)]">
                                <SelectTrigger class="h-11 w-full border border-slate-200 bg-white text-xs shadow-sm">
                                    <SelectValue placeholder="Seleccionar sede de origen..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="hq in headquarters" :key="hq.id" :value="String(hq.id)">
                                        <div class="flex w-64 items-center justify-between">
                                            <span
                                                >{{ hq.name }} <span class="ml-1 text-[9px] opacity-50">({{ hq.business?.name }})</span></span
                                            >
                                            <span
                                                :class="[
                                                    'rounded-full px-2 py-0.5 text-[10px] font-black',
                                                    getMultiStockForHq(item, hq.id) >= (getDraftValue(item.epp_id, 'quantity', item.quantity) || 1)
                                                        ? 'bg-green-100 text-green-700'
                                                        : 'bg-rose-100 text-rose-700',
                                                ]"
                                            >
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
                        :disabled="
                            multiDeliveryModal.isLoading || Object.keys(multiDeliveryModal.headquarters).length !== multiDeliveryModal.items.length
                        "
                        class="bg-indigo-600 text-[10px] font-black text-white uppercase hover:bg-indigo-700"
                    >
                        Confirmar y Procesar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
