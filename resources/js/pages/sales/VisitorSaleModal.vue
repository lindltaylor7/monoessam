<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { UserRoundPlus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Service  { id: number; name: string; code: string; pivot?: { price: number } }

const props = defineProps<{
    open: boolean;
    services: Service[];
    cafeId: number;
    saletypeId: number;
    receiptType: number;
    date: string;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'success', sales: any[]): void;
}>();

const currentUser = computed(() => (usePage<any>().props.auth.user as any));

const form = ref({
    name:       '',
    dni:        '',
    service_id: '' as string | number,
    price:      '' as string | number,
});
const errors = ref<Record<string, string>>({});
const processing = ref(false);

const selectedService = computed(() =>
    props.services.find((s) => s.id === Number(form.value.service_id)),
);

watch(selectedService, (s) => {
    if (s) form.value.price = s.pivot?.price ?? '';
});

watch(() => props.open, (v) => {
    if (v) {
        form.value = { name: '', dni: '', service_id: '', price: '' };
        errors.value = {};
    }
});

const validate = () => {
    const e: Record<string, string> = {};
    if (!form.value.name.trim())                                   e.name       = 'Requerido';
    if (!form.value.dni || String(form.value.dni).length !== 8)    e.dni        = 'DNI debe tener 8 dígitos';
    if (!form.value.service_id)                                    e.service_id = 'Requerido';
    if (!form.value.price || Number(form.value.price) <= 0)        e.price      = 'Ingrese un monto válido';
    if (!props.cafeId)                                             e.cafe       = 'Seleccione una cafetería primero';
    if (!props.date)                                               e.date       = 'Seleccione una fecha primero';
    errors.value = e;
    return Object.keys(e).length === 0;
};

const submit = async () => {
    if (!validate()) return;
    processing.value = true;
    try {
        const res = await axios.post('/sales/visitor', {
            name:         form.value.name,
            dni:          form.value.dni,
            mine_id:      currentUser.value?.mine_id || null,
            business_id:  currentUser.value?.business_id || null,
            service_id:   form.value.service_id,
            price:        form.value.price,
            cafe_id:      props.cafeId,
            sale_type_id: props.saletypeId,
            receipt_type: props.receiptType,
            date:         props.date,
        });
        emit('success', res.data.sales || []);
        emit('update:open', false);
    } catch (err: any) {
        const msg = err.response?.data?.message || 'Error al registrar la venta.';
        errors.value.general = msg;
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-[520px]">

            <!-- Header -->
            <div class="relative bg-violet-600 px-6 py-6 text-white">
                <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 rounded-full bg-white/10 blur-xl"></div>
                <DialogHeader class="relative z-10">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20 backdrop-blur-md">
                            <UserRoundPlus class="h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-xl font-black tracking-tight text-white uppercase">Venta a Visitante</DialogTitle>
                            <p class="mt-0.5 text-[10px] font-bold tracking-widest text-white/70 uppercase">Registro de servicio para persona externa</p>
                        </div>
                    </div>
                </DialogHeader>
            </div>

            <form @submit.prevent="submit" class="space-y-5 bg-white px-6 py-6">

                <!-- Alerta si falta café o fecha -->
                <div v-if="!cafeId || !date" class="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <Icon name="triangle-alert" size="16" class="shrink-0 text-amber-500" />
                    <p class="text-xs font-semibold text-amber-700">
                        {{ !cafeId ? 'Seleccione una cafetería' : 'Seleccione una fecha' }} antes de registrar la venta.
                    </p>
                </div>

                <!-- Error general -->
                <div v-if="errors.general" class="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                    <Icon name="circle-x" size="16" class="shrink-0 text-red-500" />
                    <p class="text-xs font-semibold text-red-700">{{ errors.general }}</p>
                </div>

                <!-- Nombre y DNI -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label class="text-[11px] font-bold tracking-wider text-slate-400 uppercase">Nombre completo</Label>
                        <Input v-model="form.name" placeholder="Ej. Juan Pérez"
                            :class="{ 'border-red-400': errors.name }"
                            class="h-10 border-slate-200 text-sm" />
                        <p v-if="errors.name" class="text-[10px] font-semibold text-red-500">{{ errors.name }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-[11px] font-bold tracking-wider text-slate-400 uppercase">DNI</Label>
                        <Input v-model="form.dni" placeholder="12345678" maxlength="8"
                            :class="{ 'border-red-400': errors.dni }"
                            class="h-10 border-slate-200 text-sm" />
                        <p v-if="errors.dni" class="text-[10px] font-semibold text-red-500">{{ errors.dni }}</p>
                    </div>
                </div>

                <!-- Empresa y Mina (auto desde el usuario) -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label class="text-[11px] font-bold tracking-wider text-slate-400 uppercase">Empresa</Label>
                        <div class="flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3">
                            <Icon name="building-2" size="14" class="shrink-0 text-slate-400" />
                            <span class="truncate text-sm font-semibold text-slate-700">
                                {{ currentUser?.business?.name || '—' }}
                            </span>
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-[11px] font-bold tracking-wider text-slate-400 uppercase">Mina</Label>
                        <div class="flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3">
                            <Icon name="mountain" size="14" class="shrink-0 text-slate-400" />
                            <span class="truncate text-sm font-semibold text-slate-700">
                                {{ currentUser?.mine?.name || '—' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100"></div>

                <!-- Servicio y Precio -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label class="text-[11px] font-bold tracking-wider text-slate-400 uppercase">Servicio</Label>
                        <Select v-model="form.service_id">
                            <SelectTrigger :class="{ 'border-red-400': errors.service_id }" class="h-10 border-slate-200 text-sm">
                                <SelectValue placeholder="Seleccionar" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in services" :key="s.id" :value="s.id.toString()">
                                    {{ s.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.service_id" class="text-[10px] font-semibold text-red-500">{{ errors.service_id }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-[11px] font-bold tracking-wider text-slate-400 uppercase">Costo (S/.)</Label>
                        <Input v-model="form.price" type="number" min="0" step="0.01" placeholder="0.00"
                            :class="{ 'border-red-400': errors.price }"
                            class="h-10 border-slate-200 text-sm" />
                        <p v-if="errors.price" class="text-[10px] font-semibold text-red-500">{{ errors.price }}</p>
                    </div>
                </div>

                <!-- Resumen del servicio seleccionado -->
                <div v-if="selectedService" class="flex items-center justify-between rounded-xl border border-violet-100 bg-violet-50 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Icon name="utensils" size="14" class="text-violet-500" />
                        <span class="text-xs font-bold text-violet-700">{{ selectedService.name }}</span>
                        <span class="rounded bg-violet-100 px-1.5 py-0.5 text-[10px] font-mono font-bold text-violet-500">
                            {{ selectedService.code }}
                        </span>
                    </div>
                    <span class="text-sm font-black text-violet-700">S/. {{ Number(form.price || 0).toFixed(2) }}</span>
                </div>

                <DialogFooter class="gap-2 border-t border-slate-100 pt-4">
                    <Button type="button" variant="ghost" class="border border-slate-200" @click="$emit('update:open', false)">
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="processing || !cafeId || !date"
                        class="flex-1 bg-violet-600 shadow-sm hover:bg-violet-700">
                        <Icon v-if="processing" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
                        Registrar Visitante
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
