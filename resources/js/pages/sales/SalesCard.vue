<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { UserCheck } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    services: {
        type: Array as () => any[],
        required: true,
    },
    cafeSelected: {
        type: Number,
        required: true,
    },
    saletypeSelected: {
        type: Number,
        required: true,
    },
    servicesSelectedToSale: {
        type: Array as () => any[],
        required: true,
    },
    receiptType: {
        type: Number,
        required: true,
    },
    doublePrice: {
        type: Boolean,
    },
    dateSelected: {
        type: String,
        default: '',
    },
    dinnerFound: {
        type: Object,
        default: () => ({}),
    },
    subdealership: {
        type: Object,
        default: () => ({}),
    },
});

const emits = defineEmits(['handleShowAlert', 'showDialog', 'updateDni', 'saveSale']);

const dni = ref('');

const cleanInput = () => {
    dni.value = '';
};

const triggerSearch = () => {
    emits('updateDni', dni.value);
};

defineExpose({
    cleanInput,
});
</script>

<template>
    <Card class="overflow-hidden border-none bg-white py-0 shadow-sm">
        <div class="bg-primary text-primary-foreground relative overflow-hidden px-6 py-6">
            <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 rounded-full bg-white/10 blur-xl"></div>
            <div class="relative z-10 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20 backdrop-blur-md">
                        <UserCheck />
                    </div>
                    <div>
                        <h3 class="text-xl leading-none font-black tracking-tight uppercase">Identificación</h3>
                        <p class="mt-1 text-[10px] font-bold tracking-widest text-white/70 uppercase">Escanee o ingrese el DNI del comensal</p>
                    </div>
                </div>

                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <Icon name="id-card" class="text-primary absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2" />
                        <input
                            v-model="dni"
                            type="text"
                            placeholder="Ingrese DNI (8 dígitos)..."
                            class="h-14 w-full rounded-2xl border-none bg-white pr-4 pl-12 text-xl font-black text-slate-900 shadow-inner transition-all placeholder:text-sm placeholder:font-bold placeholder:text-slate-300 focus:ring-4 focus:ring-white/20"
                            @keyup.enter="triggerSearch"
                            maxlength="8"
                        />
                    </div>
                    <Button
                        @click="triggerSearch"
                        class="text-primary h-14 w-14 rounded-2xl bg-white shadow-lg transition-all hover:bg-slate-50 active:scale-95"
                    >
                        <Icon name="search" size="24" />
                    </Button>
                </div>
            </div>
        </div>

        <CardContent class="px-4 py-3">
            <!-- Result: found dinner -->
            <div
                v-if="dinnerFound && dinnerFound.id"
                class="animate-in fade-in slide-in-from-bottom-2 flex items-center gap-0 overflow-hidden rounded-xl border border-slate-100 bg-slate-50 duration-300"
            >
                <!-- Comensal -->
                <div class="flex flex-1 items-center gap-3 px-4 py-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                        <Icon name="user" size="16" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-black leading-tight text-slate-900 uppercase">{{ dinnerFound.name }}</p>
                        <div class="mt-0.5 flex items-center gap-2">
                            <Badge variant="secondary" class="h-4 bg-emerald-100 px-1.5 text-[9px] font-bold text-emerald-700">
                                <Icon name="id-card" size="9" class="mr-0.5" />{{ dinnerFound.dni }}
                            </Badge>
                            <Badge v-if="dinnerFound.phone" variant="secondary" class="h-4 bg-slate-200 px-1.5 text-[9px] font-bold text-slate-500">
                                <Icon name="phone" size="9" class="mr-0.5" />{{ dinnerFound.phone }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="h-12 w-px shrink-0 bg-slate-200"></div>

                <!-- Subdealership -->
                <div class="flex flex-1 items-center gap-3 px-4 py-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600">
                        <Icon name="building" size="16" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-black leading-tight text-slate-900 uppercase">{{ subdealership.name || '—' }}</p>
                        <div class="mt-0.5 flex items-center gap-2">
                            <Badge v-if="subdealership.ruc" variant="secondary" class="h-4 bg-blue-100 px-1.5 text-[9px] font-bold text-blue-700">
                                RUC {{ subdealership.ruc }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="flex items-center justify-center gap-3 py-4 text-slate-300">
                <Icon name="user-check" size="20" stroke-width="1.5" />
                <p class="text-xs font-semibold text-slate-400">Ingrese el DNI del comensal para continuar</p>
            </div>
        </CardContent>
    </Card>
</template>

<style scoped>
input:focus {
    outline: none;
}
</style>
