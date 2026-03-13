<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription, DialogClose 
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Trash2, Plus, Search, Loader2, Package, User, Building2, Truck, Shirt, X } from 'lucide-vue-next';
import axios from 'axios';

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
    items: [] as any[]
});

const isSubmitting = ref(false);
const itemSearch = ref('');
const searchResults = ref<any[]>([]);
const isSearching = ref(false);
const selectedType = ref<'cloth' | 'epp'>('epp');

// Filtrar personal por unidad seleccionada
const filteredStaff = computed(() => {
    if (!form.value.unit_id) return [];
    return props.staff.filter(s => {
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
            params: { type: selectedType.value, query }
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
    const exists = form.value.items.find(i => i.stockable_id === item.id && i.stockable_type === selectedType.value);
    if (exists) {
        exists.quantity++;
    } else {
        form.value.items.push({
            stockable_id: item.id,
            stockable_type: selectedType.value,
            name: item.name,
            quantity: 1,
            size: ''
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
        onSuccess: () => {
            emit('update:open', false);
            resetForm();
        },
        onFinish: () => isSubmitting.value = false,
        preserveScroll: true
    });
};

const resetForm = () => {
    form.value = {
        unit_id: '',
        staff_id: '',
        notes: '',
        items: []
    };
};

watch(() => props.open, (val) => {
    if (val) resetForm();
});

</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-[800px] p-0 overflow-hidden border-none rounded-2xl shadow-2xl">
            <DialogHeader class="p-6 bg-slate-900 text-white relative">
                <DialogTitle class="text-xl font-black flex items-center gap-3">
                    <Truck class="h-6 w-6 text-indigo-400" />
                    Generar Envío a Unidad
                </DialogTitle>
                <DialogDescription class="text-slate-400">
                    Registra la salida de items desde el almacén principal hacia una unidad específica.
                </DialogDescription>
                <DialogClose class="absolute right-4 top-4 text-white hover:opacity-100 opacity-60">
                    <X class="h-5 w-5" />
                </DialogClose>
            </DialogHeader>

            <div class="p-6 space-y-6 bg-white overflow-y-auto max-h-[70vh]">
                <!-- Step 1: Destination -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label class="text-[10px] font-black uppercase text-slate-500">Unidad de Destino</Label>
                        <Select v-model="form.unit_id">
                            <SelectTrigger class="h-11 bg-slate-50 border-none">
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
                        <Label class="text-[10px] font-black uppercase text-slate-500">Asignar a Persona (Opcional)</Label>
                        <Select v-model="form.staff_id" :disabled="!form.unit_id">
                            <SelectTrigger class="h-11 bg-slate-50 border-none">
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
                        <div class="flex-none w-32">
                            <Label class="text-[10px] font-black uppercase text-slate-500">Tipo</Label>
                            <Select v-model="selectedType">
                                <SelectTrigger class="h-10 bg-slate-50 border-none">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="epp">EPP</SelectItem>
                                    <SelectItem value="cloth">Ropa</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="flex-1 relative">
                            <Label class="text-[10px] font-black uppercase text-slate-500">Buscar Item</Label>
                            <div class="relative">
                                <Search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                                <Input 
                                    v-model="itemSearch" 
                                    placeholder="Nombre del EPP o prenda..." 
                                    class="pl-9 h-10 bg-slate-50 border-none"
                                />
                                <div v-if="isSearching" class="absolute right-3 top-3">
                                    <Loader2 class="h-4 w-4 animate-spin text-indigo-600" />
                                </div>
                            </div>

                            <!-- Search Results -->
                            <div v-if="searchResults.length > 0" class="absolute z-50 w-full mt-1 bg-white border rounded-xl shadow-2xl max-h-48 overflow-y-auto border-slate-100">
                                <div 
                                    v-for="item in searchResults" 
                                    :key="item.id"
                                    @click="addItem(item)"
                                    class="p-3 hover:bg-indigo-50 cursor-pointer flex items-center justify-between text-sm transition-colors border-b last:border-none"
                                >
                                    <span class="font-bold text-slate-700">{{ item.name }}</span>
                                    <Plus class="h-4 w-4 text-indigo-400" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="rounded-xl border border-slate-100 overflow-hidden shadow-sm">
                        <Table>
                            <TableHeader class="bg-slate-50">
                                <TableRow>
                                    <TableHead class="text-[10px] font-black uppercase py-2">Item</TableHead>
                                    <TableHead class="text-[10px] font-black uppercase py-2 w-24">Talla</TableHead>
                                    <TableHead class="text-[10px] font-black uppercase py-2 w-24">Cantidad</TableHead>
                                    <TableHead class="w-12"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(item, index) in form.items" :key="index" class="hover:bg-slate-50 group">
                                    <TableCell class="p-3">
                                        <div class="flex items-center gap-2">
                                            <div class="h-7 w-7 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
                                                <Package v-if="item.stockable_type === 'epp'" class="h-3.5 w-3.5" />
                                                <Shirt v-else class="h-3.5 w-3.5" />
                                            </div>
                                            <span class="text-sm font-bold text-slate-700">{{ item.name }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="p-3">
                                        <Input v-model="item.size" placeholder="Talla" class="h-8 text-xs bg-white" />
                                    </TableCell>
                                    <TableCell class="p-3">
                                        <Input type="number" v-model="item.quantity" min="1" class="h-8 text-xs bg-white text-center" />
                                    </TableCell>
                                    <TableCell class="p-3 text-right">
                                        <Button variant="ghost" size="icon" @click="removeItem(index)" class="h-8 w-8 text-slate-300 hover:text-rose-500">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="form.items.length === 0">
                                    <TableCell colspan="4" class="h-32 text-center text-slate-400 italic text-xs">
                                        No has seleccionado items para enviar.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <div class="space-y-2 pt-4">
                    <Label class="text-[10px] font-black uppercase text-slate-500">Observaciones / Notas</Label>
                    <textarea 
                        v-model="form.notes"
                        class="flex min-h-[80px] w-full rounded-xl border-none bg-slate-50 px-3 py-2 text-sm placeholder:text-slate-400 focus:ring-1 focus:ring-indigo-500"
                        placeholder="Escribe alguna nota adicional..."
                    ></textarea>
                </div>
            </div>

            <DialogFooter class="p-6 bg-slate-50 border-t flex items-center justify-between sm:justify-between">
                <div class="text-[10px] font-black uppercase text-slate-400 tracking-widest">
                    {{ form.items.length }} Items en lista
                </div>
                <div class="flex gap-3">
                    <Button variant="ghost" @click="$emit('update:open', false)" class="font-bold uppercase text-[10px] tracking-widest text-slate-500">
                        Cancelar
                    </Button>
                    <Button 
                        @click="handleSubmit" 
                        :disabled="isSubmitting || !form.unit_id || form.items.length === 0"
                        class="bg-slate-900 hover:bg-black text-white px-8 font-black uppercase text-[10px] tracking-widest shadow-lg"
                    >
                        {{ isSubmitting ? 'Enviando...' : 'Confirmar Envío' }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
