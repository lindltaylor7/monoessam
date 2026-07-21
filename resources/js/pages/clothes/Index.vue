<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useStaffFilter } from '@/composables/useStaffFilter';
import AppLayout from '@/layouts/AppLayout.vue';
import StaffClothesDialog from '@/pages/clothes/partials/StaffClothesDialog.vue';
import StaffHistoryDialog from '@/pages/clothes/partials/StaffHistoryDialog.vue';
import InventoryTransferDialog from '@/pages/inventory/partials/InventoryTransferDialog.vue';
import { Staff } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    Briefcase,
    Building2,
    Calendar,
    ChevronLeft,
    ChevronRight,
    Coffee,
    Eye,
    History,
    Package,
    RotateCcw,
    Truck,
    User,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';

interface ExtendedStaff extends Staff {
    staff_clothes: Array<{
        id: number;
        cloth_id: number | null;
        epp_id: number | null;
        color_id: number | null;
        clothing_size: string;
        clothe_name?: string;
        cloth?: { name: string };
        epp?: { name: string; sizes: Array<{ id: number; size: string }> };
        color?: { id: number; name: string };
        status?: string;
        condition?: string;
        quantity: number;
    }>;
    staff_financial?: {
        start_date?: string;
    };
}

const props = defineProps<{
    staff: ExtendedStaff[];
    roleClothes: Record<number, Record<string, Array<{ id: number; name: string }>>>;
    roleEpps: Record<number, Record<string, Array<{ id: number; name: string; sizes: Array<{ id: number; size: string }> }>>>;
    units: any[];
    colors: Array<{ id: number; name: string }>;
    headquarters?: Array<{ id: number; name: string; business?: { name: string } }>;
    transfers: any[];
}>();

const getClothesForStaff = (person: ExtendedStaff) => {
    if (!person.role_id) return [];
    const roleMap = props.roleClothes[person.role_id];
    if (!roleMap) return [];

    const cafeId = person.cafe_id || (person.staffable_type === 'App\\Models\\Cafe' ? person.staffable_id : null);
    const specific = cafeId ? roleMap[String(cafeId)] || [] : [];
    const common = roleMap['all'] || [];

    // Merge without duplicates
    const merged = [...specific];
    common.forEach((c) => {
        if (!merged.find((m) => m.id === c.id)) merged.push(c);
    });
    return merged;
};

const localStaff = ref(props.staff as any[]);
const selectedStaff = ref<ExtendedStaff | null>(null);

watch(
    () => props.staff,
    (newVal) => {
        localStaff.value = newVal;
        if (selectedStaff.value) {
            const updated = newVal.find((s) => s.id === selectedStaff.value?.id);
            if (updated) {
                selectedStaff.value = updated;
            }
        }
    },
);

const { filteredStaff, searchQuery, selectedUnitId } = useStaffFilter(localStaff as any);

const currentPage = ref(1);
const itemsPerPage = 10;

const totalPages = computed(() => Math.ceil(filteredStaff.value.length / itemsPerPage));

const paginatedStaff = computed(() => {
    if (currentPage.value > totalPages.value && totalPages.value > 0) currentPage.value = 1;
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredStaff.value.slice(start, end);
});

const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
};

const prevPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--;
    }
};

watch(filteredStaff, () => {
    currentPage.value = 1;
});

const isModalOpen = ref(false);
const isTransferModalOpen = ref(false);

const openModal = (staff: ExtendedStaff) => {
    selectedStaff.value = staff;
    isModalOpen.value = true;
};

const isHistoryModalOpen = ref(false);
const selectedStaffForHistory = ref<ExtendedStaff | null>(null);

const openHistoryModal = (staff: ExtendedStaff) => {
    selectedStaffForHistory.value = staff;
    isHistoryModalOpen.value = true;
};

// ── Registro de Envíos a Unidad + Devolución (mismo backend que /inventory) ────────────────
const isTransfersLogOpen = ref(false);
const transfersFilterStaff = ref<ExtendedStaff | null>(null);

