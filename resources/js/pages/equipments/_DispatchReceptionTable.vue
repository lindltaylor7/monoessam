<script setup lang="ts">
import { CheckCircle2, Clock, Laptop, UtensilsCrossed } from 'lucide-vue-next';

interface DispatchRow {
    id: number;
    dispatch_number: string;
    equipable_type: 'computer' | 'kitchen';
    quantity: number;
    equipment_name: string;
    equipment_brand: string | null;
    equipment_model: string | null;
    origin_name: string;
    dispatched_by: string;
    dispatched_at: string;
    received_at: string | null;
    received_by: string | null;
}

defineProps<{
    dispatches:  DispatchRow[];
    confirmId:   number | null;
    processing:  boolean;
}>();

const emit = defineEmits<{
    confirm: [id: number];
    cancel:  [];
    receive: [id: number];
}>();
</script>

<template>
    <div class="overflow-x-auto rounded-md border">
        <table class="w-full text-sm">
            <thead class="bg-muted/40 text-muted-foreground">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold">N° Despacho</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">Equipo</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold">Cant.</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">Origen</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">Despachado</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">Estado</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr
                    v-for="d in dispatches"
                    :key="d.id"
                    :class="d.received_at ? 'bg-emerald-50/50 dark:bg-emerald-900/10' : ''"
                >
                    <!-- N° Despacho -->
                    <td class="px-3 py-2.5">
                        <span class="font-mono text-xs font-semibold">{{ d.dispatch_number }}</span>
                    </td>

                    <!-- Equipo -->
                    <td class="px-3 py-2.5">
                        <div class="flex items-center gap-1.5">
                            <Laptop v-if="d.equipable_type === 'computer'" class="h-3.5 w-3.5 flex-shrink-0 text-blue-500" />
                            <UtensilsCrossed v-else class="h-3.5 w-3.5 flex-shrink-0 text-orange-500" />
                            <div>
                                <p class="font-semibold leading-tight">{{ d.equipment_name }}</p>
                                <p class="text-muted-foreground text-xs">
                                    {{ [d.equipment_brand, d.equipment_model].filter(Boolean).join(' · ') || '—' }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <!-- Cantidad -->
                    <td class="px-3 py-2.5 text-center">
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 font-mono text-xs font-bold text-amber-700">
                            {{ d.quantity }}
                        </span>
                    </td>

                    <!-- Origen -->
                    <td class="px-3 py-2.5 text-xs text-slate-600">{{ d.origin_name }}</td>

                    <!-- Despachado -->
                    <td class="px-3 py-2.5">
                        <p class="text-xs">{{ d.dispatched_at }}</p>
                        <p class="text-muted-foreground text-[10px]">por {{ d.dispatched_by }}</p>
                    </td>

                    <!-- Estado recepción -->
                    <td class="px-3 py-2.5">
                        <span
                            v-if="d.received_at"
                            class="inline-flex items-center gap-1 rounded-full border border-emerald-300 bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700"
                        >
                            <CheckCircle2 class="h-3 w-3" />
                            {{ d.received_at }}
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-600"
                        >
                            <Clock class="h-3 w-3" />
                            En tránsito
                        </span>
                    </td>

                    <!-- Acción -->
                    <td class="px-3 py-2.5 text-center">
                        <!-- Received: show who confirmed -->
                        <span v-if="d.received_at" class="text-muted-foreground text-xs">
                            {{ d.received_by ?? '—' }}
                        </span>

                        <!-- Pending confirm inline -->
                        <template v-else-if="confirmId === d.id">
                            <div class="flex items-center justify-center gap-1">
                                <button
                                    :disabled="processing"
                                    class="rounded bg-emerald-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                                    @click="emit('receive', d.id)"
                                >
                                    Confirmar
                                </button>
                                <button
                                    class="rounded border px-2.5 py-1 text-[11px] font-semibold hover:bg-muted"
                                    @click="emit('cancel')"
                                >
                                    No
                                </button>
                            </div>
                        </template>

                        <!-- Receive button -->
                        <button
                            v-else
                            class="rounded border border-emerald-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-50 dark:bg-transparent"
                            @click="emit('confirm', d.id)"
                        >
                            Recepcionar
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
