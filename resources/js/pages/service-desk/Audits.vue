<script setup lang="ts">
import ModuleTabs from '@/components/service-desk/ModuleTabs.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { IMPACT_OPTIONS, PRIORITY_LABELS, URGENCY_OPTIONS, calculatePriority, formatDateTime, priorityClass } from '@/lib/service-desk';
import type { SdNamed, SdTicket } from '@/types/service-desk';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ClipboardCheck, Filter, ShieldAlert, Wand2 } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    tickets: SdTicket[];
    categories: SdNamed[];
    filters: { from: string; to: string; scope: string };
    stats: {
        total: number;
        audited: number;
        pending: number;
        correct: number;
        coverage_pct: number | null;
        accuracy_pct: number | null;
        min_coverage: number;
        target: number;
    };
}>();

const filters = reactive({ ...props.filters });

const applyFilters = () => {
    router.get(route('service-desk.audits.index'), { ...filters }, { preserveState: true, preserveScroll: true });
};

const SCOPES = [
    { value: 'pendientes', label: 'Pendientes de auditar' },
    { value: 'auditados', label: 'Ya auditados' },
    { value: 'incorrectos', label: 'Con observación' },
    { value: 'todos', label: 'Todos' },
];

// ── Formulario de auditoría ───────────────────────────────────────────────
const auditing = ref<SdTicket | null>(null);

const form = useForm({
    category_correct: true,
    impact_correct: true,
    urgency_correct: true,
    suggested_category_id: '' as number | string,
    suggested_impact: '' as number | string,
    suggested_urgency: '' as number | string,
    findings: '',
    audited_at: '',
});

const openAudit = (ticket: SdTicket) => {
    auditing.value = ticket;
    form.reset();
    form.clearErrors();
    if (ticket.audit) {
        form.category_correct = ticket.audit.category_correct;
        form.impact_correct = ticket.audit.impact_correct;
        form.urgency_correct = ticket.audit.urgency_correct;
        form.suggested_category_id = ticket.audit.suggested_category_id ?? '';
        form.suggested_impact = ticket.audit.suggested_impact ?? '';
        form.suggested_urgency = ticket.audit.suggested_urgency ?? '';
        form.findings = ticket.audit.findings ?? '';
    }
};

const save = () => {
    if (!auditing.value) return;
    form.post(route('service-desk.audits.store', auditing.value.id), {
        preserveScroll: true,
        onSuccess: () => (auditing.value = null),
    });
};

const applyCorrection = (ticket: SdTicket) => {
    if (!confirm(`¿Aplicar la clasificación sugerida a ${ticket.code}? Se recalculará prioridad y SLA.`)) return;
    router.post(route('service-desk.audits.apply', ticket.id), {}, { preserveScroll: true });
};

const removeAudit = (ticket: SdTicket) => {
    if (!confirm(`¿Eliminar la auditoría de ${ticket.code}?`)) return;
    router.delete(route('service-desk.audits.destroy', ticket.id), { preserveScroll: true });
};

/**
 * La prioridad registrada debe coincidir con la que arroja la matriz. Se muestra aquí para
 * que el auditor vea la inconsistencia sin tener que calcularla de memoria.
 */
const matrixPriority = (ticket: SdTicket) => calculatePriority(ticket.impact, ticket.urgency);
const priorityMismatch = (ticket: SdTicket) => ticket.priority !== matrixPriority(ticket);

const coverageTone = computed(() =>
    props.stats.coverage_pct !== null && props.stats.coverage_pct < props.stats.min_coverage ? 'text-amber-600' : 'text-emerald-600',
);
const accuracyTone = computed(() =>
    props.stats.accuracy_pct !== null && props.stats.accuracy_pct < props.stats.target ? 'text-red-600' : 'text-emerald-600',
);
</script>