// Sin staff seleccionado (botón de la cabecera) se ven todos los envíos; abierto desde la fila de
// una persona (botón "Retornar EPP") se filtra solo a los envíos asignados a ella.
const filteredTransfers = computed(() => {
    if (!transfersFilterStaff.value) return props.transfers;
    return props.transfers.filter((t: any) => t.staff_id === transfersFilterStaff.value?.id);
});

const openTransfersLog = (staff: ExtendedStaff | null = null) => {
    transfersFilterStaff.value = staff;
    isTransfersLogOpen.value = true;
};

// Entregas directas a esta persona (Staff_clothes con status "Entregado") — distintas de los
// envíos a Unidad de arriba: son EPP/ropa asignados a alguien puntualmente desde la ficha del
// colaborador (pestaña "Estado Actual"), no un traslado masivo de stock a una unidad.
const directDeliveries = computed(() => {
    if (!transfersFilterStaff.value) return [];
    // Excluye las prendas de "talla de referencia" (Polo, Overall, Casaca, etc. — clothe_name
    // fijo, sin cloth_id/epp_id): no tienen stock real detrás, así que "Entregado" ahí solo
    // indica que se le confirmó la talla, no que haya algo que devolver a inventario.
    return (transfersFilterStaff.value.staff_clothes || []).filter((sc) => sc.status === 'Entregado' && (sc.epp_id || sc.cloth_id));
});

const directReturnModal = ref({
    open: false,
    item: null as ExtendedStaff['staff_clothes'][number] | null,
    headquarter_id: '',
});

const openDirectReturnModal = (item: ExtendedStaff['staff_clothes'][number]) => {
    directReturnModal.value = { open: true, item, headquarter_id: '' };
};

const confirmDirectReturn = () => {
    const { item, headquarter_id } = directReturnModal.value;
    if (!item || !headquarter_id) return;
    router.post(
        route('clothes.status'),
        {
            id: item.id,
            status: 'Devuelto',
            color_id: item.color_id,
            clothing_size: item.clothing_size,
            epp_id: item.epp_id,
            quantity: item.quantity,
            headquarter_id,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                directReturnModal.value.open = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Devolución registrada',
                    text: 'La devolución del EPP se realizó correctamente.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
        },
    );
};

const isReturnModalOpen = ref(false);
const returnForm = ref({
    unit_id: '',
    transfer_id: null as number | null,
    items: [] as any[],
});

const openReturnModal = (transfer: any) => {
    returnForm.value = {
        unit_id: String(transfer.unit_id),
        transfer_id: transfer.id,
        items: transfer.items.map((i: any) => ({
            stockable_id: i.stockable_id,
            stockable_type: i.stockable_type,
            name: i.stockable?.name,
            quantity: i.quantity,
            size: i.size,
            color_id: i.color_id,
        })),
    };
    isReturnModalOpen.value = true;
};

const handleReturn = () => {
    router.post(route('inventory.transfer.return'), returnForm.value, {
        onSuccess: () => {
            isReturnModalOpen.value = false;
            Swal.fire({
                icon: 'success',
                title: 'Devolución registrada',
                text: 'La devolución se realizó correctamente.',
                timer: 2000,
                showConfirmButton: false,
            });
        },
        preserveScroll: true,
    });
};

const updateSize = (staffId: number, clothId: number, size: string) => {
    router.post(
        route('clothes.staff-size'),
        {
            staff_id: staffId,
            cloth_id: clothId,
            clothing_size: size,
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['staff'],
        },
    );
};

const getClothSize = (staff: ExtendedStaff, clothId: number) => {
    const entry = staff.staff_clothes.find((sc) => sc.cloth_id === clothId);
    return entry ? entry.clothing_size : '';
};

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((word: string) => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};
</script>

