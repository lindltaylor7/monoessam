<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { 
    Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger, DialogFooter 
} from '@/components/ui/dialog';
import { 
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue 
} from '@/components/ui/select';
import { 
    Plus, Trash2, Calendar, FileText, Truck, Building, Box, Shirt, ArrowUpRight, History, MoreHorizontal, Edit2, UploadCloud, Loader2
} from 'lucide-vue-next';
import { 
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow 
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

interface Props {
    invoices: any[];
    clothProviders: any[];
    businesses: any[];
    headquarters: any[];
    cafes: any[];
    clothes: any[];
    colors: any[];
    epps: any[];
    cities: any[];
}

const props = defineProps<Props>();

// --- Invoice Form Logic ---
const isInvoiceModalOpen = ref(false);
const invoiceForm = ref({
    business_id: '',
    headquarter_id: '',
    cloth_provider_id: '',
    invoice_number: '',
    date: new Date().toISOString().split('T')[0],
    notes: '',
    invoice_image: null as File | null,
    items: [
        { cloth_id: '', epp_id: '', color_id: '', size: '', quantity: 1, unit_price: 0 }
    ]
});

const filteredHeadquarters = computed(() => {
    if (!invoiceForm.value.business_id) return [];
    return props.headquarters.filter(hq => String(hq.business_id) === invoiceForm.value.business_id);
});

watch(() => invoiceForm.value.business_id, () => {
    invoiceForm.value.headquarter_id = '';
});

const getAvailableClothes = (providerId: string | number) => {
    let baseList: any[] = [];
    if (!providerId) {
        baseList = [
            ...props.clothes.map((c: any) => ({ ...c, type: 'cloth', unique_id: `cloth_${c.id}` })),
            ...props.epps.map((e: any) => ({ ...e, type: 'epp', unique_id: `epp_${e.id}` }))
        ];
    } else {
        const provider = props.clothProviders.find(p => String(p.id) === String(providerId));
        if (!provider) return [];
        
        baseList = [
            ...(provider.clothes || []).map((c: any) => ({ ...c, type: 'cloth', unique_id: `cloth_${c.id}` })),
            ...(provider.epps || []).map((e: any) => ({ ...e, type: 'epp', unique_id: `epp_${e.id}` }))
        ];
    }
    return baseList;
};

const onItemSelect = (uniqueId: string, index: number) => {
    const item = invoiceForm.value.items[index];
    const available = getAvailableClothes(invoiceForm.value.cloth_provider_id);
    const selected = available.find(a => a.unique_id === uniqueId);
    
    if (selected) {
        if (selected.type === 'cloth') {
            item.cloth_id = String(selected.id);
            item.epp_id = '';
            item.unit_price = 0; 
        } else {
            item.epp_id = String(selected.id);
            item.cloth_id = '';
            
            // Try to find price for this provider
            const providerId = invoiceForm.value.cloth_provider_id;
            // Note: We don't have city context here directly in the form header yet, 
            // but we can try to match if multiple prices exist or use a default.
            // For now, if we have the city in the item, we use it.
            const cityId = item.size ? getCityIdFromSize(selected, item.size) : null;
            
            const priceEntry = selected.city_providers?.find((cp: any) => 
                String(cp.cloth_provider_id) === String(providerId) && 
                (!cityId || String(cp.city_id) === String(cityId))
            );
            
            item.unit_price = Number(priceEntry?.cost_price || 0);
        }
        item.size = ''; // Reset size on item change
    }
};

const getCityIdFromSize = (epp: any, sizeName: string) => {
    const size = epp.sizes?.find((s: any) => s.size === sizeName);
    return size?.city_id;
};

// Re-calculate price when size (and thus city) changes for EPP
watch(() => invoiceForm.value.items, (newItems) => {
    newItems.forEach((item, index) => {
        if (item.epp_id && item.size) {
            const epp = props.epps.find(e => String(e.id) === String(item.epp_id));
            if (epp) {
                const cityId = getCityIdFromSize(epp, item.size);
                const providerId = invoiceForm.value.cloth_provider_id;
                const priceEntry = epp.city_providers?.find((cp: any) => 
                    String(cp.cloth_provider_id) === String(providerId) && 
                    String(cp.city_id) === String(cityId)
                );
                if (priceEntry) {
                    item.unit_price = Number(priceEntry.cost_price);
                }
            }
        }
    });
}, { deep: true });

const getItemUniqueId = (item: any) => {
    if (item.cloth_id) return `cloth_${item.cloth_id}`;
    if (item.epp_id) return `epp_${item.epp_id}`;
    return '';
};

const getSizesForItem = (uniqueId: string) => {
    if (!uniqueId) return [];
    if (uniqueId.startsWith('epp_')) {
        const eppId = uniqueId.split('_')[1];
        const epp = props.epps.find(e => String(e.id) === eppId);
        return epp?.sizes || [];
    }
    return []; // Cloth models don't have sizes relation in migration yet
};

const getAvailablePrices = (eppId: string | number, providerId: string | number) => {
    const epp = props.epps.find(e => String(e.id) === String(eppId));
    if (!epp || !epp.city_providers) return [];
    return epp.city_providers.filter((cp: any) => String(cp.cloth_provider_id) === String(providerId));
};


const addInvoiceItem = () => {
    invoiceForm.value.items.push({ cloth_id: '', epp_id: '', color_id: '', size: '', quantity: 1, unit_price: 0 });
};

const removeInvoiceItem = (index: number) => {
    if (invoiceForm.value.items.length > 1) {
        invoiceForm.value.items.splice(index, 1);
    }
};

const handleInvoiceImageUpload = (e: Event) => {
    const target = e.target as HTMLInputElement;
    invoiceForm.value.invoice_image = target.files?.[0] || null;
};

const isInvoiceSubmitDisabled = computed(() => {
    return !invoiceForm.value.business_id || 
           !invoiceForm.value.cloth_provider_id || 
           (filteredHeadquarters.value.length > 0 && !invoiceForm.value.headquarter_id) ||
           invoiceForm.value.items.length === 0 || 
           invoiceForm.value.items.some(i => (!i.cloth_id && !i.epp_id) || i.quantity <= 0);
});

const handleInvoiceSubmit = () => {
    if (isInvoiceSubmitDisabled.value) return;
    
    const submitData = {
        ...invoiceForm.value,
        headquarter_id: invoiceForm.value.headquarter_id === '' ? null : invoiceForm.value.headquarter_id,
        items: invoiceForm.value.items.map(item => ({
            ...item,
            color_id: (item.color_id === 'none' || item.color_id === '') ? null : item.color_id
        }))
    };

    router.post(route('inventory.invoice.store'), submitData, {
        forceFormData: true,
        onSuccess: () => {
            isInvoiceModalOpen.value = false;
            resetInvoiceForm();
        },
        onError: (errors) => {
            console.error('Submission errors:', errors);
            alert('Error al guardar la factura. Por favor revise los campos.');
        },
        preserveScroll: true
    });
};

const resetInvoiceForm = () => {
    invoiceForm.value = {
        business_id: '',
        headquarter_id: '',
        cloth_provider_id: '',
        invoice_number: '',
        date: new Date().toISOString().split('T')[0],
        notes: '',
        invoice_image: null as File | null,
        items: [{ cloth_id: '', epp_id: '', color_id: '', size: '', quantity: 1, unit_price: 0 }]
    };
};

// --- Provider CRUD Logic ---
const isProviderModalOpen = ref(false);
const editingProvider = ref<any>(null);
const providerForm = ref({
    name: '',
    email: '',
    phone: '',
});

const openProviderModal = (provider: any = null) => {
    if (provider) {
        editingProvider.value = provider;
        providerForm.value = { 
            name: provider.name || '', 
            email: provider.email || '', 
            phone: provider.phone || ''
        };
    } else {
        editingProvider.value = null;
        providerForm.value = { name: '', email: '', phone: '' };
    }
    isProviderModalOpen.value = true;
};

const handleProviderSubmit = () => {
    if (editingProvider.value) {
        router.put(route('inventory.providers.update', editingProvider.value.id), providerForm.value, {
            onSuccess: () => isProviderModalOpen.value = false
        });
    } else {
        router.post(route('inventory.providers.store'), providerForm.value, {
            onSuccess: () => isProviderModalOpen.value = false
        });
    }
};

const deleteProvider = (id: number) => {
    if (confirm('¿Estás seguro de eliminar este proveedor?')) {
        router.delete(route('inventory.providers.destroy', id));
    }
};

// --- EPP Logic ---
const isEppModalOpen = ref(false);
const eppForm = ref({ name: '' });

const handleEppSubmit = () => {
    router.post(route('inventory.epps.store'), eppForm.value, {
        onSuccess: () => {
            isEppModalOpen.value = false;
            eppForm.value.name = '';
        }
    });
};

// --- EPP Price Logic ---
const isPriceModalOpen = ref(false);
const selectedEppForPrice = ref<any>(null);
const priceForm = ref({
    epp_id: '',
    cloth_provider_id: '',
    city_id: '',
    cost_price: ''
});

const openPriceModal = (epp: any, providerId: string | number | null = null) => {
    selectedEppForPrice.value = epp;
    priceForm.value = {
        epp_id: String(epp.id),
        cloth_provider_id: providerId ? String(providerId) : '',
        city_id: '',
        cost_price: ''
    };
    isPriceModalOpen.value = true;
};

const handlePriceSubmit = () => {
    router.post(route('inventory.epps.assign-price'), priceForm.value, {
        onSuccess: () => {
            priceForm.value.cloth_provider_id = '';
            priceForm.value.city_id = '';
            priceForm.value.cost_price = '';
             isPriceModalOpen.value = false;
        }
    });
};

// --- Assignment Logic ---
const isAssignEppModalOpen = ref(false);
const assigningProvider = ref<any>(null);
const selectedEppIds = ref<number[]>([]);
const selectedClothIds = ref<number[]>([]);

const openAssignModal = (provider: any) => {
    assigningProvider.value = provider;
    
    // Get unique IDs from both the pivot relationship and the city_providers price table
    const pivotIds = provider.epps.map((e: any) => e.id);
    const ternaryIds = props.epps
        .filter(epp => epp.city_providers?.some((cp: any) => String(cp.cloth_provider_id) === String(provider.id)))
        .map(epp => epp.id);

    selectedEppIds.value = [...new Set([...pivotIds, ...ternaryIds])];
    selectedClothIds.value = provider.clothes?.map((c: any) => c.id) || [];
    isAssignEppModalOpen.value = true;
};

const handleAssignmentSubmit = () => {
    router.post(route('inventory.providers.epps.sync', assigningProvider.value.id), {
        epp_ids: selectedEppIds.value,
        cloth_ids: selectedClothIds.value
    }, {
        onSuccess: () => isAssignEppModalOpen.value = false
    });
};

// --- View Invoice Details ---
const isViewInvoiceModalOpen = ref(false);
const selectedInvoice = ref<any>(null);
const isUploadingInvoiceImage = ref(false);

const viewInvoiceDetails = (invoice: any) => {
    selectedInvoice.value = invoice;
    isViewInvoiceModalOpen.value = true;
};

const handleInvoiceImageUpdate = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file || !selectedInvoice.value) return;

    isUploadingInvoiceImage.value = true;
    router.post(route('inventory.invoice.image.update', selectedInvoice.value.id), {
        invoice_image: file
    }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (page) => {
            const updatedInvoice = (page.props as any).invoices.find((i: any) => i.id === selectedInvoice.value.id);
            if (updatedInvoice) {
                selectedInvoice.value = updatedInvoice;
            }
        },
        onFinish: () => {
            isUploadingInvoiceImage.value = false;
        }
    });
};

