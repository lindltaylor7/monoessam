<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertCircle, Loader2, Package, Plus, Search, Shirt, Trash2, Truck, X } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    units: any[];
    staff: any[];
    clothes: any[];
    epps: any[];
}>();

const emit = defineEmits(['update:open']);

const form = ref({
    unit_id: '',
    staff_id: '',
    notes: '',
    items: [] as any[],
});

const isSubmitting = ref(false);
const itemSearch = ref('');
const searchResults = ref<any[]>([]);
const isSearching = ref(false);
const selectedType = ref<'cloth' | 'epp'>('epp');

// Filtrar personal por unidad seleccionada
const filteredStaff = computed(() => {
    if (!form.value.unit_id) return [];
    return props.staff.filter((s) => {
        // Asumiendo que s.staffable tiene unit_id o similar
        // En este sistema Cafe pertenece a Unit.
        const cafe = s.cafe;
        return cafe && String(cafe.unit_id) === form.value.unit_id;
    });
});

const searchItems = async (query: string) => {
    if (!query || query.length < 2) {
        searchResults.value = [];
        return;
    }
    isSearching.value = true;
    try {
        const response = await axios.get(route('inventory.items.search'), {
            params: { type: selectedType.value, query },
        });
        searchResults.value = response.data;
    } catch (e) {
        console.error(e);
    } finally {
        isSearching.value = false;
    }
};

let timeout: any = null;
watch(itemSearch, (val) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => searchItems(val), 300);
});

const addItem = (item: any) => {
    if (item.stock <= 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Sin Stock',
            text: 'Este item no tiene stock disponible en el almacén principal.',
        });
        return;
    }
    const exists = form.value.items.find((i) => i.stockable_id === item.id && i.stockable_type === selectedType.value);
    if (exists) {
        exists.quantity++;
    } else {
        form.value.items.push({
            stockable_id: item.id,
            stockable_type: selectedType.value,
            name: item.name,
            quantity: 1,
            size: '',
            color_id: null,
            color_name: '',
            color_hex: '',
            available_stock: item.stock,
            stock_details: item.stock_details || [],
            stock_options: item.stock_options || [],
        });
    }
    itemSearch.value = '';
    searchResults.value = [];
};

const removeItem = (index: number) => {
    form.value.items.splice(index, 1);
};

const handleSubmit = () => {
    if (!form.value.unit_id || form.value.items.length === 0) return;

    isSubmitting.value = true;
    router.post(route('inventory.transfer.store'), form.value, {
        onSuccess: (page) => {
            const flash = page.props.flash as any;
            if (flash && flash.error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Envío',
                    text: flash.error,
                });
            } else {
                emit('update:open', false);
                setTimeout(() => resetForm(), 300);
                Swal.fire({
                    icon: 'success',
                    title: 'Envío Registrado',
                    text: 'Los ítems se enviaron correctamente.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            }
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors).join('\n');
            Swal.fire({
                icon: 'error',
                title: 'No se pudo generar el envío',
                text: errorMsg,
            });
        },
        onFinish: () => (isSubmitting.value = false),
        preserveScroll: true,
    });
};

const resetForm = () => {
    form.value = {
        unit_id: '',
        staff_id: '',
        notes: '',
        items: [],
    };
};