<template>
    <Head title="EPPs" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Personal', href: route('staff.index') },
            { title: 'Tallas', href: route('clothes.index') },
        ]"
    >
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Asignación de Prendas por Rol y Comedor</h1>
                    <p class="text-muted-foreground mt-1 text-xs sm:text-sm">
                        Selecciona las prendas asignadas a cada rol/cargo en el comedor seleccionado
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <!-- <Link :href="route('inventory.index')">
                        <Button variant="outline" class="gap-2">
                            <Package class="h-4 w-4" />
                            Inventario
                        </Button>
                    </Link> -->
                    <Button @click="openTransfersLog()" size="sm" variant="outline" class="gap-2">
                        <History class="h-4 w-4" />
                        Registro de Envíos
                    </Button>
                    <Button @click="isTransferModalOpen = true" size="sm" class="gap-2 bg-slate-900 text-white shadow-lg hover:bg-black">
                        <Truck class="h-4 w-4" />
                        Generar Envío a Unidad
                    </Button>
                </div>
            </div>

            <div class="mb-6 flex items-center">
                <Input type="text" placeholder="Buscar personal por dni o nombre" v-model="searchQuery" />
                <Select v-model="selectedUnitId">
                    <SelectTrigger class="ml-2 h-10 w-[200px] border-zinc-200 bg-white hover:bg-zinc-50">
                        <SelectValue placeholder="Seleccionar unidad" />
                    </SelectTrigger>
                    <SelectContent class="border-zinc-200 bg-white shadow-lg">
                        <SelectItem value="0" class="hover:bg-zinc-50"> Ninguna </SelectItem>
                        <SelectItem :value="String(unit.id)" class="hover:bg-zinc-50" v-for="unit in units" :key="unit.id">
                            {{ unit.mine.name }} - {{ unit.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-gray-50 text-gray-500">
                            <tr>
                                <th class="p-4 font-medium">
                                    <span class="flex items-center gap-1.5"><User class="h-3.5 w-3.5" /> Personal</span>
                                </th>
                                <th class="p-4 font-medium">
                                    <span class="flex items-center gap-1.5"><Calendar class="h-3.5 w-3.5" /> Fecha de ingreso</span>
                                </th>
                                <th class="p-4 font-medium">
                                    <span class="flex items-center gap-1.5"><Briefcase class="h-3.5 w-3.5" /> Cargo</span>
                                </th>
                                <th class="p-4 font-medium">
                                    <span class="flex items-center gap-1.5"><Coffee class="h-3.5 w-3.5" /> Comedor</span>
                                </th>
                                <th class="p-4 font-medium">
                                    <span class="flex items-center gap-1.5"><Building2 class="h-3.5 w-3.5" /> Unidad</span>
                                </th>
                                <!-- <th class="p-4 font-medium">Tallas</th> -->
                                <th class="w-36 p-4 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="person in paginatedStaff" :key="person.id" class="transition-colors hover:bg-indigo-50/30">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <Avatar class="h-10 w-10 border-2 border-white shadow ring-2 ring-indigo-100">
                                            <AvatarImage v-if="person.photo?.url" :src="`/storage/${person.photo?.url}`" class="object-cover" />
                                            <AvatarFallback class="bg-indigo-50 font-bold text-indigo-600">{{
                                                getInitials(person.name)
                                            }}</AvatarFallback>
                                        </Avatar>
                                        <div class="font-semibold text-gray-900">{{ person.name }}</div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"
                                        v-if="person.staff_financial && person.staff_financial.start_date"
                                    >
                                        <Calendar class="h-3 w-3" />
                                        {{ person.staff_financial.start_date }}
                                    </span>
                                    <span v-else class="text-gray-400 italic">Sin fecha de ingreso</span>
                                </td>
                                <td class="p-4">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700"
                                        v-if="person.role"
                                    >
                                        <Briefcase class="h-3 w-3" />
                                        {{ person.role.name }}
                                    </span>
                                    <span v-else class="text-gray-400 italic">Sin cargo</span>
                                </td>
                                <td class="p-4">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700"
                                        v-if="person.staffable"
                                    >
                                        <Coffee class="h-3 w-3" />
                                        {{ person.staffable.name }}
                                    </span>
                                    <span v-else class="text-gray-400 italic">Sin café</span>
                                </td>
                                <td class="p-4">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700"
                                        v-if="person.staffable?.unit"
                                    >
                                        <Building2 class="h-3 w-3" />
                                        {{ person.staffable?.unit?.name }} - {{ person.staffable?.unit?.mine?.name }}
                                    </span>
                                    <span v-else class="text-gray-400 italic">Sin unidad</span>
                                </td>
                                <!--
                                <td class="p-4">
                                    <div v-if="person.role_id && roleClothes[person.role_id] && roleClothes[person.role_id].length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        <div v-for="cloth in roleClothes[person.role_id]" :key="cloth.id" class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-gray-500">{{ cloth.name }}</label>
                                            <input 
                                                type="text" 
                                                class="h-8 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                                :value="getClothSize(person, cloth.id)"
                                                placeholder="Talla..."
                                                @change="(e) => updateSize(person.id, cloth.id, (e.target as HTMLInputElement).value)"
                                            />
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-gray-400 italic">
                                        No hay prendas configuradas para este cargo.
                                    </div>
                                </td>
                                -->
                                <td class="p-4">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            @click="openModal(person as any)"
                                            title="Ver / Editar tallas y EPP"
                                            class="group flex h-9 w-9 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-indigo-50 hover:text-indigo-600"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="openHistoryModal(person as any)"
                                            title="Historial de entregas"
                                            class="group flex h-9 w-9 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-blue-50 hover:text-blue-600"
                                        >
                                            <History class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="openTransfersLog(person as any)"
                                            title="Envíos y devolución de EPP"
                                            class="group flex h-9 w-9 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-amber-50 hover:text-amber-600"
                                        >
                                            <RotateCcw class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="paginatedStaff.length === 0">
                                <td colspan="5" class="p-8 text-center text-gray-500">No se encontró personal.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between border-t p-4" v-if="totalPages > 1">
                    <div class="text-muted-foreground text-sm">
                        Mostrando {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, filteredStaff.length) }} de
                        {{ filteredStaff.length }} registros
                    </div>
                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="sm" :disabled="currentPage === 1" @click="prevPage">
                            <ChevronLeft class="h-4 w-4" />
                            Anterior
                        </Button>
                        <div class="text-sm font-medium">Página {{ currentPage }} de {{ totalPages }}</div>
                        <Button variant="outline" size="sm" :disabled="currentPage === totalPages" @click="nextPage">
                            Siguiente
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <StaffClothesDialog
            :open="isModalOpen"
            :staff="selectedStaff"
            :colors="colors"
            :role-clothes="roleClothes"
            :role-epps="roleEpps"
            :headquarters="headquarters"
            @update:open="isModalOpen = $event"
        />

        <StaffHistoryDialog :open="isHistoryModalOpen" :staff="selectedStaffForHistory" @update:open="isHistoryModalOpen = $event" />

        <InventoryTransferDialog
            v-if="isTransferModalOpen"
            :open="isTransferModalOpen"
            :units="units"
            :staff="staff"
            :clothes="[]"
            :epps="[]"
            @update:open="isTransferModalOpen = $event"
        />

        <!-- Registro de Envíos a Unidad -->
        <Dialog v-model:open="isTransfersLogOpen">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-[900px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <History class="h-5 w-5 text-indigo-600" />
                        Registro de Envíos a Unidad
                        <span v-if="transfersFilterStaff" class="text-base font-normal text-slate-400">— {{ transfersFilterStaff.name }}</span>
                    </DialogTitle>
                    <DialogDescription>
                        <template v-if="transfersFilterStaff">EPP y ropa enviados con {{ transfersFilterStaff.name }} como responsable.</template>
                        <template v-else>EPP y ropa enviados desde el almacén principal hacia una unidad.</template>
                    </DialogDescription>
                </DialogHeader>

                <div class="overflow-hidden rounded-2xl border shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow class="bg-indigo-50/30">
                                <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Fecha Envío</TableHead>
                                <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Destino (Unidad)</TableHead>
                                <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Personal Asignado</TableHead>
                                <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Items</TableHead>
                                <TableHead class="text-muted-foreground text-center text-[10px] font-bold uppercase">Estado</TableHead>
                                <TableHead class="w-[120px]"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="transfer in filteredTransfers" :key="transfer.id" class="group hover:bg-muted/30 transition-colors">
                                <TableCell class="text-xs font-medium text-slate-600">
                                    {{ new Date(transfer.created_at).toLocaleDateString() }}
                                    <span class="block font-mono text-[10px] text-slate-400">{{
                                        new Date(transfer.created_at).toLocaleTimeString()
                                    }}</span>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <div class="rounded-lg bg-indigo-100 p-1.5 text-indigo-600">
                                            <Building2 class="h-3.5 w-3.5" />
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-foreground leading-tight font-bold">{{ transfer.unit?.name }}</span>
                                            <span class="text-[10px] font-black tracking-tighter text-slate-400 uppercase">{{
                                                transfer.unit?.mine?.name
                                            }}</span>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <User class="h-3.5 w-3.5 text-slate-300" />
                                        <span class="text-sm text-slate-600">{{ transfer.staff?.name || 'Stock de Unidad' }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="item in transfer.items"
                                            :key="item.id"
                                            variant="outline"
                                            class="bg-card text-muted-foreground border px-1.5 py-0 text-[10px] lowercase"
                                        >
                                            {{ item.quantity }}x {{ item.stockable?.name }} ({{ item.size || 'U' }})
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge
                                        :class="[
                                            'rounded-full border-none px-2 text-[9px] font-black tracking-tighter uppercase shadow-none',
                                            transfer.status === 'sent' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700',
                                        ]"
                                    >
                                        {{ transfer.status === 'sent' ? 'En Tránsito / Uso' : 'Devuelto' }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Button
                                        v-if="transfer.status === 'sent'"
                                        @click="openReturnModal(transfer)"
                                        variant="outline"
                                        size="sm"
                                        class="bg-card h-8 gap-1 rounded-lg border text-[10px] font-black tracking-tighter uppercase transition-all hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                    >
                                        <History class="h-3.5 w-3.5" /> DEVOLVER
                                    </Button>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="filteredTransfers.length === 0">
                                <TableCell colspan="6" class="h-32 text-center text-slate-400 italic">
                                    {{ transfersFilterStaff ? 'No hay envíos registrados para esta persona.' : 'No hay envíos registrados.' }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Entregas directas a la persona (Staff_clothes, distinto de un envío a Unidad) -->
                <div v-if="transfersFilterStaff" class="space-y-2">
                    <p class="text-xs font-bold tracking-widest text-slate-400 uppercase">Entregas Directas a {{ transfersFilterStaff.name }}</p>
                    <div class="overflow-hidden rounded-2xl border shadow-sm">
                        <Table>
                            <TableHeader>
                                <TableRow class="bg-indigo-50/30">
                                    <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Ítem</TableHead>
                                    <TableHead class="text-muted-foreground text-center text-[10px] font-bold uppercase">Talla</TableHead>
                                    <TableHead class="text-muted-foreground text-[10px] font-bold uppercase">Color</TableHead>
                                    <TableHead class="text-muted-foreground text-center text-[10px] font-bold uppercase">Cant</TableHead>
                                    <TableHead class="w-[120px]"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in directDeliveries" :key="item.id" class="group hover:bg-muted/30 transition-colors">
                                    <TableCell class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                        <Package class="h-3.5 w-3.5 text-slate-300" />
                                        {{ item.epp?.name || item.cloth?.name || item.clothe_name }}
                                    </TableCell>
                                    <TableCell class="text-center text-sm text-slate-600">{{ item.clothing_size || '—' }}</TableCell>
                                    <TableCell class="text-sm text-slate-600">{{ item.color?.name || '—' }}</TableCell>
                                    <TableCell class="text-center text-sm font-bold text-slate-700">{{ item.quantity }}</TableCell>
                                    <TableCell>
                                        <Button
                                            @click="openDirectReturnModal(item)"
                                            variant="outline"
                                            size="sm"
                                            class="bg-card h-8 gap-1 rounded-lg border text-[10px] font-black tracking-tighter uppercase transition-all hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                        >
                                            <RotateCcw class="h-3.5 w-3.5" /> DEVOLVER
                                        </Button>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="directDeliveries.length === 0">
                                    <TableCell colspan="5" class="h-24 text-center text-slate-400 italic">
                                        No hay entregas directas activas para esta persona.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Confirmar Devolución -->
        <Dialog v-model:open="isReturnModalOpen">
            <DialogContent class="overflow-hidden rounded-2xl border-none p-0 shadow-2xl sm:max-w-[500px]">
                <DialogHeader class="bg-rose-600 p-6 text-white">
                    <DialogTitle class="flex items-center gap-3 text-xl font-black">
                        <History class="h-6 w-6 text-rose-200" />
                        Confirmar Devolución
                    </DialogTitle>
                    <DialogDescription class="text-rose-100"> Estos items retornarán al stock general (Principal). </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 bg-white p-6">
                    <div class="space-y-3 rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <div v-for="(item, idx) in returnForm.items" :key="idx" class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2">
                                <Package class="h-4 w-4 text-slate-400" />
                                <span class="font-bold text-slate-700">{{ item.name }} ({{ item.size || 'U' }})</span>
                            </div>
                            <span class="rounded-lg border border-slate-100 bg-white px-2 py-0.5 font-black text-rose-600">{{ item.quantity }}</span>
                        </div>
                    </div>

                    <p class="text-center text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                        ¿Estás seguro de que este stock regresó al almacén?
                    </p>
                </div>

                <DialogFooter class="flex gap-3 border-t bg-slate-50 p-6 sm:justify-center">
                    <Button variant="ghost" @click="isReturnModalOpen = false" class="text-muted-foreground text-[10px] font-bold uppercase">
                        Cancelar
                    </Button>
                    <Button
                        @click="handleReturn"
                        class="bg-rose-600 px-8 text-[10px] font-black tracking-widest text-white uppercase shadow-lg hover:bg-rose-700"
                    >
                        Sí, Devolver a Principal
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Confirmar Devolución (entrega directa) -->
        <Dialog v-model:open="directReturnModal.open">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <RotateCcw class="h-5 w-5 text-rose-600" />
                        Confirmar Retorno de Stock
                    </DialogTitle>
                    <DialogDescription>
                        Selecciona a qué almacén regresa
                        <span class="font-bold text-slate-900">{{
                            directReturnModal.item?.epp?.name || directReturnModal.item?.cloth?.name || directReturnModal.item?.clothe_name
                        }}</span>
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label class="text-[10px] font-black text-slate-500 uppercase">Almacén de Destino (Sede)</Label>
                        <Select v-model="directReturnModal.headquarter_id">
                            <SelectTrigger class="h-11 w-full border-none bg-slate-50">
                                <SelectValue placeholder="Seleccionar sede..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="hq in headquarters ?? []" :key="hq.id" :value="String(hq.id)">
                                    {{ hq.name }} <span class="ml-1 text-[9px] opacity-50">({{ hq.business?.name }})</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="directReturnModal.open = false" class="text-[10px] font-bold uppercase">Cancelar</Button>
                    <Button
                        @click="confirmDirectReturn"
                        :disabled="!directReturnModal.headquarter_id"
                        class="bg-rose-600 text-[10px] font-black text-white uppercase hover:bg-rose-700"
                    >
                        Confirmar Retorno
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
