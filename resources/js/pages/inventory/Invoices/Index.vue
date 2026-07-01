<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Box,
    Building,
    ChefHat,
    Edit2,
    ExternalLink,
    FileText,
    Filter,
    Laptop,
    Loader2,
    MoreHorizontal,
    Package,
    Plus,
    Search,
    Shirt,
    Tags,
    Trash2,
    Truck,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface EquipmentProvider {
    id: number;
    name: string;
    ruc: string | null;
    email: string | null;
    phone: string | null;
}

interface Props {
    invoices: any[];
    equipmentInvoices: any[];
    clothProviders: any[];
    equipmentProviders: EquipmentProvider[];
    businesses: any[];
    headquarters: any[];
    cafes: any[];
    clothes: any[];
    colors: any[];
    epps: any[];
    cities: any[];
    all_sizes: any[];
    epp_categories: any[];
}

const props = defineProps<Props>();

// ── Invoice type filter ────────────────────────────────────────────────────
type InvoiceCategory = 'all' | 'epp' | 'ropa' | 'equipos' | 'menaje' | 'insumos';

const invoiceTypeFilter = ref<InvoiceCategory>('all');

const CATEGORY_META: Record<string, { label: string; icon: any; cls: string }> = {
    epp: { label: 'EPPs', icon: Box, cls: 'bg-amber-100 text-amber-700 border-amber-300' },
    ropa: { label: 'Ropa', icon: Shirt, cls: 'bg-purple-100 text-purple-700 border-purple-300' },
    equipos: { label: 'Equipos Tecnológicos', icon: Laptop, cls: 'bg-blue-100 text-blue-700 border-blue-300' },
    menaje: { label: 'Menaje', icon: ChefHat, cls: 'bg-orange-100 text-orange-700 border-orange-300' },
    insumos: { label: 'Insumos', icon: Package, cls: 'bg-green-100 text-green-700 border-green-300' },
};

const allInvoices = computed(() => {
    const clothList = (props.invoices ?? []).map((inv: any) => {
        const items = inv.items ?? [];
        const hasEpp = items.some((i: any) => i.epp_id);
        const hasCloth = items.some((i: any) => i.cloth_id);
        const category = hasEpp ? 'epp' : hasCloth ? 'ropa' : 'ropa';
        return { ...inv, _category: category, _source: 'cloth' };
    });

    const equipList = (props.equipmentInvoices ?? []).map((inv: any) => {
        const hasComputer = (inv.computer_equipments ?? []).length > 0;
        const hasKitchen = (inv.kitchen_equipments ?? []).length > 0;
        const category = hasComputer ? 'equipos' : hasKitchen ? 'menaje' : 'equipos';
        return { ...inv, _category: category, _source: 'equipment' };
    });

    return [...clothList, ...equipList].sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
});

const filteredInvoices = computed(() => {
    if (invoiceTypeFilter.value === 'all') return allInvoices.value;
    return allInvoices.value.filter((inv: any) => inv._category === invoiceTypeFilter.value);
});

// --- Invoice Form Logic ---
const isInvoiceModalOpen = ref(false);
const invoiceForm = ref({
    business_id: '',
    headquarter_id: '',
    cloth_provider_id: '',
    document_type: 'factura',
    invoice_number: '',
    date: new Date().toISOString().split('T')[0],
    notes: '',
    invoice_image: null as File | null,
    items: [{ cloth_id: '', epp_id: '', color_id: '', size: '', quantity: 1, unit_price: 0 }],
});

const filteredHeadquarters = computed(() => {
    if (!invoiceForm.value.business_id) return [];
    return props.headquarters.filter((hq) => String(hq.business_id) === invoiceForm.value.business_id);
});

watch(
    () => invoiceForm.value.business_id,
    () => {
        invoiceForm.value.headquarter_id = '';
    },
);

const getAvailableClothes = (providerId: string | number) => {
    let baseList: any[] = [];
    if (!providerId) {
        baseList = [
            ...props.clothes.map((c: any) => ({ ...c, type: 'cloth', unique_id: `cloth_${c.id}` })),
            ...props.epps.map((e: any) => ({ ...e, type: 'epp', unique_id: `epp_${e.id}` })),
        ];
    } else {
        const provider = props.clothProviders.find((p) => String(p.id) === String(providerId));
        if (!provider) return [];

        baseList = [
            ...(provider.clothes || []).map((c: any) => ({ ...c, type: 'cloth', unique_id: `cloth_${c.id}` })),
            ...(provider.epps || []).map((e: any) => ({ ...e, type: 'epp', unique_id: `epp_${e.id}` })),
        ];
    }
    return baseList;
};

const onItemSelect = (uniqueId: string, index: number) => {
    const item = invoiceForm.value.items[index];
    const available = getAvailableClothes(invoiceForm.value.cloth_provider_id);
    const selected = available.find((a) => a.unique_id === uniqueId);

    if (selected) {
        if (selected.type === 'cloth') {
            item.cloth_id = String(selected.id);
            item.epp_id = '';
            item.unit_price = 0;
        } else {
            item.epp_id = String(selected.id);
            item.cloth_id = '';
            item.unit_price = 0;
        }
        item.size = ''; // Reset size on item change
    }
};

const getItemUniqueId = (item: any) => {
    if (item.cloth_id) return `cloth_${item.cloth_id}`;
    if (item.epp_id) return `epp_${item.epp_id}`;
    return '';
};

const getSizesForItem = (uniqueId: string) => {
    if (!uniqueId) return [];
    if (uniqueId.startsWith('epp_')) {
        const eppId = uniqueId.split('_')[1];
        const epp = props.epps.find((e) => String(e.id) === eppId);
        return epp?.sizes || [];
    }
    return []; // Cloth models don't have sizes relation in migration yet
};

const getAvailablePrices = (eppId: string | number, providerId: string | number) => {
    const epp = props.epps.find((e) => String(e.id) === String(eppId));
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

const totalAmount = computed(() => {
    return invoiceForm.value.items.reduce((acc, item) => acc + item.quantity * item.unit_price, 0);
});

const subtotal = computed(() => {
    if (invoiceForm.value.document_type === 'factura') {
        return totalAmount.value / 1.18;
    }
    return totalAmount.value;
});

const igv = computed(() => {
    return totalAmount.value - subtotal.value;
});

const handleInvoiceImageUpload = (e: Event) => {
    const target = e.target as HTMLInputElement;
    invoiceForm.value.invoice_image = target.files?.[0] || null;
};

const isInvoiceSubmitDisabled = computed(() => {
    return (
        !invoiceForm.value.business_id ||
        !invoiceForm.value.cloth_provider_id ||
        (filteredHeadquarters.value.length > 0 && !invoiceForm.value.headquarter_id) ||
        invoiceForm.value.items.length === 0 ||
        invoiceForm.value.items.some((i) => {
            const hasItem = i.cloth_id || i.epp_id;
            const validQty = i.quantity > 0;
            const hasSize = i.size && i.size.trim() !== '';

            // Verification for EPP
            if (i.epp_id) {
                const sizes = getSizesForItem(`epp_${i.epp_id}`);
                return !hasItem || !validQty || !hasSize || sizes.length === 0;
            }

            return !hasItem || !validQty || !hasSize;
        })
    );
});

const handleInvoiceSubmit = () => {
    if (isInvoiceSubmitDisabled.value) return;

    const submitData = {
        ...invoiceForm.value,
        headquarter_id: invoiceForm.value.headquarter_id === '' ? null : invoiceForm.value.headquarter_id,
        items: invoiceForm.value.items.map((item) => ({
            ...item,
            color_id: item.color_id === 'none' || item.color_id === '' ? null : item.color_id,
        })),
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
        preserveScroll: true,
    });
};

const resetInvoiceForm = () => {
    invoiceForm.value = {
        business_id: '',
        headquarter_id: '',
        cloth_provider_id: '',
        document_type: 'factura',
        invoice_number: '',
        date: new Date().toISOString().split('T')[0],
        notes: '',
        invoice_image: null as File | null,
        items: [{ cloth_id: '', epp_id: '', color_id: '', size: '', quantity: 1, unit_price: 0 }],
    };
};

// --- Equipment Invoice Modal ---
const isEquipmentInvoiceModalOpen = ref(false);

const emptyEquipItem = (type: 'computer' | 'kitchen') => ({
    type,
    name: '',
    brand: '',
    model: '',
    code: '',
    series: '',
    color: '',
    status: 0,
    unit_price: 0,
    quantity: 1,
});

const equipInvoiceForm = ref({
    business_id: '',
    provider_id: '',
    headquarter_id: '',
    document_type: 'factura',
    invoice_number: '',
    date: new Date().toISOString().split('T')[0],
    notes: '',
    invoice_image: null as File | null,
    items: [emptyEquipItem('computer')],
});

const filteredEquipHeadquarters = computed(() => {
    if (!equipInvoiceForm.value.business_id) return [];
    return props.headquarters.filter((hq) => String(hq.business_id) === equipInvoiceForm.value.business_id);
});

const equipItemType = computed<'computer' | 'kitchen'>(() => (invoiceTypeFilter.value === 'menaje' ? 'kitchen' : 'computer'));

function openInvoiceModal() {
    if (invoiceTypeFilter.value === 'equipos' || invoiceTypeFilter.value === 'menaje') {
        equipInvoiceForm.value = {
            business_id: '',
            provider_id: '',
            headquarter_id: '',
            document_type: 'factura',
            invoice_number: '',
            date: new Date().toISOString().split('T')[0],
            notes: '',
            invoice_image: null,
            items: [emptyEquipItem(equipItemType.value)],
        };
        isEquipmentInvoiceModalOpen.value = true;
    } else if (invoiceTypeFilter.value !== 'insumos') {
        isInvoiceModalOpen.value = true;
    }
}

function addEquipItem() {
    equipInvoiceForm.value.items.push(emptyEquipItem(equipItemType.value));
}

function removeEquipItem(index: number) {
    if (equipInvoiceForm.value.items.length > 1) {
        equipInvoiceForm.value.items.splice(index, 1);
    }
}

const equipTotal = computed(() => equipInvoiceForm.value.items.reduce((s, i) => s + i.quantity * i.unit_price, 0));

const equipSubtotal = computed(() => (equipInvoiceForm.value.document_type === 'factura' ? equipTotal.value / 1.18 : equipTotal.value));

const equipIgv = computed(() => equipTotal.value - equipSubtotal.value);

const isEquipSubmitDisabled = computed(
    () => !equipInvoiceForm.value.business_id || equipInvoiceForm.value.items.some((i) => !i.name || i.unit_price <= 0 || i.quantity < 1),
);