<template>
    <Head title="Auditoría · Service Desk" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-4 pb-8">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Auditoría de clasificación</h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    Revisión de categoría, impacto y urgencia de los tickets del periodo. Es la fuente del indicador «% de tickets categorizados
                    correctamente».
                </p>
            </div>

            <ModuleTabs />

            <!-- Resumen -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-card rounded-xl border p-4 shadow-sm">
                    <p class="text-muted-foreground text-xs">Tickets del periodo</p>
                    <p class="text-2xl font-bold text-zinc-900">{{ stats.total }}</p>
                </div>
                <div class="bg-card rounded-xl border p-4 shadow-sm">
                    <p class="text-muted-foreground text-xs">Pendientes de auditar</p>
                    <p class="text-2xl font-bold text-zinc-900">{{ stats.pending }}</p>
                </div>
                <div class="bg-card rounded-xl border p-4 shadow-sm">
                    <p class="text-muted-foreground text-xs">Cobertura de auditoría (mín. {{ stats.min_coverage }}%)</p>
                    <p class="text-2xl font-bold" :class="coverageTone">
                        {{ stats.coverage_pct === null ? '—' : stats.coverage_pct + '%' }}
                    </p>
                </div>
                <div class="bg-card rounded-xl border p-4 shadow-sm">
                    <p class="text-muted-foreground text-xs">Clasificación correcta (meta {{ stats.target }}%)</p>
                    <p class="text-2xl font-bold" :class="accuracyTone">
                        {{ stats.accuracy_pct === null ? '—' : stats.accuracy_pct + '%' }}
                    </p>
                </div>
            </div>

            <!-- Filtros -->
            <div class="bg-card flex flex-wrap items-end gap-3 rounded-xl border p-4 shadow-sm">
                <div class="space-y-1.5">
                    <Label class="text-xs">Desde</Label>
                    <Input v-model="filters.from" type="date" class="h-9 w-[160px]" />
                </div>
                <div class="space-y-1.5">
                    <Label class="text-xs">Hasta</Label>
                    <Input v-model="filters.to" type="date" class="h-9 w-[160px]" />
                </div>
                <div class="space-y-1.5">
                    <Label class="text-xs">Mostrar</Label>
                    <select v-model="filters.scope" class="h-9 rounded-md border px-3 text-sm">
                        <option v-for="scope in SCOPES" :key="scope.value" :value="scope.value">{{ scope.label }}</option>
                    </select>
                </div>
                <Button class="gap-2 bg-indigo-600 text-white hover:bg-indigo-700" @click="applyFilters"> <Filter class="h-4 w-4" /> Aplicar </Button>
                <span class="text-muted-foreground ml-auto text-sm">{{ tickets.length }} ticket(s) listado(s)</span>
            </div>

            <!-- Listado -->
            <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Ticket</th>
                                <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Clasificación registrada</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Según matriz</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Auditoría</th>
                                <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Hallazgos</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ticket in tickets" :key="ticket.id" class="hover:bg-muted/30 border-t align-top transition-colors">
                                <td class="p-4">
                                    <p class="font-mono text-xs text-zinc-500">{{ ticket.code }}</p>
                                    <p class="text-sm font-semibold text-zinc-900">{{ ticket.title }}</p>
                                    <p class="text-xs text-zinc-500">{{ formatDateTime(ticket.reported_at) }}</p>
                                </td>
                                <td class="p-4 text-sm text-zinc-700">
                                    <p>{{ ticket.category?.name ?? 'Sin categoría' }}</p>
                                    <p class="text-xs text-zinc-500">Impacto {{ ticket.impact }} · Urgencia {{ ticket.urgency }}</p>
                                    <span
                                        class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                        :class="priorityClass(ticket.priority)"
                                    >
                                        {{ PRIORITY_LABELS[ticket.priority] }}
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                        :class="priorityClass(matrixPriority(ticket))"
                                    >
                                        {{ PRIORITY_LABELS[matrixPriority(ticket)] }}
                                    </span>
                                    <p v-if="priorityMismatch(ticket)" class="mt-1 flex items-center justify-center gap-1 text-[11px] text-red-600">
                                        <ShieldAlert class="h-3 w-3" /> No coincide
                                    </p>
                                </td>
                                <td class="p-4 text-center">
                                    <span
                                        v-if="ticket.audit"
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                        :class="
                                            ticket.audit.is_correct
                                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                                                : 'bg-red-50 text-red-700 ring-red-600/20'
                                        "
                                    >
                                        {{ ticket.audit.is_correct ? 'Conforme' : 'Observado' }}
                                    </span>
                                    <span v-else class="text-xs text-zinc-400">Pendiente</span>
                                    <p v-if="ticket.audit" class="mt-1 text-[11px] text-zinc-400">
                                        {{ ticket.audit.auditor?.name ?? '—' }}<br />{{ formatDateTime(ticket.audit.audited_at) }}
                                    </p>
                                </td>
                                <td class="max-w-xs p-4 text-sm text-zinc-600">
                                    <p v-if="ticket.audit?.findings" class="line-clamp-3">{{ ticket.audit.findings }}</p>
                                    <p
                                        v-if="ticket.audit?.suggested_category_id || ticket.audit?.suggested_impact"
                                        class="mt-1 text-xs text-indigo-700"
                                    >
                                        Sugerido: {{ ticket.audit.suggested_category?.name ?? 'misma categoría' }}
                                        <span v-if="ticket.audit.suggested_impact">· I{{ ticket.audit.suggested_impact }}</span>
                                        <span v-if="ticket.audit.suggested_urgency">· U{{ ticket.audit.suggested_urgency }}</span>
                                    </p>
                                    <span v-if="!ticket.audit?.findings && !ticket.audit?.suggested_category_id" class="text-zinc-400">—</span>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <Button variant="outline" size="sm" class="gap-1" @click="openAudit(ticket)">
                                            <ClipboardCheck class="h-4 w-4" /> {{ ticket.audit ? 'Revisar' : 'Auditar' }}
                                        </Button>
                                        <Button
                                            v-if="ticket.audit && !ticket.audit.is_correct"
                                            variant="ghost"
                                            size="sm"
                                            class="gap-1 text-indigo-600 hover:bg-indigo-50"
                                            @click="applyCorrection(ticket)"
                                        >
                                            <Wand2 class="h-4 w-4" /> Aplicar corrección
                                        </Button>
                                        <Button
                                            v-if="ticket.audit"
                                            variant="ghost"
                                            size="sm"
                                            class="text-zinc-400 hover:text-red-600"
                                            @click="removeAudit(ticket)"
                                        >
                                            Eliminar auditoría
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="tickets.length === 0">
                                <td colspan="6" class="p-12 text-center">
                                    <div class="flex flex-col items-center gap-3 text-zinc-400">
                                        <ClipboardCheck class="h-10 w-10 opacity-30" />
                                        <p class="font-medium">Sin tickets para este filtro</p>
                                        <p class="text-sm">Amplíe el rango de fechas o cambie el criterio de visualización.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal de auditoría -->
        <Dialog :open="auditing !== null" @update:open="(value: boolean) => !value && (auditing = null)">
            <DialogContent v-if="auditing" class="max-h-[90vh] overflow-y-auto sm:max-w-[620px]">
                <DialogHeader>
                    <DialogTitle>Auditar {{ auditing.code }}</DialogTitle>
                </DialogHeader>

                <div class="rounded-lg border bg-zinc-50 p-3 text-sm">
                    <p class="font-semibold text-zinc-900">{{ auditing.title }}</p>
                    <p class="mt-1 text-zinc-600">
                        Registrado como <strong>{{ auditing.category?.name ?? 'sin categoría' }}</strong
                        >, impacto {{ auditing.impact }}, urgencia {{ auditing.urgency }} → {{ PRIORITY_LABELS[auditing.priority] }}.
                    </p>
                    <p v-if="priorityMismatch(auditing)" class="mt-1 font-medium text-red-600">
                        La prioridad guardada no corresponde a la matriz ({{ PRIORITY_LABELS[matrixPriority(auditing)] }}). El sistema marcará este
                        criterio como incorrecto.
                    </p>
                </div>

                <form class="mt-2 space-y-4" @submit.prevent="save">
                    <div class="space-y-2">
                        <Label class="text-xs tracking-wider uppercase">Criterios</Label>
                        <label class="flex items-center gap-2 text-sm text-zinc-700">
                            <input v-model="form.category_correct" type="checkbox" class="h-4 w-4 rounded border" />
                            La categoría asignada es correcta
                        </label>
                        <label class="flex items-center gap-2 text-sm text-zinc-700">
                            <input v-model="form.impact_correct" type="checkbox" class="h-4 w-4 rounded border" />
                            El impacto registrado es correcto
                        </label>
                        <label class="flex items-center gap-2 text-sm text-zinc-700">
                            <input v-model="form.urgency_correct" type="checkbox" class="h-4 w-4 rounded border" />
                            La urgencia registrada es correcta
                        </label>
                        <p class="text-xs text-zinc-500">La coherencia de la prioridad con la matriz la verifica el sistema; no se captura a mano.</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="space-y-1.5">
                            <Label class="text-xs">Categoría sugerida</Label>
                            <select v-model="form.suggested_category_id" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option value="">Sin cambio</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">Impacto sugerido</Label>
                            <select v-model="form.suggested_impact" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option value="">Sin cambio</option>
                                <option v-for="option in IMPACT_OPTIONS" :key="option.value" :value="option.value">{{ option.value }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">Urgencia sugerida</Label>
                            <select v-model="form.suggested_urgency" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option value="">Sin cambio</option>
                                <option v-for="option in URGENCY_OPTIONS" :key="option.value" :value="option.value">{{ option.value }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-xs">Hallazgos</Label>
                        <textarea
                            v-model="form.findings"
                            rows="3"
                            class="w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="Observación concreta de la revisión…"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-xs">Fecha de auditoría</Label>
                        <Input v-model="form.audited_at" type="datetime-local" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="auditing = null">Cancelar</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-indigo-600 text-white hover:bg-indigo-700">
                            {{ form.processing ? 'Guardando…' : 'Registrar auditoría' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