watch(
    () => props.open,
    (val) => {
        if (val) resetForm();
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="overflow-hidden rounded-2xl border-none p-0 shadow-2xl sm:max-w-[800px]">
            <DialogHeader class="relative bg-slate-900 p-6 text-white">
                <DialogTitle class="flex items-center gap-3 text-xl font-black">
                    <Truck class="h-6 w-6 text-indigo-400" />
                    Generar Envío a Unidad
                </DialogTitle>
                <DialogDescription class="text-slate-400">
                    Registra la salida de items desde el almacén principal hacia una unidad específica.
                </DialogDescription>
                <DialogClose class="absolute top-4 right-4 text-white opacity-60 hover:opacity-100">
                    <X class="h-5 w-5" />
                </DialogClose>
            </DialogHeader>

            <div class="max-h-[70vh] space-y-6 overflow-y-auto bg-white p-6">
                <!-- Step 1: Destination -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label class="text-[10px] font-black text-slate-500 uppercase">Unidad de Destino</Label>
                        <Select v-model="form.unit_id">
                            <SelectTrigger class="h-11 border-none bg-slate-50">
                                <SelectValue placeholder="Seleccionar Unidad" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="unit in units" :key="unit.id" :value="String(unit.id)">
                                    {{ unit.mine?.name }} — {{ unit.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label class="text-[10px] font-black text-slate-500 uppercase">Asignar a Persona (Opcional)</Label>
                        <Select v-model="form.staff_id" :disabled="!form.unit_id">
                            <SelectTrigger class="h-11 border-none bg-slate-50">
                                <SelectValue placeholder="Seleccionar Personal" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in filteredStaff" :key="s.id" :value="String(s.id)">
                                    {{ s.name }}
                                </SelectItem>
                                <SelectItem v-if="filteredStaff.length === 0" value="none" disabled>No hay personal en esta unidad</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Step 2: Add Items -->
                <div class="space-y-4 border-t pt-4">
                    <div class="flex items-end gap-2">
                        <div class="w-32 flex-none">
                            <Label class="text-[10px] font-black text-slate-500 uppercase">Tipo</Label>
                            <Select v-model="selectedType">
                                <SelectTrigger class="h-10 border-none bg-slate-50">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="epp">EPP</SelectItem>
                                    <SelectItem value="cloth">Ropa</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="relative flex-1">
                            <Label class="text-[10px] font-black text-slate-500 uppercase">Buscar Item</Label>
                            <div class="relative">
                                <Search class="absolute top-3 left-3 h-4 w-4 text-slate-400" />
                                <Input v-model="itemSearch" placeholder="Nombre del EPP o prenda..." class="h-10 border-none bg-slate-50 pl-9" />
                                <div v-if="isSearching" class="absolute top-3 right-3">
                                    <Loader2 class="h-4 w-4 animate-spin text-indigo-600" />
                                </div>
                            </div>

                            <!-- Search Results -->
                            <div
                                v-if="searchResults.length > 0"
                                class="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-100 bg-white shadow-2xl"
                            >
                                <div
                                    v-for="item in searchResults"
                                    :key="item.id"
                                    @click="addItem(item)"
                                    class="flex cursor-pointer items-center justify-between border-b p-3 text-sm transition-colors last:border-none hover:bg-slate-50"
                                    :class="{ 'cursor-not-allowed opacity-50 hover:bg-white': item.stock <= 0 }"
                                >
                                    <div class="flex flex-col gap-1">
                                        <span class="font-bold text-slate-700">{{ item.name }}</span>
                                        <div class="flex flex-wrap gap-1 text-[10px]">
                                            <span
                                                v-if="item.stock > 0"
                                                class="rounded-full border border-emerald-100 bg-emerald-50 px-2 py-0.5 font-bold text-emerald-600"
                                            >
                                                Stock: {{ item.stock }}
                                            </span>
                                            <span
                                                v-else
                                                class="flex items-center gap-1 rounded-full border border-rose-100 bg-rose-50 px-2 py-0.5 font-bold text-rose-600"
                                            >
                                                <AlertCircle class="h-3 w-3" /> Sin Stock Principal
                                            </span>
                                            <span
                                                v-for="(detalle, i) in item.stock_details"
                                                :key="i"
                                                class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-medium text-slate-500"
                                            >
                                                {{ detalle }}
                                            </span>
                                        </div>
                                    </div>
                                    <Button
                                        v-if="item.stock > 0"
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 w-8 shrink-0 p-0 text-indigo-600 hover:bg-indigo-50"
                                    >
                                        <Plus class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="overflow-hidden rounded-xl border border-slate-100 shadow-sm">
                        <Table>
                            <TableHeader class="bg-slate-50">
                                <TableRow>
                                    <TableHead class="py-2 text-[10px] font-black uppercase">Item</TableHead>
                                    <TableHead class="w-48 py-2 text-[10px] font-black uppercase">Talla y Color</TableHead>
                                    <TableHead class="w-24 py-2 text-[10px] font-black uppercase">Cantidad</TableHead>
                                    <TableHead class="w-12"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(item, index) in form.items" :key="index" class="group hover:bg-slate-50">
                                    <TableCell class="p-3">
                                        <div class="flex items-center gap-2">
                                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                                <Package v-if="item.stockable_type === 'epp'" class="h-3.5 w-3.5" />
                                                <Shirt v-else class="h-3.5 w-3.5" />
                                            </div>
                                            <div class="flex min-w-0 flex-col gap-0.5">
                                                <span class="line-clamp-1 block truncate text-xs font-bold text-slate-700" :title="item.name">{{
                                                    item.name
                                                }}</span>
                                                <span class="w-fit rounded bg-emerald-50 px-1.5 py-0.5 text-[9px] font-bold text-emerald-600 italic">
                                                    Stock Disp: {{ item.available_stock || 0 }}
                                                </span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell class="p-3">
                                        <div v-if="item.stock_options && item.stock_options.length > 0">
                                            <Select
                                                :model-value="item.size + '|' + (item.color_id || '')"
                                                @update:model-value="
                                                    (val: any) => {
                                                        const sVal = String(val);
                                                        const [size, colorId] = sVal.split('|');
                                                        item.size = size;
                                                        item.color_id = colorId ? Number(colorId) : null;
                                                        const opt = item.stock_options.find(
                                                            (o: any) => o.value === size && String(o.color_id || '') === String(colorId || ''),
                                                        );
                                                        if (opt) {
                                                            item.color_name = opt.color_name;
                                                            item.color_hex = opt.color_hex;
                                                            item.available_stock = opt.quantity;
                                                            if (item.quantity > opt.quantity) item.quantity = opt.quantity;
                                                        }
                                                    }
                                                "
                                            >
                                                <SelectTrigger class="h-8 w-full min-w-[160px] bg-white text-xs text-slate-700">
                                                    <SelectValue placeholder="Elegir Opción" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="opt in item.stock_options"
                                                        :key="opt.label"
                                                        :value="opt.value + '|' + (opt.color_id || '')"
                                                    >
                                                        <div class="flex items-center gap-2">
                                                            <div
                                                                v-if="opt.color_hex"
                                                                class="h-2 w-2 rounded-full"
                                                                :style="{ backgroundColor: opt.color_hex }"
                                                            ></div>
                                                            <span>{{ opt.label }} ({{ opt.quantity }})</span>
                                                        </div>
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div v-else class="rounded bg-rose-50 p-1 text-center text-[10px] font-bold text-rose-500 italic">
                                            Sin Opciones de Stock
                                        </div>
                                    </TableCell>
                                    <TableCell class="p-3">
                                        <Input
                                            type="number"
                                            v-model="item.quantity"
                                            min="1"
                                            :max="item.available_stock"
                                            class="h-8 bg-white text-center text-xs"
                                        />
                                    </TableCell>
                                    <TableCell class="p-3 text-right">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="removeItem(index)"
                                            class="h-8 w-8 text-slate-300 hover:text-rose-500"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="form.items.length === 0">
                                    <TableCell colspan="4" class="h-32 text-center text-xs text-slate-400 italic">
                                        No has seleccionado items para enviar.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <div class="space-y-2 pt-4">
                    <Label class="text-[10px] font-black text-slate-500 uppercase">Observaciones / Notas</Label>
                    <textarea
                        v-model="form.notes"
                        class="flex min-h-[80px] w-full rounded-xl border-none bg-slate-50 px-3 py-2 text-sm placeholder:text-slate-400 focus:ring-1 focus:ring-indigo-500"
                        placeholder="Escribe alguna nota adicional..."
                    ></textarea>
                </div>
            </div>

            <DialogFooter class="flex items-center justify-between border-t bg-slate-50 p-6 sm:justify-between">
                <div class="text-[10px] font-black tracking-widest text-slate-400 uppercase">{{ form.items.length }} Items en lista</div>
                <div class="flex gap-3">
                    <Button
                        variant="ghost"
                        @click="$emit('update:open', false)"
                        class="text-[10px] font-bold tracking-widest text-slate-500 uppercase"
                    >
                        Cancelar
                    </Button>
                    <Button
                        @click="handleSubmit"
                        :disabled="isSubmitting || !form.unit_id || form.items.length === 0"
                        class="bg-slate-900 px-8 text-[10px] font-black tracking-widest text-white uppercase shadow-lg hover:bg-black"
                    >
                        {{ isSubmitting ? 'Enviando...' : 'Confirmar Envío' }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