function handleEquipImageUpload(e: Event) {
    equipInvoiceForm.value.invoice_image = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function handleEquipInvoiceSubmit() {
    if (isEquipSubmitDisabled.value) return;
    const fd = new FormData();
    const f = equipInvoiceForm.value;
    fd.append('business_id', f.business_id);
    if (f.provider_id) fd.append('provider_id', f.provider_id);
    if (f.headquarter_id) fd.append('headquarter_id', f.headquarter_id);
    fd.append('document_type', f.document_type);
    fd.append('invoice_number', f.invoice_number);
    fd.append('date', f.date);
    fd.append('notes', f.notes);
    if (f.invoice_image) fd.append('invoice_image', f.invoice_image);
    f.items.forEach((item, i) => {
        Object.entries(item).forEach(([k, v]) => fd.append(`items[${i}][${k}]`, String(v)));
    });
    router.post(route('equipments.invoice.store'), fd, {
        forceFormData: true,
        onSuccess: () => {
            isEquipmentInvoiceModalOpen.value = false;
        },
        onError: () => {
            alert('Error al guardar. Revise los campos requeridos.');
        },
        preserveScroll: true,
    });
}

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
            phone: provider.phone || '',
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
            onSuccess: () => (isProviderModalOpen.value = false),
        });
    } else {
        router.post(route('inventory.providers.store'), providerForm.value, {
            onSuccess: () => (isProviderModalOpen.value = false),
        });
    }
};

const deleteProvider = (id: number) => {
    if (confirm('¿Estás seguro de eliminar este proveedor?')) {
        router.delete(route('inventory.providers.destroy', id));
    }
};

// --- Equipment Provider CRUD Logic ---
const isEqProviderModalOpen = ref(false);
const editingEqProvider = ref<EquipmentProvider | null>(null);
const eqProviderForm = ref({ name: '', ruc: '', email: '', phone: '' });

const openEqProviderModal = (provider: EquipmentProvider | null = null) => {
    editingEqProvider.value = provider;
    eqProviderForm.value = provider
        ? { name: provider.name, ruc: provider.ruc ?? '', email: provider.email ?? '', phone: provider.phone ?? '' }
        : { name: '', ruc: '', email: '', phone: '' };
    isEqProviderModalOpen.value = true;
};

const handleEqProviderSubmit = () => {
    if (editingEqProvider.value) {
        router.put(route('equipments.providers.update', editingEqProvider.value.id), eqProviderForm.value, {
            preserveScroll: true,
            onSuccess: () => (isEqProviderModalOpen.value = false),
        });
    } else {
        router.post(route('equipments.providers.store'), eqProviderForm.value, {
            preserveScroll: true,
            onSuccess: () => (isEqProviderModalOpen.value = false),
        });
    }
};

const deleteEqProvider = (id: number) => {
    if (confirm('¿Estás seguro de eliminar este proveedor de equipos?')) {
        router.delete(route('equipments.providers.destroy', id), { preserveScroll: true });
    }
};

const isEppModalOpen = ref(false);
const eppForm = ref({
    name: '',
    category_epp_id: 'none',
    size_ids: [] as number[],
});

const handleEppSubmit = () => {
    const submitData = {
        ...eppForm.value,
        category_epp_id: eppForm.value.category_epp_id === 'none' ? null : eppForm.value.category_epp_id,
    };

    router.post(route('inventory.epps.store'), submitData, {
        onSuccess: () => {
            isEppModalOpen.value = false;
            eppForm.value.name = '';
            eppForm.value.category_epp_id = 'none';
            eppForm.value.size_ids = [];
        },
    });
};

// --- EPP Category Logic ---
const isCategoryModalOpen = ref(false);
const categoryForm = ref({ name: '' });

const handleCategorySubmit = () => {
    router.post(route('inventory.epp-categories.store'), categoryForm.value, {
        onSuccess: () => {
            isCategoryModalOpen.value = false;
            categoryForm.value.name = '';
        },
    });
};

// --- EPP Price Logic ---
const isPriceModalOpen = ref(false);
const selectedEppForPrice = ref<any>(null);
const priceForm = ref({
    epp_id: '',
    cloth_provider_id: '',
    city_id: '',
    cost_price: '',
});

const openPriceModal = (epp: any, providerId: string | number | null = null) => {
    selectedEppForPrice.value = epp;
    priceForm.value = {
        epp_id: String(epp.id),
        cloth_provider_id: providerId ? String(providerId) : '',
        city_id: '',
        cost_price: '',
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
        },
    });
};

// --- Assignment Logic ---
const isAssignEppModalOpen = ref(false);
const assigningProvider = ref<any>(null);
const selectedEppIds = ref<number[]>([]);
const selectedClothIds = ref<number[]>([]);
const eppAssignmentSearch = ref('');

const filteredEppsForAssignment = computed(() => {
    if (!eppAssignmentSearch.value) return props.epps;
    const s = eppAssignmentSearch.value.toLowerCase();
    return props.epps.filter((e) => e.name.toLowerCase().includes(s));
});

const openAssignModal = (provider: any) => {
    assigningProvider.value = provider;
    eppAssignmentSearch.value = ''; // Reset search

    // Get unique IDs from both the pivot relationship and the city_providers price table
    const pivotIds = provider.epps.map((e: any) => e.id);
    const ternaryIds = props.epps
        .filter((epp) => epp.city_providers?.some((cp: any) => String(cp.cloth_provider_id) === String(provider.id)))
        .map((epp) => epp.id);

    selectedEppIds.value = [...new Set([...pivotIds, ...ternaryIds])];
    selectedClothIds.value = provider.clothes?.map((c: any) => c.id) || [];
    isAssignEppModalOpen.value = true;
};

const handleAssignmentSubmit = () => {
    router.post(
        route('inventory.providers.epps.sync', assigningProvider.value.id),
        {
            epp_ids: selectedEppIds.value,
            cloth_ids: selectedClothIds.value,
        },
        {
            onSuccess: () => (isAssignEppModalOpen.value = false),
        },
    );
};

// --- View Equipment Invoice Details ---
const isViewEquipInvoiceModalOpen = ref(false);
const selectedEquipInvoice = ref<any>(null);

const viewEquipInvoiceDetails = (invoice: any) => {
    selectedEquipInvoice.value = invoice;
    isViewEquipInvoiceModalOpen.value = true;
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
    router.post(
        route('inventory.invoice.image.update', selectedInvoice.value.id),
        {
            invoice_image: file,
        },
        {
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
            },
        },
    );
};

// --- EPP Size Logic ---
const isSizeModalOpen = ref(false);
const selectedEppForSizes = ref<any>(null);
const sizeForm = ref({
    epp_id: '',
    size: '',
});

const openSizeModal = (epp: any) => {
    selectedEppForSizes.value = epp;
    sizeForm.value = {
        epp_id: epp.id,
        size: '',
    };
    isSizeModalOpen.value = true;
};

const handleSizeSubmit = () => {
    router.post(route('inventory.epp-sizes.store'), sizeForm.value, {
        onSuccess: () => {
            sizeForm.value.size = '';
            // Update the local list if needed or let Inertia reload
        },
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
        router.post(
            route('inventory.epps.assign-price'),
            {
                epp_id: cp.epp_id,
                cloth_provider_id: cp.cloth_provider_id,
                city_id: cp.city_id,
                cost_price: newPrice,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    editingPriceId.value = null;
                },
            },
        );
    } else {
        editingPriceId.value = null;
    }
};
</script>