// --- EPP Size Logic ---
const isSizeModalOpen = ref(false);
const selectedEppForSizes = ref<any>(null);
const sizeForm = ref({
    epp_id: '',
    city_id: '',
    size: ''
});

const openSizeModal = (epp: any) => {
    selectedEppForSizes.value = epp;
    sizeForm.value = {
        epp_id: epp.id,
        city_id: '',
        size: ''
    };
    isSizeModalOpen.value = true;
};

const handleSizeSubmit = () => {
    router.post(route('inventory.epp-sizes.store'), sizeForm.value, {
        onSuccess: () => {
            sizeForm.value.city_id = '';
            sizeForm.value.size = '';
            // Update the local list if needed or let Inertia reload
        }
    });
};

const deleteSize = (id: number) => {
    if (confirm('¿Eliminar esta talla?')) {
        router.delete(route('inventory.epp-sizes.destroy', id));
    }
};

// --- EPP Inline Editing ---
const editingPriceId = ref<number | null>(null);
const tempPriceValue = ref('');

const startEditingPrice = (cp: any) => {
    editingPriceId.value = cp.id;
    tempPriceValue.value = String(cp.cost_price);
};

const saveInlinePrice = (cp: any) => {
    if (editingPriceId.value === null) return;
    
    const newPrice = parseFloat(tempPriceValue.value);
    if (isNaN(newPrice) || newPrice < 0) {
        editingPriceId.value = null;
        return;
    }

    // Only update if value changed
    if (newPrice !== parseFloat(cp.cost_price)) {
        router.post(route('inventory.epps.assign-price'), {
            epp_id: cp.epp_id,
            cloth_provider_id: cp.cloth_provider_id,
            city_id: cp.city_id,
            cost_price: newPrice
        }, {
            preserveScroll: true,
            onSuccess: () => {
                editingPriceId.value = null;
            }
        });
    } else {
        editingPriceId.value = null;
    }
};

