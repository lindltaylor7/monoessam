<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import axios from 'axios';
import { Camera, FileText, History, Package } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface StaffLike {
    id: number;
    name: string;
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
}

const props = defineProps<{
    open: boolean;
    staff: StaffLike | null;
}>();

defineEmits(['update:open']);

const eppOptions = ref<any[]>([]);
const loadEppOptions = async () => {
    try {
        const response = await axios.get(route('inventory.items.search'), {
            params: { type: 'epp', query: '' },
        });
        eppOptions.value = response.data;
    } catch (e) {
        console.error(e);
    }
};

watch(
    () => props.open,
    (val) => {
        if (val) loadEppOptions();
    },
);

const uploadEvidence = (histId: number, file: File) => {
    router.post(
        route('inventory.history.evidence', histId),
        { evidence_image: file },
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
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-[700px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-xl font-black">
                    <History class="h-5 w-5 text-indigo-600" />
                    Historial de Entregas
                </DialogTitle>
                <DialogDescription>Entregas registradas para {{ staff?.name }}</DialogDescription>
            </DialogHeader>

            <div v-if="staff" class="space-y-4">
                <div class="flex justify-end">
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
                                    <span class="rounded-md bg-indigo-50 px-2 py-0.5 text-[10px] font-black text-indigo-600 uppercase">{{
                                        hist.reason
                                    }}</span>
                                    <span class="text-xs font-medium text-slate-500">{{ new Date(hist.created_at).toLocaleString() }}</span>
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
                                        eppOptions.find((e) => String(e.id) === String(item.epp_id))?.name || item.epp_name || `EPP #${item.epp_id}`
                                    }}</span>
                                    <span
                                        v-if="item.condition"
                                        :class="[
                                            'rounded-full px-2 py-0.5 text-[8px] font-black uppercase',
                                            item.condition === 'Nuevo' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700',
                                        ]"
                                    >
                                        {{ item.condition }}
                                    </span>
                                </div>
                                <div class="mt-1 flex gap-3 text-[10px] text-slate-500">
                                    <span class="font-medium">Cant: <strong class="text-indigo-600">{{ item.quantity }}</strong></span>
                                    <span class="font-medium">Talla: <strong class="text-slate-700">{{ item.size || '-' }}</strong></span>
                                </div>
                            </div>
                        </div>
                        <div v-if="hist.evidence_image" class="mt-2">
                            <div class="mb-2 text-[10px] font-bold text-slate-400 uppercase">Evidencia Fotográfica:</div>
                            <a :href="hist.evidence_image" target="_blank" class="block w-fit overflow-hidden rounded-xl border border-slate-200">
                                <img :src="hist.evidence_image" class="h-20 w-auto object-cover transition-transform hover:scale-105" />
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
                <div v-else class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 py-10 text-center text-slate-400">
                    <Package class="mx-auto mb-2 h-8 w-8 opacity-20" />
                    <p class="text-[11px] font-black tracking-widest uppercase">No hay historial de asignaciones.</p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
