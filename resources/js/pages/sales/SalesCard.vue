<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import Input from '@/components/ui/input/Input.vue';
import { ref } from 'vue';
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { UserCheck } from 'lucide-vue-next';

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
    <Card class="border-none shadow-sm bg-white overflow-hidden py-0">
        <div class="bg-primary px-6 py-6 text-primary-foreground relative overflow-hidden">
            <div class="absolute top-0 right-0 -mr-4 -mt-4 h-24 w-24 rounded-full bg-white/10 blur-xl"></div>
            <div class="relative z-10 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur-md">
                        <UserCheck />
                    </div>
                    <div>
                        <h3 class="text-xl font-black uppercase tracking-tight leading-none">Identificación</h3>
                        <p class="text-[10px] font-bold text-white/70 uppercase tracking-widest mt-1">Escanee o ingrese el DNI del comensal</p>
                    </div>
                </div>

                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <Icon name="id-card" class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-primary" />
                        <input
                            v-model="dni"
                            type="text"
                            placeholder="Ingrese DNI (8 dígitos)..."
                            class="w-full h-14 pl-12 pr-4 bg-white text-slate-900 font-black text-xl placeholder:text-slate-300 placeholder:text-sm placeholder:font-bold rounded-2xl border-none focus:ring-4 focus:ring-white/20 transition-all shadow-inner"
                            @keyup.enter="triggerSearch"
                            maxlength="8"
                        />
                    </div>
                    <Button @click="triggerSearch" class="h-14 w-14 rounded-2xl bg-white text-primary hover:bg-slate-50 transition-all shadow-lg active:scale-95">
                        <Icon name="search" size="24" />
                    </Button>
                </div>
            </div>
        </div>

        <CardContent class="p-6">
            <div v-if="dinnerFound && dinnerFound.id" class="grid grid-cols-1 md:grid-cols-2 gap-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                <!-- Comensal Info -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <div class="h-1 w-8 bg-emerald-500 rounded-full"></div>
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Datos del Comensal</span>
                    </div>
                    
                    <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 flex items-start gap-4 transition-all hover:bg-white hover:shadow-md hover:border-emerald-200 group">
                        <div class="h-14 w-14 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600 transition-colors group-hover:bg-emerald-500 group-hover:text-white">
                            <Icon name="user" size="24" />
                        </div>
                        <div class="flex-1 space-y-1">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Nombre Completo</p>
                            <p class="text-base font-black text-slate-900 leading-tight uppercase">{{ dinnerFound.name }}</p>
                            <div class="flex items-center gap-3 mt-2">
                                <Badge variant="secondary" class="bg-emerald-100 text-emerald-700 font-bold text-[10px]">
                                    <Icon name="id-card" size="10" class="mr-1" /> {{ dinnerFound.dni }}
                                </Badge>
                                <Badge v-if="dinnerFound.phone" variant="secondary" class="bg-slate-200 text-slate-600 font-bold text-[10px]">
                                    <Icon name="phone" size="10" class="mr-1" /> {{ dinnerFound.phone }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subdealership Info -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <div class="h-1 w-8 bg-blue-500 rounded-full"></div>
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Empresa / Concesionaria</span>
                    </div>
                    
                    <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 flex items-start gap-4 transition-all hover:bg-white hover:shadow-md hover:border-blue-200 group">
                        <div class="h-14 w-14 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 transition-colors group-hover:bg-blue-500 group-hover:text-white">
                            <Icon name="building" size="24" />
                        </div>
                        <div class="flex-1 space-y-1">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Razón Social</p>
                            <p class="text-base font-black text-slate-900 leading-tight uppercase">{{ subdealership.name }}</p>
                            <div class="flex items-center gap-3 mt-2">
                                <Badge variant="secondary" class="bg-blue-100 text-blue-700 font-bold text-[10px]">
                                    <Icon name="hash" size="10" class="mr-1" /> RUC {{ subdealership.ruc }}
                                </Badge>
                                <Badge v-if="subdealership.phone" variant="secondary" class="bg-slate-200 text-slate-600 font-bold text-[10px]">
                                    <Icon name="phone" size="10" class="mr-1" /> {{ subdealership.phone }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-12 text-center space-y-4">
                <div class="h-20 w-20 rounded-full bg-slate-50 flex items-center justify-center text-slate-200">
                    <Icon name="user-check" size="48" stroke-width="1.5" />
                </div>
                <div class="space-y-1">
                    <p class="text-lg font-bold text-slate-900 uppercase">Esperando Identificación</p>
                    <p class="text-sm text-slate-500 max-w-[280px]">Ingrese el DNI para verificar los datos del comensal y registrar el servicio.</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>

<style scoped>
input:focus {
    outline: none;
}
</style>