const vFocus = {
  mounted: (el: HTMLElement) => el.focus()
};

</script>

<template>
    <Head title="Facturas y Proveedores" />

    <AppLayout :breadcrumbs="[
        { title: 'Logística', href: route('logistics') },
        { title: 'Inventario', href: route('inventory.index') },
        { title: 'Facturas y Proveedores', href: route('inventory.invoices.index') }
    ]">
        <div class="flex flex-col h-full w-full p-4 sm:p-6 gap-6 bg-slate-50/50">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 flex items-center gap-3">
                        <FileText class="h-8 w-8 text-indigo-600" />
                        Facturas y Proveedores de Prendas
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        Historial de ingresos y gestión de proveedores especializados.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button @click="isEppModalOpen = true" variant="outline" class="gap-2 border-slate-200 bg-white">
                        <Box class="h-4 w-4" /> Nuevo EPP
                    </Button>
                    <Button @click="openProviderModal()" variant="outline" class="gap-2 border-slate-200 bg-white">
                        <Truck class="h-4 w-4" /> Nuevo Proveedor
                    </Button>
                    <Dialog v-model:open="isInvoiceModalOpen">
                        <DialogTrigger as-child>
                            <Button class="gap-2 bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-200">
                                <Plus class="h-4 w-4" /> Ingresar Factura
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-[1000px] max-h-[100vh] overflow-y-auto">
                            <DialogHeader>
                                <DialogTitle class="flex items-center gap-2">
                                    <FileText class="h-5 w-5 text-indigo-600" />
                                    Nueva Factura de Stock
                                </DialogTitle>
                                <DialogDescription>Registre el ingreso de prendas detallando proveedores, costos y colores.</DialogDescription>
                            </DialogHeader>
                            
                            <div class="grid gap-6 py-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-slate-50 rounded-2xl border border-slate-100">
                                    <div class="space-y-2">
                                        <Label class="text-xs font-bold uppercase text-slate-500">Empresa (Business)</Label>
                                        <Select v-model="invoiceForm.business_id">
                                            <SelectTrigger class="bg-white"><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="b in businesses" :key="b.id" :value="String(b.id)">{{ b.name }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="space-y-2">
                                        <Label class="text-xs font-bold uppercase text-slate-500">Sede (Correspondiente)</Label>
                                        <Select v-model="invoiceForm.headquarter_id" :disabled="!invoiceForm.business_id">
                                            <SelectTrigger class="bg-white"><SelectValue placeholder="Elegir Sede" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="hq in filteredHeadquarters" :key="hq.id" :value="String(hq.id)">{{ hq.name }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="space-y-2">
                                        <Label class="text-xs font-bold uppercase text-slate-500">Proveedor</Label>
                                        <Select v-model="invoiceForm.cloth_provider_id">
                                            <SelectTrigger class="bg-white"><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="p in clothProviders" :key="p.id" :value="String(p.id)">{{ p.name }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div class="space-y-2">
                                        <Label class="text-xs font-bold uppercase text-slate-500">Nº Factura</Label>
                                        <Input v-model="invoiceForm.invoice_number" placeholder="Ej: F-001-123" class="bg-white" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label class="text-xs font-bold uppercase text-slate-500">Fecha</Label>
                                        <Input v-model="invoiceForm.date" type="date" class="bg-white" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label class="text-xs font-bold uppercase text-slate-500">Notas Adicionales</Label>
                                        <Input v-model="invoiceForm.notes" placeholder="..." class="bg-white" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label class="text-xs font-bold uppercase text-slate-500">Evidencia (Opcional)</Label>
                                        <Input type="file" @change="handleInvoiceImageUpload" accept="image/*,.pdf" class="bg-white text-xs file:bg-slate-100 file:border-0 file:mr-4 file:py-2 file:px-4 file:rounded-full file:text-xs file:font-semibold hover:file:bg-slate-200" />
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div class="flex justify-between items-center px-1">
                                        <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                                            <Shirt class="h-4 w-4 text-indigo-500" />
                                            Prendas Incluidas
                                        </h3>
                                        <Button @click="addInvoiceItem" size="sm" variant="outline" class="h-8 gap-1.5 text-xs font-bold border-indigo-200 text-indigo-600 hover:bg-indigo-50">
                                            <Plus class="h-3.5 w-3.5" /> Agregar Fila
                                        </Button>
                                    </div>

                                    <div class="border rounded-2xl overflow-hidden shadow-sm">
                                        <table class="w-full text-sm">
                                            <thead class="bg-slate-50 border-b">
                                                <tr class="text-left text-[10px] font-black uppercase text-slate-400">
                                                    <th class="px-4 py-3">Item (Prenda/EPP)</th>
                                                    <th class="px-4 py-3">Talla</th>
                                                    <th class="px-4 py-3">Color</th>
                                                    <th class="px-4 py-3 w-20">Cant.</th>
                                                    <th class="px-4 py-3 w-32">P. Unitario</th>
                                                    <th class="px-4 py-3 w-32">Total</th>
                                                    <th class="px-4 py-3 w-12 text-center"></th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y">
                                                <tr v-for="(item, index) in invoiceForm.items" :key="index" class="group hover:bg-slate-50/50 transition-colors">
                                                   <td class="p-3">
                                                        <Select :model-value="getItemUniqueId(item)" @update:model-value="onItemSelect($event as string, index)">
                                                            <SelectTrigger class="h-9 w-[200px] border-none shadow-none focus:ring-1 justify-start">
                                                                <SelectValue placeholder="Elegir..." class="truncate" />
                                                            </SelectTrigger>
                                                            
                                                            <SelectContent>
                                                                <SelectItem v-for="c in getAvailableClothes(invoiceForm.cloth_provider_id)" :key="c.unique_id" :value="c.unique_id">
                                                                    <div class="flex items-center gap-2">
                                                                        <component :is="c.type === 'cloth' ? Shirt : Box" class="h-3.5 w-3.5 text-slate-400" />
                                                                        <span class="truncate">{{ c.name }}</span>
                                                                    </div>
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </td>
                                                    <td class="p-3">
                                                        <div v-if="getSizesForItem(getItemUniqueId(item)).length > 0">
                                                            <Select v-model="item.size">
                                                                <SelectTrigger class="h-9 border-none shadow-none focus:ring-1"><SelectValue placeholder="Talla" /></SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem v-for="s in getSizesForItem(getItemUniqueId(item))" :key="s.id" :value="s.size">
                                                                        {{ s.size }} <span class="text-[10px] text-slate-400 text-right">({{ s.city?.name }})</span>
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <Input v-else v-model="item.size" placeholder="Talla..." class="h-9 border-none shadow-none focus:ring-1" />
                                                    </td>
                                                    <td class="p-3">
                                                        <Select v-model="item.color_id">
                                                            <SelectTrigger class="h-9 border-none shadow-none focus:ring-1"><SelectValue placeholder="Ninguno" /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="none">Ninguno</SelectItem>
                                                                <SelectItem v-for="color in colors" :key="color.id" :value="String(color.id)">
                                                                    <div class="flex items-center gap-2">
                                                                        <div class="w-3 h-3 rounded-full border border-slate-200" :style="{ backgroundColor: color.hex_code }"></div>
                                                                        {{ color.name }}
                                                                    </div>
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </td>
                                                    <td class="p-3">
                                                        <Input type="number" v-model="item.quantity" min="1" class="h-9 border-none shadow-none focus:ring-1 text-center font-bold" />
                                                    </td>
                                                    <td class="p-3">
                                                        <div v-if="item.epp_id && getAvailablePrices(item.epp_id, invoiceForm.cloth_provider_id).length > 0" class="flex flex-col gap-1">
                                                            <Select v-model="item.unit_price">
                                                                <SelectTrigger class="h-9 border-none shadow-none focus:ring-1 text-xs font-bold text-indigo-600">
                                                                    <SelectValue placeholder="Elegir Precio" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem v-for="cp in getAvailablePrices(item.epp_id, invoiceForm.cloth_provider_id)" :key="cp.id" :value="Number(cp.cost_price)">
                                                                        S/.{{ Number(cp.cost_price).toFixed(2) }} ({{ cp.city?.name }})
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div v-else class="relative">
                                                            <span class="absolute left-2 top-2 text-slate-400 text-xs">S/.</span>
                                                            <Input type="number" v-model="item.unit_price" step="0.01" class="h-9 pl-6 border-none shadow-none focus:ring-1 font-bold text-indigo-600" />
                                                        </div>
                                                    </td>
                                                    <td class="p-3 font-bold text-slate-900">
                                                        S/.{{ (item.quantity * item.unit_price).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                                                    </td>
                                                    <td class="p-3 text-center">
                                                        <Button @click="removeInvoiceItem(index)" variant="ghost" size="sm" class="h-8 w-8 p-0 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-full opacity-0 group-hover:opacity-100 transition-opacity">
                                                            <Trash2 class="h-4 w-4" />
                                                        </Button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tfoot class="bg-indigo-50/30 border-t">
                                                <tr>
                                                    <td colspan="4" class="px-4 py-3 text-right font-black uppercase text-slate-500 text-[10px]">Importe Total</td>
                                                    <td class="px-4 py-3 text-lg font-black text-indigo-700">
                                                        S/.{{ invoiceForm.items.reduce((acc, item) => acc + (item.quantity * item.unit_price), 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                                                    </td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <DialogFooter class="bg-slate-50 -mx-6 -mb-6 p-6 mt-4 border-t rounded-b-lg">
                                <Button variant="ghost" @click="isInvoiceModalOpen = false" class="font-bold">Cancelar</Button>
                                <Button 
                                    @click="handleInvoiceSubmit" 
                                    :disabled="isInvoiceSubmitDisabled"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-200 font-bold"
                                >
                                    Guardar e Ingresar Stock
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <Tabs defaultValue="invoices" class="w-full">
                <TabsList class="mb-4 bg-white border">
                    <TabsTrigger value="invoices" class="gap-2">
                        <FileText class="h-4 w-4" /> Facturas Recientes
                    </TabsTrigger>
                    <TabsTrigger value="providers" class="gap-2">
                        <Truck class="h-4 w-4" /> Proveedores de Ropa
                    </TabsTrigger>
                    <TabsTrigger value="epps" class="gap-2">
                        <Box class="h-4 w-4" /> Catálogo de EPP
                    </TabsTrigger>
                </TabsList>
                
                <TabsContent value="invoices">
                    <Card class="bg-white border-slate-200 shadow-sm overflow-hidden rounded-2xl">
                        <CardHeader class="border-b bg-slate-50/50">
                            <CardTitle class="text-lg">Historial de Facturación</CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-slate-50/50 hover:bg-slate-50/50">
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Nº Factura</TableHead>
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Empresa / Sede</TableHead>
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Proveedor</TableHead>
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Fecha</TableHead>
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px] text-right">Total</TableHead>
                                        <TableHead class="w-[50px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="inv in invoices" :key="inv.id" class="group transition-colors">
                                        <TableCell class="font-bold text-indigo-600">{{ inv.invoice_number || 'S/N' }}</TableCell>
                                        <TableCell>
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-slate-900">{{ inv.business?.name }}</span>
                                                <span class="text-xs text-slate-500">{{ inv.headquarter?.name || 'General' }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell class="font-medium text-slate-700">{{ inv.provider?.name }}</TableCell>
                                        <TableCell class="text-slate-500">{{ inv.date  }}</TableCell>
                                        <TableCell class="text-right font-black text-slate-900">
                                            S/.{{ Number(inv.total_amount).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                                        </TableCell>
                                        <TableCell>
                                            <Button @click="viewInvoiceDetails(inv)" variant="ghost" size="sm" class="h-8 w-8 p-0 text-slate-400 hover:text-indigo-600 transition-colors">
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="invoices.length === 0">
                                        <TableCell colspan="6" class="h-32 text-center text-slate-400 italic">
                                            No se han registrado facturas aún.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="providers">
                    <Card class="bg-white border-slate-200 shadow-sm overflow-hidden rounded-2xl">
                        <CardHeader class="border-b bg-slate-50/50">
                            <CardTitle class="text-lg">Gestión de Proveedores</CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-slate-50/50 hover:bg-slate-50/50">
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Nombre</TableHead>
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Email</TableHead>
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Teléfono</TableHead>
                                        <TableHead class="w-[100px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="prov in clothProviders" :key="prov.id" class="group transition-colors">
                                        <TableCell class="font-bold text-slate-900">{{ prov.name }}</TableCell>
                                        <TableCell>{{ prov.email || '-' }}</TableCell>
                                        <TableCell>{{ prov.phone || '-' }}</TableCell>
                                        <TableCell class="flex justify-end gap-2">
                                            <Button @click="openAssignModal(prov)" variant="ghost" size="sm" class="h-8 gap-2 text-slate-400 hover:text-indigo-600">
                                                <Box class="h-4 w-4" /> <span class="text-xs font-bold uppercase">EPPs</span>
                                                <Badge variant="secondary" class="h-5 px-1.5 text-[10px]">{{ prov.epps?.length || 0 }}</Badge>
                                            </Button>
                                            <Button @click="openProviderModal(prov)" variant="ghost" size="sm" class="h-8 w-8 p-0 text-slate-400 hover:text-indigo-600">
                                                <Edit2 class="h-4 w-4" />
                                            </Button>
                                            <Button @click="deleteProvider(prov.id)" variant="ghost" size="sm" class="h-8 w-8 p-0 text-slate-400 hover:text-rose-600">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="clothProviders.length === 0">
                                        <TableCell colspan="4" class="h-32 text-center text-slate-400 italic">
                                            No hay proveedores de ropa registrados.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="epps">
                    <Card class="bg-white border-slate-200 shadow-sm overflow-hidden rounded-2xl">
                        <CardHeader class="border-b bg-slate-50/50 flex flex-row items-center justify-between">
                            <CardTitle class="text-lg">Catálogo de Elementos de Protección</CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-slate-50/50 hover:bg-slate-50/50">
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Nombre</TableHead>
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Precio Costo</TableHead>
                                        <TableHead class="font-bold text-slate-500 uppercase text-[10px]">Tallas por Ciudad</TableHead>
                                        <TableHead class="w-[100px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="epp in epps" :key="epp.id" :class="'group transition-colors ' + (epp.id % 2 === 0 ? '' : 'bg-slate-50/30')">
                                        <TableCell class="font-bold text-slate-900">{{ epp.name }}</TableCell>
                                        <TableCell>
                                            <div class="flex flex-col gap-1.5 max-w-[300px]">
                                                <div v-for="cp in epp.city_providers" :key="cp.id" class="flex items-center justify-between p-1.5 rounded-lg border border-indigo-100 bg-indigo-50/20 text-[10px]">
                                                    <span class="font-bold text-slate-700 truncate mr-2" :title="cp.provider?.name">{{ cp.provider?.name }}</span>
                                                    <div class="flex items-center gap-2 shrink-0">
                                                        <Badge variant="outline" class="h-4 border-slate-200 text-slate-500 whitespace-nowrap">{{ cp.city?.name }}</Badge>
                                                        <span class="font-black text-indigo-600 font-mono">S/.{{ Number(cp.cost_price).toFixed(2) }}</span>
                                                    </div>
                                                </div>
                                                <span v-if="!epp.city_providers || epp.city_providers.length === 0" class="text-xs text-slate-400 italic">Precios no asignados</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex flex-wrap gap-2">
                                                <Badge v-for="sz in epp.sizes" :key="sz.id" variant="outline" class="gap-1 pr-1 border-slate-200 bg-slate-50">
                                                    <span class="font-bold text-indigo-600">{{ sz.size }}</span> 
                                                    <span class="text-[10px] text-slate-400">({{ sz.city?.name }})</span>
                                                    <Button @click="deleteSize(sz.id)" variant="ghost" size="sm" class="h-4 w-4 p-0 text-slate-300 hover:text-rose-600">
                                                        <Trash2 class="h-2.5 w-2.5" />
                                                    </Button>
                                                </Badge>
                                                <span v-if="epp.sizes.length === 0" class="text-xs text-slate-400 italic">Sin tallas registradas</span>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <div class="flex justify-end gap-1">
                                                <Button @click="openPriceModal(epp)" variant="ghost" size="sm" class="h-8 gap-2 text-indigo-600 hover:bg-indigo-50">
                                                    <Truck class="h-4 w-4" /> <span class="text-xs font-bold uppercase">Precio</span>
                                                </Button>
                                                <Button @click="openSizeModal(epp)" variant="ghost" size="sm" class="h-8 gap-2 text-indigo-600 hover:bg-indigo-50">
                                                    <Plus class="h-4 w-4" /> <span class="text-xs font-bold uppercase">Talla</span>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="epps.length === 0">
                                        <TableCell colspan="3" class="h-32 text-center text-slate-400 italic">
                                            No hay EPPs registrados.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

            <!-- Provider CRUD Modal -->
            <Dialog v-model:open="isProviderModalOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{{ editingProvider ? 'Editar Proveedor' : 'Nuevo Proveedor de Prendas' }}</DialogTitle>
                        <DialogDescription>Complete los datos para el envío y facturación.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label>Nombre / Razón Social</Label>
                            <Input v-model="providerForm.name" placeholder="Ej: Textiles S.A." />
                        </div>
                        <div class="grid gap-2">
                            <Label>Email</Label>
                            <Input v-model="providerForm.email" type="email" placeholder="ventas@empresa.com" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Teléfono</Label>
                            <Input v-model="providerForm.phone" placeholder="+54 11 ..." />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" @click="isProviderModalOpen = false">Cancelar</Button>
                        <Button @click="handleProviderSubmit" class="bg-indigo-600 hover:bg-indigo-700 text-white">
                            {{ editingProvider ? 'Actualizar' : 'Registrar Proveedor' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Epp Insertion Modal -->
            <Dialog v-model:open="isEppModalOpen">
                <DialogContent class="sm:max-w-[400px]">
                    <DialogHeader>
                        <DialogTitle>Nuevo Elemento de Protección (EPP)</DialogTitle>
                        <DialogDescription>Ingrese el nombre del nuevo elemento para el inventario.</DialogDescription>
                    </DialogHeader>
                    <div class="space-y-4 py-4">
                        <div class="grid gap-2">
                            <Label>Nombre del EPP</Label>
                            <Input v-model="eppForm.name" placeholder="Ej: Guantes de Nitrilo" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" @click="isEppModalOpen = false">Cancelar</Button>
                        <Button @click="handleEppSubmit" :disabled="!eppForm.name" class="bg-slate-900 text-white hover:bg-slate-800">
                            Guardar EPP
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- EPP Assignment Modal -->
            <Dialog v-model:open="isAssignEppModalOpen">
                <DialogContent class="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Box class="h-5 w-5 text-indigo-600" />
                            Asignar EPPs a {{ assigningProvider?.name }}
                        </DialogTitle>
                        <DialogDescription>Seleccione los elementos (EPPs y Prendas) que este proveedor puede suministrar.</DialogDescription>
                    </DialogHeader>
                    <div class="py-4 max-h-[500px] overflow-y-auto pr-2 space-y-6">
                        <!-- EPPs Section -->
                        <div class="space-y-3">
                            <h3 class="text-sm font-bold text-slate-500 uppercase flex items-center gap-2 px-1">
                                <Box class="h-4 w-4" /> Catálogo de EPPs
                            </h3>
                            <div v-if="epps.length === 0" class="text-center py-4 text-slate-400 italic text-xs">
                                No hay EPPs registrados.
                            </div>
                            <div class="grid grid-cols-1 gap-2">
                                <label 
                                    v-for="epp in epps" 
                                    :key="epp.id" 
                                    class="flex items-center gap-3 p-2.5 rounded-xl border transition-all cursor-pointer hover:bg-slate-50"
                                    :class="selectedEppIds.includes(epp.id) ? 'border-indigo-600 bg-indigo-50/50' : 'border-slate-100'"
                                >
                                    <input 
                                        type="checkbox" 
                                        :value="epp.id" 
                                        v-model="selectedEppIds"
                                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600"
                                    />
                                    <span class="text-sm font-semibold text-slate-700">{{ epp.name }}</span>
                                    
                                    <!-- Prices for this specific provider -->
                                    <div class="ml-auto flex flex-col items-end gap-1">
                                        <div v-if="assigningProvider && epp.city_providers?.some((cp: any) => String(cp.cloth_provider_id) === String(assigningProvider.id))" 
                                             class="flex flex-col items-end gap-1">
                                            <div v-for="cp in epp.city_providers.filter((cp: any) => String(cp.cloth_provider_id) === String(assigningProvider.id))" 
                                                 :key="cp.id"
                                                 class="text-[10px] font-black text-indigo-700 bg-white px-2 py-0.5 rounded-md border border-indigo-200 flex items-center gap-1.5"
                                                 @dblclick.stop="startEditingPrice(cp)"
                                            >
                                                <span class="text-[8px] text-slate-400 uppercase tracking-tighter">{{ cp.city?.name }}</span>
                                                
                                                <template v-if="editingPriceId === cp.id">
                                                    <input 
                                                        v-model="tempPriceValue"
                                                        class="w-16 h-4 px-1 text-[10px] border border-indigo-500 rounded focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                                        @blur="saveInlinePrice(cp)"
                                                        @keyup.enter="saveInlinePrice(cp)"
                                                        @click.stop
                                                        v-focus
                                                    />
                                                </template>
                                                <span v-else>S/.{{ Number(cp.cost_price).toFixed(2) }}</span>
                                            </div>
                                        </div>
                                        
                                        <Button 
                                            v-if="assigningProvider"
                                            variant="ghost" 
                                            size="sm" 
                                            class="h-5 px-1.5 text-[8px] font-bold uppercase text-indigo-400 hover:text-indigo-600 hover:bg-indigo-50"
                                            @click.stop.prevent="openPriceModal(epp, assigningProvider.id)"
                                        >
                                            <Plus class="h-2.5 w-2.5 mr-1" /> Precio
                                        </Button>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Clothes Section -->
                        <div class="space-y-3">
                            <h3 class="text-sm font-bold text-slate-500 uppercase flex items-center gap-2 px-1">
                                <Shirt class="h-4 w-4" /> Catálogo de Prendas
                            </h3>
                            <div v-if="clothes.length === 0" class="text-center py-4 text-slate-400 italic text-xs">
                                No hay prendas registradas.
                            </div>
                            <div class="grid grid-cols-1 gap-2">
                                <label 
                                    v-for="cloth in clothes" 
                                    :key="cloth.id" 
                                    class="flex items-center gap-3 p-2.5 rounded-xl border transition-all cursor-pointer hover:bg-slate-50"
                                    :class="selectedClothIds.includes(cloth.id) ? 'border-indigo-600 bg-indigo-50/50' : 'border-slate-100'"
                                >
                                    <input 
                                        type="checkbox" 
                                        :value="cloth.id" 
                                        v-model="selectedClothIds"
                                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600"
                                    />
                                    <span class="text-sm font-semibold text-slate-700">{{ cloth.name }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <DialogFooter class="border-t pt-4">
                        <Button variant="ghost" @click="isAssignEppModalOpen = false">Cancelar</Button>
                        <Button @click="handleAssignmentSubmit" class="bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-200">
                            Confirmar Asignación
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- EPP Size Modal -->
            <Dialog v-model:open="isSizeModalOpen">
                <DialogContent class="sm:max-w-[400px]">
                    <DialogHeader>
                        <DialogTitle>Gestionar Tallas - {{ selectedEppForSizes?.name }}</DialogTitle>
                        <DialogDescription>Asigne una nueva talla y su ciudad correspondiente.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label>Ciudad</Label>
                            <Select v-model="sizeForm.city_id">
                                <SelectTrigger><SelectValue placeholder="Seleccionar ciudad" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="city in cities" :key="city.id" :value="String(city.id)">{{ city.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Talla / Medida</Label>
                            <Input v-model="sizeForm.size" placeholder="Ej: XL, 42, Grande" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" @click="isSizeModalOpen = false">Cerrar</Button>
                        <Button @click="handleSizeSubmit" :disabled="!sizeForm.city_id || !sizeForm.size" class="bg-indigo-600 text-white">
                            Agregar Talla
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <!-- View Invoice Details Modal -->
            <Dialog v-model:open="isViewInvoiceModalOpen">
                <DialogContent class="sm:max-w-[700px] max-h-[90vh] flex flex-col p-0 overflow-hidden">
                    <DialogHeader class="p-6 border-b bg-slate-50/50">
                        <div class="flex justify-between items-start">
                            <div>
                                <DialogTitle class="text-xl font-black text-slate-900 flex items-center gap-2">
                                    <FileText class="h-5 w-5 text-indigo-600" />
                                    Factura #{{ selectedInvoice?.invoice_number }}
                                </DialogTitle>
                                <DialogDescription class="mt-1">
                                    {{ selectedInvoice?.provider?.name }} • {{ selectedInvoice?.date }}
                                </DialogDescription>
                            </div>
                            <Badge class="bg-indigo-600 text-white border-none shadow-md">
                                S/. {{ Number(selectedInvoice?.total_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                            </Badge>
                        </div>
                    </DialogHeader>

                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        <!-- Invoice Info Cards -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 italic">
                                <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-2">Origen / Destino</p>
                                <div class="space-y-1">
                                    <p class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                        <Building class="h-3.5 w-3.5" /> {{ selectedInvoice?.business?.name }}
                                    </p>
                                    <p class="text-xs text-slate-500 ml-5">
                                        {{ selectedInvoice?.headquarter?.name || 'Distribución General' }}
                                    </p>
                                </div>
                            </div>
                            <div class="p-4 rounded-2xl bg-indigo-50/30 border border-indigo-100">
                                <p class="text-[10px] font-black uppercase text-indigo-400 tracking-widest mb-2">Notas / Observaciones</p>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    {{ selectedInvoice?.notes || 'Sin observaciones adicionales para esta factura.' }}
                                </p>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div class="space-y-3">
                            <h3 class="text-xs font-black uppercase text-slate-400 tracking-widest flex items-center gap-2">
                                <Box class="h-4 w-4" /> Detalle de Ítems
                            </h3>
                            <div class="rounded-2xl border border-slate-100 overflow-hidden">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-slate-50/80">
                                            <TableHead class="text-[10px] font-black uppercase text-slate-500">Item</TableHead>
                                            <TableHead class="text-[10px] font-black uppercase text-slate-500">Talla</TableHead>
                                            <TableHead class="text-[10px] font-black uppercase text-slate-500">Color</TableHead>
                                            <TableHead class="text-[10px] font-black uppercase text-slate-500 text-center">Cant.</TableHead>
                                            <TableHead class="text-[10px] font-black uppercase text-slate-500 text-right">P. Unit</TableHead>
                                            <TableHead class="text-[10px] font-black uppercase text-slate-500 text-right">Total</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="item in selectedInvoice?.items" :key="item.id">
                                            <TableCell class="py-2">
                                                <div class="flex items-center gap-2">
                                                    <component :is="item.cloth_id ? Shirt : Box" class="h-3.5 w-3.5 text-slate-400" />
                                                    <span class="text-xs font-bold text-slate-700">
                                                        {{ item.cloth?.name || item.epp?.name || 'N/A' }}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell class="py-2 text-xs text-slate-600">{{ item.size || '-' }}</TableCell>
                                            <TableCell class="py-2">
                                                <div v-if="item.color" class="flex items-center gap-1.5">
                                                    <div class="h-2.5 w-2.5 rounded-full border border-slate-200" :style="{ backgroundColor: item.color.hex_code }"></div>
                                                    <span class="text-[10px] text-slate-500 font-medium">{{ item.color.name }}</span>
                                                </div>
                                                <span v-else class="text-[10px] text-slate-400">-</span>
                                            </TableCell>
                                            <TableCell class="py-2 text-center text-xs font-black text-slate-700">{{ item.quantity }}</TableCell>
                                            <TableCell class="py-2 text-right text-[10px] text-slate-500 font-medium">S/.{{ Number(item.unit_price).toFixed(2) }}</TableCell>
                                            <TableCell class="py-2 text-right text-xs font-black text-indigo-600">S/.{{ Number(item.total_price).toFixed(2) }}</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <div v-if="selectedInvoice?.invoice_image" class="p-4 rounded-2xl bg-indigo-50/30 border border-indigo-100 mt-4 flex items-center justify-between">
                            <p class="text-[10px] font-black uppercase text-indigo-400 tracking-widest">Evidencia Adjunta</p>
                            <a :href="selectedInvoice.invoice_image" target="_blank" class="inline-flex items-center gap-2 text-sm font-bold text-indigo-600 hover:text-indigo-800 transition-colors">
                                <FileText class="h-4 w-4" /> Ver Documento / Imagen
                            </a>
                        </div>
                        <div v-else class="p-4 rounded-2xl bg-slate-50/30 border border-slate-100 mt-4 space-y-3">
                            <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Adjuntar Evidencia</p>
                            <p class="text-xs text-slate-500">Esta factura aún no cuenta con un documento o imagen de evidencia.</p>
                            <div class="relative w-full">
                                <Input 
                                    type="file" 
                                    @change="handleInvoiceImageUpdate" 
                                    accept="image/*,.pdf" 
                                    :disabled="isUploadingInvoiceImage"
                                    class="bg-white text-xs file:bg-indigo-50 file:border-0 file:mr-4 file:py-2 file:px-4 file:rounded-full file:text-xs file:font-bold file:text-indigo-600 hover:file:bg-indigo-100 w-full cursor-pointer h-12" 
                                />
                                <div v-if="isUploadingInvoiceImage" class="absolute inset-0 bg-white/50 backdrop-blur-sm flex items-center justify-center rounded-md font-bold text-indigo-600 gap-2 text-sm z-10">
                                    <Loader2 class="h-4 w-4 animate-spin" /> Subiendo archivo...
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <DialogFooter class="p-4 border-t bg-slate-50/50">
                        <Button variant="outline" @click="isViewInvoiceModalOpen = false" class="rounded-xl border-slate-200 font-bold uppercase text-[10px] tracking-widest h-10">
                            Cerrar Detalle
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- EPP Price Assignment Modal -->
            <Dialog v-model:open="isPriceModalOpen">
                <DialogContent class="sm:max-w-[400px]">
                    <DialogHeader>
                        <DialogTitle>Asignar Precio - {{ selectedEppForPrice?.name }}</DialogTitle>
                        <DialogDescription>Defina el costo de este EPP para un proveedor y ciudad específicos.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label>Proveedor</Label>
                            <Select v-model="priceForm.cloth_provider_id">
                                <SelectTrigger><SelectValue placeholder="Seleccionar proveedor" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="prov in clothProviders" :key="prov.id" :value="String(prov.id)">{{ prov.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Ciudad</Label>
                            <Select v-model="priceForm.city_id">
                                <SelectTrigger><SelectValue placeholder="Seleccionar ciudad" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="city in cities" :key="city.id" :value="String(city.id)">{{ city.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Precio de Costo</Label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-slate-400 text-sm">S/.</span>
                                <Input v-model="priceForm.cost_price" type="number" step="0.01" placeholder="0.00" class="pl-10" />
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" @click="isPriceModalOpen = false">Cerrar</Button>
                        <Button @click="handlePriceSubmit" :disabled="!priceForm.cloth_provider_id || !priceForm.city_id || !priceForm.cost_price" class="bg-indigo-600 text-white font-bold">
                            Asignar Precio
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