<template>
    <Head title="Facturas y Proveedores" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Logística', href: route('logistics') },
            { title: 'Inventario', href: route('inventory.index') },
            { title: 'Facturas y Proveedores', href: route('inventory.invoices.index') },
        ]"
    >
        <div class="flex h-full w-full flex-col gap-6 bg-slate-50/50 p-4 sm:p-6">
            <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <h1 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        <FileText class="h-8 w-8 text-indigo-600" />
                        Facturas y Proveedores de Prendas
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">Historial de ingresos y gestión de proveedores especializados.</p>
                </div>
                <div class="flex gap-2">
                    <Button @click="isEppModalOpen = true" variant="outline" class="gap-2 border-slate-200 bg-white">
                        <Box class="h-4 w-4" /> Nuevo EPP
                    </Button>
                </div>
            </div>

            <Tabs defaultValue="invoices" class="w-full">
                <TabsList class="mb-4 border bg-white">
                    <TabsTrigger value="invoices" class="gap-2"> <FileText class="h-4 w-4" /> Facturas Recientes </TabsTrigger>
                    <TabsTrigger value="providers" class="gap-2"> <Truck class="h-4 w-4" /> Proveedores de Ropa </TabsTrigger>
                    <TabsTrigger value="epps" class="gap-2"> <Box class="h-4 w-4" /> Catálogo de EPP </TabsTrigger>
                    <TabsTrigger value="equipment-providers" class="gap-2"> <Building class="h-4 w-4" /> Proveedores de Equipos </TabsTrigger>
                </TabsList>

                <TabsContent value="invoices">
                    <Card class="overflow-hidden rounded-2xl border-slate-200 bg-white shadow-sm">
                        <CardHeader class="border-b bg-slate-50/50">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <CardTitle class="text-lg">Historial de Facturación</CardTitle>
                                <div class="flex items-center gap-2">
                                    <Filter class="h-4 w-4 text-slate-400" />
                                    <Select v-model="invoiceTypeFilter">
                                        <SelectTrigger class="h-8 w-52 text-sm">
                                            <SelectValue placeholder="Todos los tipos" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Todos los tipos</SelectItem>
                                            <SelectItem value="epp">
                                                <span class="flex items-center gap-1.5"><Box class="h-3.5 w-3.5 text-amber-600" /> EPPs</span>
                                            </SelectItem>
                                            <SelectItem value="ropa">
                                                <span class="flex items-center gap-1.5"><Shirt class="h-3.5 w-3.5 text-purple-600" /> Ropa</span>
                                            </SelectItem>
                                            <SelectItem value="equipos">
                                                <span class="flex items-center gap-1.5"
                                                    ><Laptop class="h-3.5 w-3.5 text-blue-600" /> Equipos Tecnológicos</span
                                                >
                                            </SelectItem>
                                            <SelectItem value="menaje">
                                                <span class="flex items-center gap-1.5"><ChefHat class="h-3.5 w-3.5 text-orange-600" /> Menaje</span>
                                            </SelectItem>
                                            <SelectItem value="insumos">
                                                <span class="flex items-center gap-1.5"><Package class="h-3.5 w-3.5 text-green-600" /> Insumos</span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <span class="text-muted-foreground text-xs">
                                        {{ filteredInvoices.length }} resultado{{ filteredInvoices.length !== 1 ? 's' : '' }}
                                    </span>
                                    <!-- Dynamic invoice button -->
                                    <Button
                                        @click="openInvoiceModal"
                                        :disabled="invoiceTypeFilter === 'insumos'"
                                        :class="[
                                            'gap-2 shadow-lg transition-colors',
                                            invoiceTypeFilter === 'equipos'
                                                ? 'bg-blue-600 shadow-blue-200 hover:bg-blue-700'
                                                : invoiceTypeFilter === 'menaje'
                                                  ? 'bg-orange-600 shadow-orange-200 hover:bg-orange-700'
                                                  : invoiceTypeFilter === 'insumos'
                                                    ? 'cursor-not-allowed bg-slate-400 shadow-slate-200'
                                                    : 'bg-indigo-600 shadow-indigo-200 hover:bg-indigo-700',
                                        ]"
                                    >
                                        <Plus class="h-4 w-4" />
                                        <span v-if="invoiceTypeFilter === 'equipos'">Factura Equipos</span>
                                        <span v-else-if="invoiceTypeFilter === 'menaje'">Factura Menaje</span>
                                        <span v-else-if="invoiceTypeFilter === 'insumos'">Insumos (próx.)</span>
                                        <span v-else>Ingresar Factura</span>
                                    </Button>

                                    <!-- ClothInvoice Dialog (EPPs / Ropa) -->
                                    <Dialog v-model:open="isInvoiceModalOpen">
                                        <DialogContent class="max-h-[100vh] overflow-y-auto sm:max-w-[1400px]">
                                            <DialogHeader>
                                                <DialogTitle class="flex items-center gap-2">
                                                    <FileText class="h-5 w-5 text-indigo-600" />
                                                    Nueva Factura de Stock
                                                </DialogTitle>
                                                <DialogDescription
                                                    >Registre el ingreso de prendas detallando proveedores, costos y colores.</DialogDescription
                                                >
                                            </DialogHeader>

                                            <div class="grid gap-6 py-4">
                                                <div
                                                    class="grid grid-cols-1 gap-4 rounded-2xl border border-slate-100 bg-slate-50 p-4 md:grid-cols-3"
                                                >
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase">Empresa (Business)</Label>
                                                        <Select v-model="invoiceForm.business_id">
                                                            <SelectTrigger class="bg-white"><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem v-for="b in businesses" :key="b.id" :value="String(b.id)">{{
                                                                    b.name
                                                                }}</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase">Sede (Correspondiente)</Label>
                                                        <Select v-model="invoiceForm.headquarter_id" :disabled="!invoiceForm.business_id">
                                                            <SelectTrigger class="bg-white"><SelectValue placeholder="Elegir Sede" /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem v-for="hq in filteredHeadquarters" :key="hq.id" :value="String(hq.id)">{{
                                                                    hq.name
                                                                }}</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase">Proveedor</Label>
                                                        <Select v-model="invoiceForm.cloth_provider_id">
                                                            <SelectTrigger class="bg-white"><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem v-for="p in clothProviders" :key="p.id" :value="String(p.id)">{{
                                                                    p.name
                                                                }}</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase">Tipo de Documento</Label>
                                                        <Select v-model="invoiceForm.document_type">
                                                            <SelectTrigger class="bg-white"><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="factura">Factura (Con IGV)</SelectItem>
                                                                <SelectItem value="boleta">Boleta (Sin IGV)</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase">Nº Documento</Label>
                                                        <Input v-model="invoiceForm.invoice_number" placeholder="Ej: F-001-123" class="bg-white" />
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase">Fecha</Label>
                                                        <Input v-model="invoiceForm.date" type="date" class="bg-white" />
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase">Notas Adicionales</Label>
                                                        <Input v-model="invoiceForm.notes" placeholder="..." class="bg-white" />
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase">Evidencia (Opcional)</Label>
                                                        <Input
                                                            type="file"
                                                            @change="handleInvoiceImageUpload"
                                                            accept="image/*,.pdf"
                                                            class="bg-white text-xs file:mr-4 file:rounded-full file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-xs file:font-semibold hover:file:bg-slate-200"
                                                        />
                                                    </div>
                                                </div>

                                                <div class="space-y-4">
                                                    <div class="flex items-center justify-between px-1">
                                                        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                                            <Shirt class="h-4 w-4 text-indigo-500" />
                                                            Prendas Incluidas
                                                        </h3>
                                                        <Button
                                                            @click="addInvoiceItem"
                                                            size="sm"
                                                            variant="outline"
                                                            class="h-8 gap-1.5 border-indigo-200 text-xs font-bold text-indigo-600 hover:bg-indigo-50"
                                                        >
                                                            <Plus class="h-3.5 w-3.5" /> Agregar Fila
                                                        </Button>
                                                    </div>

                                                    <div class="overflow-hidden rounded-2xl border shadow-sm">
                                                        <table class="w-full text-sm">
                                                            <thead class="border-b bg-slate-50">
                                                                <tr class="text-left text-[10px] font-black text-slate-400 uppercase">
                                                                    <th class="px-4 py-3">Item (Prenda/EPP)</th>
                                                                    <th class="px-4 py-3">Talla</th>
                                                                    <th class="px-4 py-3">Color</th>
                                                                    <th class="w-24 px-4 py-3">Cant.</th>
                                                                    <th class="w-24 px-4 py-3">P. Unitario</th>
                                                                    <th class="w-24 px-4 py-3 text-right">Subtotal</th>
                                                                    <th
                                                                        v-if="invoiceForm.document_type === 'factura'"
                                                                        class="w-24 px-4 py-3 text-right"
                                                                    >
                                                                        IGV (18%)
                                                                    </th>
                                                                    <th class="w-28 px-4 py-3 text-right">Total</th>
                                                                    <th class="w-10 px-4 py-3 text-center"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y">
                                                                <tr
                                                                    v-for="(item, index) in invoiceForm.items"
                                                                    :key="index"
                                                                    class="group transition-colors hover:bg-slate-50/50"
                                                                >
                                                                    <td class="p-3">
                                                                        <Select
                                                                            :model-value="getItemUniqueId(item)"
                                                                            @update:model-value="onItemSelect($event as string, index)"
                                                                        >
                                                                            <SelectTrigger
                                                                                class="h-9 w-[200px] justify-start border-none shadow-none focus:ring-1"
                                                                            >
                                                                                <SelectValue placeholder="Elegir..." class="truncate" />
                                                                            </SelectTrigger>

                                                                            <SelectContent>
                                                                                <SelectItem
                                                                                    v-for="c in getAvailableClothes(invoiceForm.cloth_provider_id)"
                                                                                    :key="c.unique_id"
                                                                                    :value="c.unique_id"
                                                                                >
                                                                                    <div class="flex items-center gap-2">
                                                                                        <component
                                                                                            :is="c.type === 'cloth' ? Shirt : Box"
                                                                                            class="h-3.5 w-3.5 text-slate-400"
                                                                                        />
                                                                                        <span class="truncate">{{ c.name }}</span>
                                                                                    </div>
                                                                                </SelectItem>
                                                                            </SelectContent>
                                                                        </Select>
                                                                    </td>
                                                                    <td class="p-3">
                                                                        <div v-if="getSizesForItem(getItemUniqueId(item)).length > 0">
                                                                            <Select v-model="item.size">
                                                                                <SelectTrigger class="h-9 border-none shadow-none focus:ring-1"
                                                                                    ><SelectValue placeholder="Talla"
                                                                                /></SelectTrigger>
                                                                                <SelectContent>
                                                                                    <SelectItem
                                                                                        v-for="s in getSizesForItem(getItemUniqueId(item))"
                                                                                        :key="s.id"
                                                                                        :value="s.size"
                                                                                    >
                                                                                        {{ s.size }}
                                                                                    </SelectItem>
                                                                                </SelectContent>
                                                                            </Select>
                                                                        </div>
                                                                        <div
                                                                            v-else-if="item.epp_id"
                                                                            class="flex flex-col items-center justify-center rounded-lg border border-rose-100 bg-rose-50 p-1"
                                                                        >
                                                                            <span
                                                                                class="mb-1 text-center text-[8px] leading-tight font-black text-rose-600 uppercase"
                                                                                >Requiere<br />asignar tallas</span
                                                                            >
                                                                            <Button
                                                                                @click="
                                                                                    openSizeModal(
                                                                                        props.epps.find((e) => String(e.id) === String(item.epp_id)),
                                                                                    )
                                                                                "
                                                                                variant="ghost"
                                                                                size="sm"
                                                                                class="h-5 p-0 text-[8px] font-bold text-indigo-600 hover:bg-transparent hover:text-indigo-700"
                                                                            >
                                                                                Configurar
                                                                            </Button>
                                                                        </div>
                                                                        <Input
                                                                            v-else
                                                                            v-model="item.size"
                                                                            placeholder="Talla..."
                                                                            class="h-9 border-none shadow-none focus:ring-1"
                                                                        />
                                                                    </td>
                                                                    <td class="p-3">
                                                                        <Select v-model="item.color_id">
                                                                            <SelectTrigger class="h-9 border-none shadow-none focus:ring-1"
                                                                                ><SelectValue placeholder="Ninguno"
                                                                            /></SelectTrigger>
                                                                            <SelectContent>
                                                                                <SelectItem value="none">Ninguno</SelectItem>
                                                                                <SelectItem
                                                                                    v-for="color in colors"
                                                                                    :key="color.id"
                                                                                    :value="String(color.id)"
                                                                                >
                                                                                    <div class="flex items-center gap-2">
                                                                                        <div
                                                                                            class="h-3 w-3 rounded-full border border-slate-200"
                                                                                            :style="{ backgroundColor: color.hex_code }"
                                                                                        ></div>
                                                                                        {{ color.name }}
                                                                                    </div>
                                                                                </SelectItem>
                                                                            </SelectContent>
                                                                        </Select>
                                                                    </td>
                                                                    <td class="p-3">
                                                                        <Input
                                                                            type="number"
                                                                            v-model="item.quantity"
                                                                            min="1"
                                                                            class="h-9 border-none text-center font-bold shadow-none focus:ring-1"
                                                                        />
                                                                    </td>
                                                                    <td class="p-3">
                                                                        <div
                                                                            v-if="
                                                                                item.epp_id &&
                                                                                getAvailablePrices(item.epp_id, invoiceForm.cloth_provider_id)
                                                                                    .length > 0
                                                                            "
                                                                            class="flex flex-col gap-1"
                                                                        >
                                                                            <Select v-model="item.unit_price">
                                                                                <SelectTrigger
                                                                                    class="h-9 border-none text-xs font-bold text-indigo-600 shadow-none focus:ring-1"
                                                                                >
                                                                                    <SelectValue placeholder="Elegir Precio" />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    <SelectItem
                                                                                        v-for="cp in getAvailablePrices(
                                                                                            item.epp_id,
                                                                                            invoiceForm.cloth_provider_id,
                                                                                        )"
                                                                                        :key="cp.id"
                                                                                        :value="Number(cp.cost_price)"
                                                                                    >
                                                                                        S/.{{ Number(cp.cost_price).toFixed(2) }} ({{
                                                                                            cp.city?.name
                                                                                        }})
                                                                                    </SelectItem>
                                                                                </SelectContent>
                                                                            </Select>
                                                                        </div>
                                                                        <div v-else class="relative">
                                                                            <span class="absolute top-2 left-2 text-xs text-slate-400">S/.</span>
                                                                            <Input
                                                                                type="number"
                                                                                v-model="item.unit_price"
                                                                                step="0.01"
                                                                                class="h-9 border-none pl-6 font-bold text-indigo-600 shadow-none focus:ring-1"
                                                                            />
                                                                        </div>
                                                                    </td>
                                                                    <!-- Subtotal Item (Base Imponible si es factura) -->
                                                                    <td class="p-3 text-right text-xs font-medium text-slate-600">
                                                                        S/.{{
                                                                            (invoiceForm.document_type === 'factura'
                                                                                ? (item.quantity * item.unit_price) / 1.18
                                                                                : item.quantity * item.unit_price
                                                                            ).toLocaleString(undefined, { minimumFractionDigits: 2 })
                                                                        }}
                                                                    </td>
                                                                    <!-- IGV Item (Desglosado si es factura) -->
                                                                    <td
                                                                        v-if="invoiceForm.document_type === 'factura'"
                                                                        class="p-3 text-right text-xs font-medium text-indigo-500"
                                                                    >
                                                                        S/.{{
                                                                            (
                                                                                item.quantity * item.unit_price -
                                                                                (item.quantity * item.unit_price) / 1.18
                                                                            ).toLocaleString(undefined, { minimumFractionDigits: 2 })
                                                                        }}
                                                                    </td>
                                                                    <!-- Total Item (Precio real ingresado) -->
                                                                    <td class="p-3 text-right text-xs font-bold text-slate-900">
                                                                        S/.{{
                                                                            (item.quantity * item.unit_price).toLocaleString(undefined, {
                                                                                minimumFractionDigits: 2,
                                                                            })
                                                                        }}
                                                                    </td>
                                                                    <td class="p-3 text-center">
                                                                        <Button
                                                                            @click="removeInvoiceItem(index)"
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            class="h-8 w-8 rounded-full p-0 text-slate-400 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-rose-50 hover:text-rose-600"
                                                                        >
                                                                            <Trash2 class="h-4 w-4" />
                                                                        </Button>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                            <tfoot class="border-t border-slate-200 bg-slate-50">
                                                                <tr>
                                                                    <td
                                                                        :colspan="invoiceForm.document_type === 'factura' ? 6 : 5"
                                                                        rowspan="3"
                                                                        class="px-4 py-3 align-top"
                                                                    >
                                                                        <div
                                                                            class="rounded-xl border border-dashed border-slate-200 bg-white p-3 text-[10px] text-slate-400"
                                                                        >
                                                                            <p class="mb-1 font-bold tracking-widest uppercase">Notas de Cálculo:</p>
                                                                            <p v-if="invoiceForm.document_type === 'factura'">
                                                                                • Los precios ingresados ya **incluyen** IGV.
                                                                            </p>
                                                                            <p v-if="invoiceForm.document_type === 'factura'">
                                                                                • Se extrae el 18% para mostrar la base imponible.
                                                                            </p>
                                                                            <p v-else>
                                                                                • Las boletas no desglosan impuestos; el total es el monto bruto.
                                                                            </p>
                                                                        </div>
                                                                    </td>
                                                                    <td
                                                                        class="px-4 py-2 text-right text-[11px] font-bold tracking-wider text-slate-500 uppercase"
                                                                    >
                                                                        Subtotal Gravado
                                                                    </td>
                                                                    <td class="px-4 py-2 text-right font-mono font-bold text-slate-700">
                                                                        S/.{{ subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                                                                    </td>
                                                                    <td></td>
                                                                </tr>
                                                                <tr>
                                                                    <td
                                                                        class="px-4 py-2 text-right text-[11px] font-bold tracking-wider text-slate-500 uppercase"
                                                                    >
                                                                        IGV (18%)
                                                                    </td>
                                                                    <td class="px-4 py-2 text-right font-mono font-bold text-slate-600">
                                                                        S/.{{ igv.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                                                                    </td>
                                                                    <td></td>
                                                                </tr>
                                                                <tr class="bg-indigo-50/50">
                                                                    <td
                                                                        class="px-4 py-3 text-right text-[12px] font-black tracking-widest text-indigo-900 uppercase"
                                                                    >
                                                                        Total Factura
                                                                    </td>
                                                                    <td class="px-4 py-3 text-right">
                                                                        <span class="font-mono text-xl font-black text-indigo-700">
                                                                            S/.{{
                                                                                totalAmount.toLocaleString(undefined, { minimumFractionDigits: 2 })
                                                                            }}
                                                                        </span>
                                                                    </td>
                                                                    <td></td>
                                                                </tr>
                                                            </tfoot>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>

                                            <DialogFooter class="-mx-6 mt-4 -mb-6 rounded-b-lg border-t bg-slate-50 p-6">
                                                <Button variant="ghost" @click="isInvoiceModalOpen = false" class="font-bold">Cancelar</Button>
                                                <Button
                                                    @click="handleInvoiceSubmit"
                                                    :disabled="isInvoiceSubmitDisabled"
                                                    class="bg-indigo-600 font-bold text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700"
                                                >
                                                    Guardar e Ingresar Stock
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>

                                    <!-- EquipmentInvoice Dialog (Equipos / Menaje) -->
                                    <Dialog v-model:open="isEquipmentInvoiceModalOpen">
                                        <DialogContent class="max-h-[100vh] overflow-y-auto sm:max-w-[1300px]">
                                            <DialogHeader>
                                                <DialogTitle class="flex items-center gap-2">
                                                    <component
                                                        :is="equipItemType === 'kitchen' ? ChefHat : Laptop"
                                                        class="h-5 w-5"
                                                        :class="equipItemType === 'kitchen' ? 'text-orange-600' : 'text-blue-600'"
                                                    />
                                                    Nueva Factura de {{ equipItemType === 'kitchen' ? 'Menaje' : 'Equipos Tecnológicos' }}
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Registre el ingreso de equipos detallando proveedor, costos e identificadores.
                                                </DialogDescription>
                                            </DialogHeader>

                                            <div class="grid gap-6 py-4">
                                                <!-- Header fields -->
                                                <div
                                                    class="grid grid-cols-1 gap-4 rounded-2xl border border-slate-100 bg-slate-50 p-4 md:grid-cols-3"
                                                >
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase"
                                                            >Empresa <span class="text-red-500">*</span></Label
                                                        >
                                                        <Select
                                                            v-model="equipInvoiceForm.business_id"
                                                            @update:model-value="equipInvoiceForm.headquarter_id = ''"
                                                        >
                                                            <SelectTrigger class="bg-white"><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem v-for="b in businesses" :key="b.id" :value="String(b.id)">{{
                                                                    b.name
                                                                }}</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase"
                                                            >Sede / Almacén <span class="text-red-500">*</span></Label
                                                        >
                                                        <Select v-model="equipInvoiceForm.headquarter_id" :disabled="!equipInvoiceForm.business_id">
                                                            <SelectTrigger class="bg-white"
                                                                ><SelectValue placeholder="Seleccionar sede"
                                                            /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem
                                                                    v-for="hq in filteredEquipHeadquarters"
                                                                    :key="hq.id"
                                                                    :value="String(hq.id)"
                                                                    >{{ hq.name }}</SelectItem
                                                                >
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase">Proveedor de Equipos</Label>
                                                        <Select v-model="equipInvoiceForm.provider_id">
                                                            <SelectTrigger class="bg-white"><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem v-for="p in equipmentProviders" :key="p.id" :value="String(p.id)">{{
                                                                    p.name
                                                                }}</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase">Tipo de Documento</Label>
                                                        <Select v-model="equipInvoiceForm.document_type">
                                                            <SelectTrigger class="bg-white"><SelectValue /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="factura">Factura (Con IGV)</SelectItem>
                                                                <SelectItem value="boleta">Boleta (Sin IGV)</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase">Nº Documento</Label>
                                                        <Input
                                                            v-model="equipInvoiceForm.invoice_number"
                                                            placeholder="Ej: F-001-0012"
                                                            class="bg-white"
                                                        />
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase"
                                                            >Fecha <span class="text-red-500">*</span></Label
                                                        >
                                                        <Input v-model="equipInvoiceForm.date" type="date" class="bg-white" />
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase">Notas</Label>
                                                        <Input v-model="equipInvoiceForm.notes" placeholder="..." class="bg-white" />
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-bold text-slate-500 uppercase">Evidencia (Opcional)</Label>
                                                        <Input
                                                            type="file"
                                                            @change="handleEquipImageUpload"
                                                            accept="image/*,.pdf"
                                                            class="bg-white text-xs file:mr-4 file:rounded-full file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-xs file:font-semibold hover:file:bg-slate-200"
                                                        />
                                                    </div>
                                                </div>

                                                <!-- Items table -->
                                                <div class="space-y-3">
                                                    <div class="flex items-center justify-between px-1">
                                                        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                                            <component
                                                                :is="equipItemType === 'kitchen' ? ChefHat : Laptop"
                                                                class="h-4 w-4"
                                                                :class="equipItemType === 'kitchen' ? 'text-orange-500' : 'text-blue-500'"
                                                            />
                                                            Equipos Incluidos
                                                        </h3>
                                                        <Button
                                                            @click="addEquipItem"
                                                            size="sm"
                                                            variant="outline"
                                                            class="h-8 gap-1.5 border-blue-200 text-xs font-bold text-blue-600 hover:bg-blue-50"
                                                        >
                                                            <Plus class="h-3.5 w-3.5" /> Agregar Fila
                                                        </Button>
                                                    </div>

                                                    <div class="overflow-hidden rounded-2xl border shadow-sm">
                                                        <table class="w-full text-sm">
                                                            <thead class="border-b bg-slate-50">
                                                                <tr class="text-left text-[10px] font-black text-slate-400 uppercase">
                                                                    <th class="px-3 py-3">Tipo</th>
                                                                    <th class="px-3 py-3">Nombre <span class="text-red-500">*</span></th>
                                                                    <th class="px-3 py-3">Marca</th>
                                                                    <th class="px-3 py-3">Modelo</th>
                                                                    <th class="px-3 py-3">Código</th>
                                                                    <th class="px-3 py-3">Serie</th>
                                                                    <th class="px-3 py-3">Color</th>
                                                                    <th class="px-3 py-3">Estado</th>
                                                                    <th class="w-28 px-3 py-3">P. Unit. <span class="text-red-500">*</span></th>
                                                                    <th class="w-24 px-3 py-3">Cant. <span class="text-red-500">*</span></th>
                                                                    <th class="w-28 px-3 py-3 text-right">Total</th>
                                                                    <th class="w-10 px-3 py-3"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y">
                                                                <tr
                                                                    v-for="(item, idx) in equipInvoiceForm.items"
                                                                    :key="idx"
                                                                    class="group transition-colors hover:bg-slate-50/50"
                                                                >
                                                                    <td class="p-2">
                                                                        <Select v-model="item.type">
                                                                            <SelectTrigger
                                                                                class="h-8 w-28 border-none text-xs shadow-none focus:ring-1"
                                                                            >
                                                                                <SelectValue />
                                                                            </SelectTrigger>
                                                                            <SelectContent>
                                                                                <SelectItem value="computer">
                                                                                    <span class="flex items-center gap-1"
                                                                                        ><Laptop class="h-3 w-3 text-blue-500" /> IT</span
                                                                                    >
                                                                                </SelectItem>
                                                                                <SelectItem value="kitchen">
                                                                                    <span class="flex items-center gap-1"
                                                                                        ><ChefHat class="h-3 w-3 text-orange-500" /> Menaje</span
                                                                                    >
                                                                                </SelectItem>
                                                                            </SelectContent>
                                                                        </Select>
                                                                    </td>
                                                                    <td class="p-2">
                                                                        <Input
                                                                            v-model="item.name"
                                                                            placeholder="Nombre*"
                                                                            class="h-8 min-w-[120px] border-none shadow-none focus:ring-1"
                                                                        />
                                                                    </td>
                                                                    <td class="p-2">
                                                                        <Input
                                                                            v-model="item.brand"
                                                                            placeholder="Marca"
                                                                            class="h-8 w-24 border-none shadow-none focus:ring-1"
                                                                        />
                                                                    </td>
                                                                    <td class="p-2">
                                                                        <Input
                                                                            v-model="item.model"
                                                                            placeholder="Modelo"
                                                                            class="h-8 w-24 border-none shadow-none focus:ring-1"
                                                                        />
                                                                    </td>
                                                                    <td class="p-2">
                                                                        <Input
                                                                            v-model="item.code"
                                                                            placeholder="Código"
                                                                            class="h-8 w-24 border-none shadow-none focus:ring-1"
                                                                        />
                                                                    </td>
                                                                    <td class="p-2">
                                                                        <Input
                                                                            v-model="item.series"
                                                                            placeholder="Serie"
                                                                            class="h-8 w-24 border-none shadow-none focus:ring-1"
                                                                        />
                                                                    </td>
                                                                    <td class="p-2">
                                                                        <Input
                                                                            v-model="item.color"
                                                                            placeholder="Color"
                                                                            class="h-8 w-20 border-none shadow-none focus:ring-1"
                                                                        />
                                                                    </td>
                                                                    <td class="p-2">
                                                                        <Select v-model="item.status">
                                                                            <SelectTrigger
                                                                                class="h-8 w-24 border-none text-xs shadow-none focus:ring-1"
                                                                                ><SelectValue
                                                                            /></SelectTrigger>
                                                                            <SelectContent>
                                                                                <SelectItem :value="0">Nuevo</SelectItem>
                                                                                <SelectItem :value="1">Bueno</SelectItem>
                                                                                <SelectItem :value="2">Regular</SelectItem>
                                                                                <SelectItem :value="3">Malo</SelectItem>
                                                                            </SelectContent>
                                                                        </Select>
                                                                    </td>
                                                                    <td class="p-2">
                                                                        <div class="relative">
                                                                            <span class="absolute top-2 left-2.5 text-xs text-slate-400 select-none"
                                                                                >S/.</span
                                                                            >
                                                                            <Input
                                                                                type="number"
                                                                                v-model="item.unit_price"
                                                                                step="0.01"
                                                                                min="0"
                                                                                placeholder="0.00"
                                                                                class="h-9 w-28 rounded-md border border-slate-200 bg-white pl-8 font-bold text-indigo-600 shadow-none focus:ring-1"
                                                                            />
                                                                        </div>
                                                                    </td>
                                                                    <td class="p-2">
                                                                        <Input
                                                                            type="number"
                                                                            v-model="item.quantity"
                                                                            min="1"
                                                                            placeholder="1"
                                                                            class="h-9 w-20 rounded-md border border-slate-200 bg-white text-center font-bold shadow-none focus:ring-1"
                                                                        />
                                                                    </td>
                                                                    <td class="p-2 text-right text-xs font-bold text-slate-900">
                                                                        S/.{{
                                                                            (item.quantity * item.unit_price).toLocaleString(undefined, {
                                                                                minimumFractionDigits: 2,
                                                                            })
                                                                        }}
                                                                    </td>
                                                                    <td class="p-2 text-center">
                                                                        <Button
                                                                            @click="removeEquipItem(idx)"
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            class="h-7 w-7 rounded-full p-0 text-slate-400 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-rose-50 hover:text-rose-600"
                                                                        >
                                                                            <Trash2 class="h-3.5 w-3.5" />
                                                                        </Button>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                            <tfoot class="border-t border-slate-200 bg-slate-50">
                                                                <tr>
                                                                    <td colspan="10" rowspan="3" class="px-4 py-3 align-top">
                                                                        <div
                                                                            class="rounded-xl border border-dashed border-slate-200 bg-white p-3 text-[10px] text-slate-400"
                                                                        >
                                                                            <p class="mb-1 font-bold tracking-widest uppercase">Notas de Cálculo:</p>
                                                                            <p v-if="equipInvoiceForm.document_type === 'factura'">
                                                                                • Los precios ingresados ya incluyen IGV (18%).
                                                                            </p>
                                                                            <p v-else>
                                                                                • Boleta: el total es el monto bruto sin desglose de impuestos.
                                                                            </p>
                                                                        </div>
                                                                    </td>
                                                                    <td
                                                                        class="px-4 py-2 text-right text-[11px] font-bold tracking-wider text-slate-500 uppercase"
                                                                    >
                                                                        Subtotal Gravado
                                                                    </td>
                                                                    <td class="px-4 py-2 text-right font-mono font-bold text-slate-700">
                                                                        S/.{{ equipSubtotal.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                                                                    </td>
                                                                    <td></td>
                                                                </tr>
                                                                <tr>
                                                                    <td
                                                                        class="px-4 py-2 text-right text-[11px] font-bold tracking-wider text-slate-500 uppercase"
                                                                    >
                                                                        IGV (18%)
                                                                    </td>
                                                                    <td class="px-4 py-2 text-right font-mono font-bold text-slate-600">
                                                                        S/.{{ equipIgv.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                                                                    </td>
                                                                    <td></td>
                                                                </tr>
                                                                <tr class="bg-blue-50/40">
                                                                    <td
                                                                        class="px-4 py-3 text-right text-[12px] font-black tracking-widest text-blue-900 uppercase"
                                                                    >
                                                                        Total Factura
                                                                    </td>
                                                                    <td class="px-4 py-3 text-right">
                                                                        <span class="font-mono text-xl font-black text-blue-700"
                                                                            >S/.{{
                                                                                equipTotal.toLocaleString(undefined, { minimumFractionDigits: 2 })
                                                                            }}</span
                                                                        >
                                                                    </td>
                                                                    <td></td>
                                                                </tr>
                                                            </tfoot>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>

                                            <DialogFooter class="-mx-6 mt-4 -mb-6 rounded-b-lg border-t bg-slate-50 p-6">
                                                <Button variant="ghost" @click="isEquipmentInvoiceModalOpen = false" class="font-bold"
                                                    >Cancelar</Button
                                                >
                                                <Button
                                                    @click="handleEquipInvoiceSubmit"
                                                    :disabled="isEquipSubmitDisabled"
                                                    :class="[
                                                        'font-bold text-white shadow-lg',
                                                        equipItemType === 'kitchen'
                                                            ? 'bg-orange-600 shadow-orange-200 hover:bg-orange-700'
                                                            : 'bg-blue-600 shadow-blue-200 hover:bg-blue-700',
                                                    ]"
                                                >
                                                    Guardar e Ingresar Equipos
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-slate-50/50 hover:bg-slate-50/50">
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Nº Factura</TableHead>
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Tipo</TableHead>
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Empresa / Sede</TableHead>
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Proveedor</TableHead>
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Fecha</TableHead>
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Registrado por</TableHead>
                                        <TableHead class="text-right text-[10px] font-bold text-slate-500 uppercase">Total</TableHead>
                                        <TableHead class="w-[50px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="inv in filteredInvoices" :key="`${inv._source}-${inv.id}`" class="group transition-colors">
                                        <TableCell class="font-bold text-indigo-600">{{ inv.invoice_number || 'S/N' }}</TableCell>
                                        <TableCell>
                                            <span
                                                :class="[
                                                    'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                                                    CATEGORY_META[inv._category]?.cls,
                                                ]"
                                            >
                                                <component :is="CATEGORY_META[inv._category]?.icon" class="h-3 w-3" />
                                                {{ CATEGORY_META[inv._category]?.label }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-slate-900">{{ inv.business?.name }}</span>
                                                <span class="text-xs text-slate-500">{{ inv.headquarter?.name || 'General' }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell class="font-medium text-slate-700">{{ inv.provider?.name }}</TableCell>
                                        <TableCell class="text-slate-500">{{ inv.date }}</TableCell>
                                        <TableCell>
                                            <div class="flex items-center gap-2">
                                                <div
                                                    class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-500"
                                                >
                                                    {{ inv.user?.name?.charAt(0) || '?' }}
                                                </div>
                                                <span class="text-xs text-slate-600">{{ inv.user?.name || 'Sistema' }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-right font-black text-slate-900">
                                            S/.{{ Number(inv.total_amount).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                v-if="inv._source === 'cloth'"
                                                @click="viewInvoiceDetails(inv)"
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 w-8 p-0 text-slate-400 transition-colors hover:text-indigo-600"
                                            >
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                v-else-if="inv._source === 'equipment'"
                                                @click="viewEquipInvoiceDetails(inv)"
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 w-8 p-0 text-slate-400 transition-colors hover:text-blue-600"
                                            >
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="filteredInvoices.length === 0">
                                        <TableCell colspan="8" class="h-32 text-center text-slate-400 italic">
                                            {{
                                                invoiceTypeFilter === 'all'
                                                    ? 'No se han registrado facturas aún.'
                                                    : `No hay facturas de tipo "${CATEGORY_META[invoiceTypeFilter]?.label ?? invoiceTypeFilter}".`
                                            }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="providers">
                    <Card class="overflow-hidden rounded-2xl border-slate-200 bg-white shadow-sm">
                        <CardHeader class="flex flex-row items-center justify-between border-b bg-slate-50/50">
                            <CardTitle class="text-lg">Gestión de Proveedores de EPPS</CardTitle>
                            <Button @click="openProviderModal()" size="sm" class="gap-2 bg-blue-600 text-white hover:bg-blue-700">
                                <Truck class="h-4 w-4" /> Nuevo Proveedor
                            </Button>
                        </CardHeader>

                        <CardContent class="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-slate-50/50 hover:bg-slate-50/50">
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Nombre</TableHead>
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Email</TableHead>
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Teléfono</TableHead>
                                        <TableHead class="w-[100px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="prov in clothProviders" :key="prov.id" class="group transition-colors">
                                        <TableCell class="font-bold text-slate-900">{{ prov.name }}</TableCell>
                                        <TableCell>{{ prov.email || '-' }}</TableCell>
                                        <TableCell>{{ prov.phone || '-' }}</TableCell>
                                        <TableCell class="flex justify-end gap-2">
                                            <Button
                                                @click="openAssignModal(prov)"
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 gap-2 text-slate-400 hover:text-indigo-600"
                                            >
                                                <Box class="h-4 w-4" /> <span class="text-xs font-bold uppercase">EPPs</span>
                                                <Badge variant="secondary" class="h-5 px-1.5 text-[10px]">{{ prov.epps?.length || 0 }}</Badge>
                                            </Button>
                                            <Button
                                                @click="openProviderModal(prov)"
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 w-8 p-0 text-slate-400 hover:text-indigo-600"
                                            >
                                                <Edit2 class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                @click="deleteProvider(prov.id)"
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 w-8 p-0 text-slate-400 hover:text-rose-600"
                                            >
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
                    <Card class="overflow-hidden rounded-2xl border-slate-200 bg-white shadow-sm">
                        <CardHeader class="flex flex-row items-center justify-between border-b bg-slate-50/50">
                            <CardTitle class="text-lg">Catálogo de Elementos de Protección</CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-slate-50/50 hover:bg-slate-50/50">
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Nombre</TableHead>
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Categoría</TableHead>
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Precio Costo</TableHead>
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Tallas Estándar</TableHead>
                                        <TableHead class="w-[100px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="epp in epps"
                                        :key="epp.id"
                                        :class="'group transition-colors ' + (epp.id % 2 === 0 ? '' : 'bg-slate-50/30')"
                                    >
                                        <TableCell class="font-bold text-slate-900">{{ epp.name }}</TableCell>
                                        <TableCell>
                                            <Badge
                                                v-if="epp.category"
                                                variant="outline"
                                                class="flex w-fit items-center gap-1 border-indigo-100 bg-indigo-50 text-indigo-700"
                                            >
                                                <Tags class="h-3 w-3" /> {{ epp.category.name }}
                                            </Badge>
                                            <span v-else class="text-xs text-slate-400 italic">Sin Categoría</span>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex max-w-[300px] flex-col gap-1.5">
                                                <div
                                                    v-for="cp in epp.city_providers"
                                                    :key="cp.id"
                                                    class="flex items-center justify-between rounded-lg border border-indigo-100 bg-indigo-50/20 p-1.5 text-[10px]"
                                                >
                                                    <span class="mr-2 truncate font-bold text-slate-700" :title="cp.provider?.name">{{
                                                        cp.provider?.name
                                                    }}</span>
                                                    <div class="flex shrink-0 items-center gap-2">
                                                        <Badge variant="outline" class="h-4 border-slate-200 whitespace-nowrap text-slate-500">{{
                                                            cp.city?.name
                                                        }}</Badge>
                                                        <span class="font-mono font-black text-indigo-600"
                                                            >S/.{{ Number(cp.cost_price).toFixed(2) }}</span
                                                        >
                                                    </div>
                                                </div>
                                                <span
                                                    v-if="!epp.city_providers || epp.city_providers.length === 0"
                                                    class="text-xs text-slate-400 italic"
                                                    >Precios no asignados</span
                                                >
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex max-w-[200px] flex-wrap gap-1.5">
                                                <Badge
                                                    v-for="sz in epp.sizes"
                                                    :key="sz.id"
                                                    class="bg-indigo-600 text-[9px] font-black tracking-tighter uppercase"
                                                >
                                                    {{ sz.size }}
                                                </Badge>
                                                <span v-if="!epp.sizes?.length" class="text-xs text-slate-400 italic">Sin tallas base</span>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <div class="flex justify-end gap-1">
                                                <Button
                                                    @click="openPriceModal(epp)"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-8 gap-2 text-indigo-600 hover:bg-indigo-50"
                                                >
                                                    <Truck class="h-4 w-4" /> <span class="text-xs font-bold uppercase">Precio</span>
                                                </Button>
                                                <Button
                                                    @click="openSizeModal(epp)"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-8 gap-2 text-indigo-600 hover:bg-indigo-50"
                                                >
                                                    <Plus class="h-4 w-4" /> <span class="text-xs font-bold uppercase">Talla</span>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="epps.length === 0">
                                        <TableCell colspan="3" class="h-32 text-center text-slate-400 italic"> No hay EPPs registrados. </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="equipment-providers">
                    <Card class="overflow-hidden rounded-2xl border-slate-200 bg-white shadow-sm">
                        <CardHeader class="flex flex-row items-center justify-between border-b bg-slate-50/50">
                            <CardTitle class="text-lg">Proveedores de Equipos Tecnológicos</CardTitle>
                            <Button @click="openEqProviderModal()" size="sm" class="gap-2 bg-blue-600 text-white hover:bg-blue-700">
                                <Plus class="h-4 w-4" /> Nuevo Proveedor
                            </Button>
                        </CardHeader>
                        <CardContent class="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-slate-50/50 hover:bg-slate-50/50">
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Nombre / Razón Social</TableHead>
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">RUC</TableHead>
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Email</TableHead>
                                        <TableHead class="text-[10px] font-bold text-slate-500 uppercase">Teléfono</TableHead>
                                        <TableHead class="w-[100px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="prov in equipmentProviders" :key="prov.id" class="group transition-colors">
                                        <TableCell class="font-bold text-slate-900">{{ prov.name }}</TableCell>
                                        <TableCell class="font-mono text-slate-600">{{ prov.ruc || '—' }}</TableCell>
                                        <TableCell class="text-slate-600">{{ prov.email || '—' }}</TableCell>
                                        <TableCell class="text-slate-600">{{ prov.phone || '—' }}</TableCell>
                                        <TableCell class="flex justify-end gap-1">
                                            <Button
                                                @click="openEqProviderModal(prov)"
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 w-8 p-0 text-slate-400 hover:text-blue-600"
                                            >
                                                <Edit2 class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                @click="deleteEqProvider(prov.id)"
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 w-8 p-0 text-slate-400 hover:text-rose-600"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="equipmentProviders.length === 0">
                                        <TableCell colspan="5" class="h-32 text-center text-slate-400 italic">
                                            No hay proveedores de equipos registrados.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="equipments">
                    <Card class="overflow-hidden rounded-2xl border-slate-200 bg-white shadow-sm">
                        <CardHeader class="border-b bg-slate-50/50">
                            <CardTitle class="text-lg">Gestión de Equipos Tecnológicos</CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-col items-center justify-center gap-6 py-16">
                            <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-blue-50">
                                <Laptop class="h-10 w-10 text-blue-600" />
                            </div>
                            <div class="text-center">
                                <h3 class="text-lg font-bold text-slate-800">Equipos Tecnológicos y de Menaje</h3>
                                <p class="mt-1 max-w-sm text-sm text-slate-500">
                                    Gestiona el inventario completo de equipos tecnológicos y de menaje: estado, responsable, historial y despachos.
                                </p>
                            </div>
                            <Button
                                @click="router.visit(route('equipments.index'))"
                                class="gap-2 bg-blue-600 px-6 shadow-lg shadow-blue-200 hover:bg-blue-700"
                            >
                                <Laptop class="h-4 w-4" />
                                Ir a Gestión de Equipos
                                <ExternalLink class="h-4 w-4" />
                            </Button>
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
                        <Button @click="handleProviderSubmit" class="bg-indigo-600 text-white hover:bg-indigo-700">
                            {{ editingProvider ? 'Actualizar' : 'Registrar Proveedor' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Equipment Provider CRUD Modal -->
            <Dialog v-model:open="isEqProviderModalOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Building class="h-5 w-5 text-blue-600" />
                            {{ editingEqProvider ? 'Editar Proveedor de Equipos' : 'Nuevo Proveedor de Equipos' }}
                        </DialogTitle>
                        <DialogDescription>Complete los datos del proveedor de equipos tecnológicos.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label>Nombre / Razón Social <span class="text-red-500">*</span></Label>
                            <Input v-model="eqProviderForm.name" placeholder="Ej: TechSupply S.A.C." />
                        </div>
                        <div class="grid gap-2">
                            <Label>RUC</Label>
                            <Input v-model="eqProviderForm.ruc" placeholder="Ej: 20123456789" maxlength="11" class="font-mono" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Email</Label>
                            <Input v-model="eqProviderForm.email" type="email" placeholder="ventas@techsupply.com" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Teléfono</Label>
                            <Input v-model="eqProviderForm.phone" placeholder="+51 999 999 999" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" @click="isEqProviderModalOpen = false">Cancelar</Button>
                        <Button
                            @click="handleEqProviderSubmit"
                            :disabled="!eqProviderForm.name.trim()"
                            class="bg-blue-600 text-white hover:bg-blue-700"
                        >
                            {{ editingEqProvider ? 'Guardar cambios' : 'Registrar Proveedor' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Epp Insertion Modal -->
            <Dialog v-model:open="isEppModalOpen">
                <DialogContent class="sm:max-w-[450px]">
                    <DialogHeader>
                        <DialogTitle>Nuevo Elemento de Protección (EPP)</DialogTitle>
                        <DialogDescription>Ingrese el nombre del nuevo elemento y seleccione las tallas base disponibles.</DialogDescription>
                    </DialogHeader>
                    <div class="space-y-6 py-4">
                        <div class="grid gap-2">
                            <Label class="text-xs font-bold tracking-widest text-slate-500 uppercase">Nombre del EPP</Label>
                            <Input v-model="eppForm.name" placeholder="Ej: Guantes de Nitrilo" class="border-slate-200" />
                        </div>

                        <div class="grid gap-2">
                            <div class="flex items-center justify-between">
                                <Label class="text-xs font-bold tracking-widest text-slate-500 uppercase">Categoría</Label>
                                <Button
                                    @click="isCategoryModalOpen = true"
                                    variant="ghost"
                                    class="h-6 gap-1 p-0 text-[10px] text-indigo-600 hover:text-indigo-700"
                                >
                                    <Plus class="h-3 w-3" /> Nueva Categoría
                                </Button>
                            </div>
                            <Select v-model="eppForm.category_epp_id">
                                <SelectTrigger class="bg-white"><SelectValue placeholder="Seleccionar categoría (Opcional)" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Ninguna</SelectItem>
                                    <SelectItem v-for="cat in epp_categories || []" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-3">
                            <Label class="flex items-center gap-2 text-xs font-bold tracking-widest text-slate-500 uppercase">
                                <Plus class="h-3 w-3" /> Tallas Estándar (Selección Múltiple)
                            </Label>
                            <div class="grid max-h-[200px] grid-cols-4 gap-2 overflow-y-auto rounded-2xl border bg-slate-50/50 p-4">
                                <label
                                    v-for="size in all_sizes"
                                    :key="size.id"
                                    class="flex cursor-pointer items-center gap-2 rounded-xl border bg-white p-2 transition-all hover:border-indigo-400"
                                    :class="eppForm.size_ids.includes(size.id) ? 'border-indigo-600 ring-1 ring-indigo-600' : 'border-slate-100'"
                                >
                                    <input type="checkbox" :value="size.id" v-model="eppForm.size_ids" class="h-3 w-3 rounded text-indigo-600" />
                                    <span class="text-xs font-bold text-slate-700">{{ size.name }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" @click="isEppModalOpen = false" class="font-bold">Cancelar</Button>
                        <Button
                            @click="handleEppSubmit"
                            :disabled="!eppForm.name"
                            class="bg-indigo-600 font-bold text-white shadow-lg shadow-indigo-100 hover:bg-indigo-700"
                        >
                            Registrar EPP con Tallas
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
                    <div class="max-h-[500px] space-y-6 overflow-y-auto py-4 pr-2">
                        <!-- EPPs Section -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between px-1">
                                <h3 class="flex items-center gap-2 text-sm font-bold text-slate-500 uppercase">
                                    <Box class="h-4 w-4" /> Catálogo de EPPs
                                </h3>
                                <div class="relative w-48">
                                    <Search class="absolute top-2.5 left-2.5 h-3.5 w-3.5 text-slate-400" />
                                    <Input
                                        v-model="eppAssignmentSearch"
                                        placeholder="Buscar EPP..."
                                        class="h-8 rounded-xl border-slate-200 pl-8 text-[10px] focus:ring-indigo-500"
                                    />
                                </div>
                            </div>
                            <div v-if="filteredEppsForAssignment.length === 0" class="py-4 text-center text-xs text-slate-400 italic">
                                No se encontraron EPPs.
                            </div>
                            <div class="grid grid-cols-1 gap-2">
                                <label
                                    v-for="epp in filteredEppsForAssignment"
                                    :key="epp.id"
                                    class="flex cursor-pointer items-center gap-3 rounded-xl border p-2.5 transition-all hover:bg-slate-50"
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
                                        <div
                                            v-if="
                                                assigningProvider &&
                                                epp.city_providers?.some((cp: any) => String(cp.cloth_provider_id) === String(assigningProvider.id))
                                            "
                                            class="flex flex-col items-end gap-1"
                                        >
                                            <div
                                                v-for="cp in epp.city_providers.filter(
                                                    (cp: any) => String(cp.cloth_provider_id) === String(assigningProvider.id),
                                                )"
                                                :key="cp.id"
                                                class="flex items-center gap-1.5 rounded-md border border-indigo-200 bg-white px-2 py-0.5 text-[10px] font-black text-indigo-700"
                                                @dblclick.stop="startEditingPrice(cp)"
                                            >
                                                <span class="text-[8px] tracking-tighter text-slate-400 uppercase">{{ cp.city?.name }}</span>

                                                <template v-if="editingPriceId === cp.id">
                                                    <input
                                                        v-model="tempPriceValue"
                                                        class="h-4 w-16 rounded border border-indigo-500 px-1 text-[10px] focus:ring-1 focus:ring-indigo-400 focus:outline-none"
                                                        @blur="saveInlinePrice(cp)"
                                                        @keyup.enter="saveInlinePrice(cp)"
                                                        @click.stop
                                                    />
                                                </template>
                                                <span v-else>S/.{{ Number(cp.cost_price).toFixed(2) }}</span>
                                            </div>
                                        </div>

                                        <Button
                                            v-if="assigningProvider"
                                            variant="ghost"
                                            size="sm"
                                            class="h-5 px-1.5 text-[8px] font-bold text-indigo-400 uppercase hover:bg-indigo-50 hover:text-indigo-600"
                                            @click.stop.prevent="openPriceModal(epp, assigningProvider.id)"
                                        >
                                            <Plus class="mr-1 h-2.5 w-2.5" /> Precio
                                        </Button>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Clothes Section -->
                        <div class="space-y-3">
                            <h3 class="flex items-center gap-2 px-1 text-sm font-bold text-slate-500 uppercase">
                                <Shirt class="h-4 w-4" /> Catálogo de Prendas
                            </h3>
                            <div v-if="clothes.length === 0" class="py-4 text-center text-xs text-slate-400 italic">No hay prendas registradas.</div>
                            <div class="grid grid-cols-1 gap-2">
                                <label
                                    v-for="cloth in clothes"
                                    :key="cloth.id"
                                    class="flex cursor-pointer items-center gap-3 rounded-xl border p-2.5 transition-all hover:bg-slate-50"
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
                        <Button @click="handleAssignmentSubmit" class="bg-indigo-600 text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700">
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
                        <DialogDescription>Asigne una nueva talla al elemento seleccionado.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label>Talla / Medida</Label>
                            <Input v-model="sizeForm.size" placeholder="Ej: XL, 42, Grande" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" @click="isSizeModalOpen = false">Cerrar</Button>
                        <Button @click="handleSizeSubmit" :disabled="!sizeForm.size" class="bg-indigo-600 text-white"> Agregar Talla </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <!-- View Invoice Details Modal -->
            <Dialog v-model:open="isViewInvoiceModalOpen">
                <DialogContent class="flex max-h-[90vh] flex-col overflow-hidden p-0 sm:max-w-[700px]">
                    <DialogHeader class="border-b bg-slate-50/50 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <DialogTitle class="flex items-center gap-2 text-xl font-black text-slate-900">
                                    <FileText class="h-5 w-5 text-indigo-600" />
                                    Factura #{{ selectedInvoice?.invoice_number }}
                                </DialogTitle>
                                <DialogDescription class="mt-1">
                                    {{ selectedInvoice?.provider?.name }} • {{ selectedInvoice?.date }} • Reg. por:
                                    {{ selectedInvoice?.user?.name || 'Sistema' }}
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <div class="flex-1 space-y-6 overflow-y-auto p-6">
                        <!-- Invoice Info Cards -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 italic">
                                <p class="mb-2 text-[10px] font-black tracking-widest text-slate-400 uppercase">Origen / Destino</p>
                                <div class="space-y-1">
                                    <p class="flex items-center gap-2 text-sm font-bold text-slate-800">
                                        <Building class="h-3.5 w-3.5" /> {{ selectedInvoice?.business?.name }}
                                    </p>
                                    <p class="ml-5 text-xs text-slate-500">
                                        {{ selectedInvoice?.headquarter?.name || 'Distribución General' }}
                                    </p>
                                </div>
                            </div>
                            <div class="rounded-2xl border border-indigo-100 bg-indigo-50/30 p-4">
                                <p class="mb-2 text-[10px] font-black tracking-widest text-indigo-400 uppercase">Notas / Observaciones</p>
                                <p class="text-xs leading-relaxed text-slate-600">
                                    {{ selectedInvoice?.notes || 'Sin observaciones adicionales para esta factura.' }}
                                </p>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div class="space-y-3">
                            <h3 class="flex items-center gap-2 text-xs font-black tracking-widest text-slate-400 uppercase">
                                <Box class="h-4 w-4" /> Detalle de Ítems
                            </h3>
                            <div class="overflow-hidden rounded-2xl border border-slate-100">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-slate-50/80">
                                            <TableHead class="text-[10px] font-black text-slate-500 uppercase">Item</TableHead>
                                            <TableHead class="text-[10px] font-black text-slate-500 uppercase">Talla</TableHead>
                                            <TableHead class="text-[10px] font-black text-slate-500 uppercase">Color</TableHead>
                                            <TableHead class="text-center text-[10px] font-black text-slate-500 uppercase">Cant.</TableHead>
                                            <TableHead class="text-right text-[10px] font-black text-slate-500 uppercase">P. Unit</TableHead>
                                            <TableHead class="text-right text-[10px] font-black text-slate-500 uppercase">Total</TableHead>
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
                                                    <div
                                                        class="h-2.5 w-2.5 rounded-full border border-slate-200"
                                                        :style="{ backgroundColor: item.color.hex_code }"
                                                    ></div>
                                                    <span class="text-[10px] font-medium text-slate-500">{{ item.color.name }}</span>
                                                </div>
                                                <span v-else class="text-[10px] text-slate-400">-</span>
                                            </TableCell>
                                            <TableCell class="py-2 text-center text-xs font-black text-slate-700">{{ item.quantity }}</TableCell>
                                            <TableCell class="py-2 text-right text-[10px] font-medium text-slate-500"
                                                >S/.{{ Number(item.unit_price).toFixed(2) }}</TableCell
                                            >
                                            <TableCell class="py-2 text-right text-xs font-black text-indigo-600"
                                                >S/.{{ Number(item.total_price).toFixed(2) }}</TableCell
                                            >
                                        </TableRow>
                                        <TableRow class="bg-slate-50/30">
                                            <TableCell colspan="4" class="rounded-bl-xl border-t border-slate-100"></TableCell>
                                            <TableCell colspan="2" class="rounded-br-xl border-t border-slate-100 p-0">
                                                <div class="space-y-3 p-4">
                                                    <div class="flex items-center justify-between px-2">
                                                        <span class="text-[10px] font-black tracking-widest text-slate-400 uppercase">IGV (18%)</span>
                                                        <span class="font-mono text-xs font-bold text-slate-600"
                                                            >S/.
                                                            {{
                                                                Number((selectedInvoice?.total_amount / 1.18) * 0.18 || 0).toLocaleString(undefined, {
                                                                    minimumFractionDigits: 2,
                                                                })
                                                            }}</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="flex items-center justify-between rounded-xl border border-indigo-100 bg-indigo-50/50 p-3 shadow-sm"
                                                    >
                                                        <span class="text-[10px] font-black tracking-widest text-indigo-700 uppercase"
                                                            >Total Factura</span
                                                        >
                                                        <span class="font-mono text-sm font-black text-indigo-700"
                                                            >S/.
                                                            {{
                                                                Number(selectedInvoice?.total_amount || 0).toLocaleString(undefined, {
                                                                    minimumFractionDigits: 2,
                                                                })
                                                            }}</span
                                                        >
                                                    </div>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <div
                            v-if="selectedInvoice?.invoice_image"
                            class="mt-4 flex items-center justify-between rounded-2xl border border-indigo-100 bg-indigo-50/30 p-4"
                        >
                            <p class="text-[10px] font-black tracking-widest text-indigo-400 uppercase">Evidencia Adjunta</p>
                            <a
                                :href="selectedInvoice.invoice_image"
                                target="_blank"
                                class="inline-flex items-center gap-2 text-sm font-bold text-indigo-600 transition-colors hover:text-indigo-800"
                            >
                                <FileText class="h-4 w-4" /> Ver Documento / Imagen
                            </a>
                        </div>
                        <div v-else class="mt-4 space-y-3 rounded-2xl border border-slate-100 bg-slate-50/30 p-4">
                            <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">Adjuntar Evidencia</p>
                            <p class="text-xs text-slate-500">Esta factura aún no cuenta con un documento o imagen de evidencia.</p>
                            <div class="relative w-full">
                                <Input
                                    type="file"
                                    @change="handleInvoiceImageUpdate"
                                    accept="image/*,.pdf"
                                    :disabled="isUploadingInvoiceImage"
                                    class="h-12 w-full cursor-pointer bg-white text-xs file:mr-4 file:rounded-full file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-xs file:font-bold file:text-indigo-600 hover:file:bg-indigo-100"
                                />
                                <div
                                    v-if="isUploadingInvoiceImage"
                                    class="absolute inset-0 z-10 flex items-center justify-center gap-2 rounded-md bg-white/50 text-sm font-bold text-indigo-600 backdrop-blur-sm"
                                >
                                    <Loader2 class="h-4 w-4 animate-spin" /> Subiendo archivo...
                                </div>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="border-t bg-slate-50/50 p-4">
                        <Button
                            variant="outline"
                            @click="isViewInvoiceModalOpen = false"
                            class="h-10 rounded-xl border-slate-200 text-[10px] font-bold tracking-widest uppercase"
                        >
                            Cerrar Detalle
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Equipment Invoice Detail Modal -->
            <Dialog v-model:open="isViewEquipInvoiceModalOpen">
                <DialogContent class="flex max-h-[90vh] flex-col overflow-hidden p-0 sm:max-w-[800px]">
                    <DialogHeader class="border-b bg-slate-50/50 p-6">
                        <DialogTitle class="flex items-center gap-2 text-xl font-black text-slate-900">
                            <component
                                :is="selectedEquipInvoice?._category === 'menaje' ? ChefHat : Laptop"
                                class="h-5 w-5"
                                :class="selectedEquipInvoice?._category === 'menaje' ? 'text-orange-600' : 'text-blue-600'"
                            />
                            Factura #{{ selectedEquipInvoice?.invoice_number || 'S/N' }}
                        </DialogTitle>
                        <DialogDescription class="mt-1">
                            {{ selectedEquipInvoice?.provider?.name || 'Sin proveedor' }} • {{ selectedEquipInvoice?.date }} • Reg. por:
                            {{ selectedEquipInvoice?.user?.name || 'Sistema' }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="flex-1 space-y-6 overflow-y-auto p-6">
                        <!-- Info cards -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <p class="mb-2 text-[10px] font-black tracking-widest text-slate-400 uppercase">Empresa</p>
                                <p class="flex items-center gap-2 text-sm font-bold text-slate-800">
                                    <Building class="h-3.5 w-3.5 flex-shrink-0" />
                                    {{ selectedEquipInvoice?.business?.name || '—' }}
                                </p>
                                <p class="mt-1 text-[10px] font-black tracking-widest text-slate-400 uppercase">Tipo Documento</p>
                                <p class="mt-1 text-xs font-semibold text-slate-600 capitalize">
                                    {{ selectedEquipInvoice?.document_type || '—' }}
                                </p>
                            </div>
                            <div class="rounded-2xl border border-blue-100 bg-blue-50/30 p-4">
                                <p class="mb-2 text-[10px] font-black tracking-widest text-blue-400 uppercase">Notas / Observaciones</p>
                                <p class="text-xs leading-relaxed text-slate-600">
                                    {{ selectedEquipInvoice?.notes || 'Sin observaciones adicionales.' }}
                                </p>
                            </div>
                        </div>

                        <!-- Equipment items -->
                        <div class="space-y-3">
                            <h3 class="flex items-center gap-2 text-xs font-black tracking-widest text-slate-400 uppercase">
                                <Box class="h-4 w-4" /> Equipos Registrados
                            </h3>
                            <div class="overflow-hidden rounded-2xl border border-slate-100">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-slate-50/80">
                                            <TableHead class="text-[10px] font-black text-slate-500 uppercase">Tipo</TableHead>
                                            <TableHead class="text-[10px] font-black text-slate-500 uppercase">Nombre</TableHead>
                                            <TableHead class="text-[10px] font-black text-slate-500 uppercase">Marca / Modelo</TableHead>
                                            <TableHead class="text-[10px] font-black text-slate-500 uppercase">Código</TableHead>
                                            <TableHead class="text-[10px] font-black text-slate-500 uppercase">Serie</TableHead>
                                            <TableHead class="text-[10px] font-black text-slate-500 uppercase">Color</TableHead>
                                            <TableHead class="text-center text-[10px] font-black text-slate-500 uppercase">Cant.</TableHead>
                                            <TableHead class="text-right text-[10px] font-black text-slate-500 uppercase">P. Unit</TableHead>
                                            <TableHead class="text-right text-[10px] font-black text-slate-500 uppercase">Total</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <template v-if="selectedEquipInvoice">
                                            <TableRow
                                                v-for="eq in [
                                                    ...(selectedEquipInvoice.computer_equipments ?? []).map((e: any) => ({
                                                        ...e,
                                                        _type: 'computer',
                                                    })),
                                                    ...(selectedEquipInvoice.kitchen_equipments ?? []).map((e: any) => ({ ...e, _type: 'kitchen' })),
                                                ]"
                                                :key="eq.id"
                                            >
                                                <TableCell class="py-2">
                                                    <span
                                                        :class="[
                                                            'inline-flex items-center gap-1 rounded-full border px-1.5 py-0.5 text-[10px] font-semibold',
                                                            eq._type === 'computer'
                                                                ? 'border-blue-200 bg-blue-50 text-blue-700'
                                                                : 'border-orange-200 bg-orange-50 text-orange-700',
                                                        ]"
                                                    >
                                                        <component :is="eq._type === 'computer' ? Laptop : ChefHat" class="h-2.5 w-2.5" />
                                                        {{ eq._type === 'computer' ? 'IT' : 'Menaje' }}
                                                    </span>
                                                </TableCell>
                                                <TableCell class="py-2 text-xs font-bold text-slate-800">{{ eq.name || '—' }}</TableCell>
                                                <TableCell class="py-2 text-xs text-slate-500">
                                                    {{ [eq.brand, eq.model].filter(Boolean).join(' · ') || '—' }}
                                                </TableCell>
                                                <TableCell class="py-2 font-mono text-xs text-slate-500">{{ eq.code || '—' }}</TableCell>
                                                <TableCell class="py-2 font-mono text-xs text-slate-500">{{ eq.series || '—' }}</TableCell>
                                                <TableCell class="py-2 text-xs text-slate-500">{{ eq.color || '—' }}</TableCell>
                                                <TableCell class="py-2 text-center text-xs font-black text-slate-700">{{
                                                    eq.quantity ?? 1
                                                }}</TableCell>
                                                <TableCell class="py-2 text-right text-[10px] font-medium text-slate-500">
                                                    S/.{{ Number(eq.unit_price ?? 0).toFixed(2) }}
                                                </TableCell>
                                                <TableCell class="py-2 text-right text-xs font-black text-blue-600">
                                                    S/.{{
                                                        (Number(eq.unit_price ?? 0) * (eq.quantity ?? 1)).toLocaleString(undefined, {
                                                            minimumFractionDigits: 2,
                                                        })
                                                    }}
                                                </TableCell>
                                            </TableRow>
                                        </template>

                                        <!-- Totals row -->
                                        <TableRow class="bg-slate-50/30">
                                            <TableCell colspan="7" class="border-t border-slate-100"></TableCell>
                                            <TableCell colspan="2" class="border-t border-slate-100 p-0">
                                                <div class="space-y-3 p-4">
                                                    <div class="flex items-center justify-between px-2">
                                                        <span class="text-[10px] font-black tracking-widest text-slate-400 uppercase">IGV (18%)</span>
                                                        <span class="font-mono text-xs font-bold text-slate-600">
                                                            S/.{{
                                                                Number((selectedEquipInvoice?.total_amount / 1.18) * 0.18 || 0).toLocaleString(
                                                                    undefined,
                                                                    { minimumFractionDigits: 2 },
                                                                )
                                                            }}
                                                        </span>
                                                    </div>
                                                    <div
                                                        class="flex items-center justify-between rounded-xl border border-blue-100 bg-blue-50/50 p-3 shadow-sm"
                                                    >
                                                        <span class="text-[10px] font-black tracking-widest text-blue-700 uppercase"
                                                            >Total Factura</span
                                                        >
                                                        <span class="font-mono text-sm font-black text-blue-700">
                                                            S/.{{
                                                                Number(selectedEquipInvoice?.total_amount || 0).toLocaleString(undefined, {
                                                                    minimumFractionDigits: 2,
                                                                })
                                                            }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- Evidence -->
                        <div
                            v-if="selectedEquipInvoice?.invoice_image"
                            class="flex items-center justify-between rounded-2xl border border-blue-100 bg-blue-50/30 p-4"
                        >
                            <p class="text-[10px] font-black tracking-widest text-blue-400 uppercase">Evidencia Adjunta</p>
                            <a
                                :href="selectedEquipInvoice.invoice_image"
                                target="_blank"
                                class="inline-flex items-center gap-2 text-sm font-bold text-blue-600 transition-colors hover:text-blue-800"
                            >
                                <FileText class="h-4 w-4" /> Ver Documento / Imagen
                            </a>
                        </div>
                        <div v-else class="rounded-2xl border border-slate-100 bg-slate-50/30 p-4">
                            <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">Sin Evidencia</p>
                            <p class="mt-1 text-xs text-slate-500">Esta factura no tiene documento o imagen adjunta.</p>
                        </div>
                    </div>

                    <DialogFooter class="border-t bg-slate-50/50 p-4">
                        <Button
                            variant="outline"
                            @click="isViewEquipInvoiceModalOpen = false"
                            class="h-10 rounded-xl border-slate-200 text-[10px] font-bold tracking-widest uppercase"
                        >
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
                                <span class="absolute top-2.5 left-3 text-sm text-slate-400">S/.</span>
                                <Input v-model="priceForm.cost_price" type="number" step="0.01" placeholder="0.00" class="pl-10" />
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" @click="isPriceModalOpen = false">Cerrar</Button>
                        <Button
                            @click="handlePriceSubmit"
                            :disabled="!priceForm.cloth_provider_id || !priceForm.city_id || !priceForm.cost_price"
                            class="bg-indigo-600 font-bold text-white"
                        >
                            Asignar Precio
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Epp Category Modal -->
            <Dialog v-model:open="isCategoryModalOpen">
                <DialogContent class="sm:max-w-[400px]">
                    <DialogHeader>
                        <DialogTitle>Nueva Categoría de EPP</DialogTitle>
                        <DialogDescription>Agrupe sus EPPs por tipos (ej: Protección Visual, Calzado, etc.)</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label>Nombre de la Categoría</Label>
                            <Input v-model="categoryForm.name" placeholder="Ej: Protección Facial" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" @click="isCategoryModalOpen = false">Cancelar</Button>
                        <Button @click="handleCategorySubmit" :disabled="!categoryForm.name" class="bg-indigo-600 font-bold text-white">
                            Guardar Categoría
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
