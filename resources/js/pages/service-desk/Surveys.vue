<script setup lang="ts">
import ModuleTabs from '@/components/service-desk/ModuleTabs.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { DIMENSION_LABELS, formatDateTime } from '@/lib/service-desk';
import type { SdAssessment, SdMaturityQuestion, SdSurvey } from '@/types/service-desk';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ClipboardList, Filter, Plus, Smile, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    surveys: SdSurvey[];
    assessments: SdAssessment[];
    questions: SdMaturityQuestion[];
    pendingTickets: { id: number; code: string; title: string; requester_name: string | null; resolved_at: string | null }[];
    filters: { from: string; to: string };
    stats: {
        surveys_count: number;
        satisfaction_avg: number | null;
        satisfaction_pct: number | null;
        assessments_count: number;
        maturity_by_dimension: Record<string, number | null>;
        targets: { satisfaction: number; estandarizacion: number };
    };
}>();

const tab = ref<'satisfaccion' | 'madurez'>('satisfaccion');
const filters = reactive({ ...props.filters });

const applyFilters = () => {
    router.get(route('service-desk.surveys.index'), { ...filters }, { preserveState: true, preserveScroll: true });
};

const LIKERT = [
    { value: 1, label: '1 · Muy en desacuerdo / Muy insatisfecho' },
    { value: 2, label: '2 · En desacuerdo / Insatisfecho' },
    { value: 3, label: '3 · Neutral' },
    { value: 4, label: '4 · De acuerdo / Satisfecho' },
    { value: 5, label: '5 · Muy de acuerdo / Muy satisfecho' },
];

// ── Encuesta de satisfacción (usuarios) ───────────────────────────────────
const showSurveyForm = ref(false);

const surveyForm = useForm({
    ticket_id: '' as number | string,
    respondent_name: '',
    respondent_area: '',
    score: 4,
    q_attention: '' as number | string,
    q_response_time: '' as number | string,
    q_solution: '' as number | string,
    q_communication: '' as number | string,
    comments: '',
    answered_at: '',
});

const openSurveyForm = () => {
    surveyForm.reset();
    surveyForm.clearErrors();
    showSurveyForm.value = true;
};

// Al elegir un ticket se precarga el solicitante: la encuesta se aplica a quien reportó.
const onTicketPicked = () => {
    const ticket = props.pendingTickets.find((t) => t.id === Number(surveyForm.ticket_id));
    if (ticket && !surveyForm.respondent_name) surveyForm.respondent_name = ticket.requester_name ?? '';
};

const saveSurvey = () => {
    surveyForm.post(route('service-desk.surveys.satisfaction.store'), {
        preserveScroll: true,
        onSuccess: () => (showSurveyForm.value = false),
    });
};

const destroySurvey = (survey: SdSurvey) => {
    if (!confirm('¿Eliminar esta encuesta? El indicador de satisfacción se recalculará sin ella.')) return;
    router.delete(route('service-desk.surveys.satisfaction.destroy', survey.id), { preserveScroll: true });
};

// ── Cuestionario de madurez (personal TI) ─────────────────────────────────
const showMaturityForm = ref(false);

const maturityForm = useForm({
    respondent_name: '',
    respondent_role: '',
    period: new Date().toISOString().slice(0, 7),
    answered_at: '',
    comments: '',
    answers: {} as Record<number, number>,
});

const openMaturityForm = () => {
    maturityForm.reset();
    maturityForm.clearErrors();
    maturityForm.period = new Date().toISOString().slice(0, 7);
    maturityForm.answers = Object.fromEntries(props.questions.map((q) => [q.id, 3]));
    showMaturityForm.value = true;
};

const saveMaturity = () => {
    maturityForm.post(route('service-desk.surveys.maturity.store'), {
        preserveScroll: true,
        onSuccess: () => (showMaturityForm.value = false),
    });
};

const destroyAssessment = (assessment: SdAssessment) => {
    if (!confirm('¿Eliminar este cuestionario? El nivel de estandarización se recalculará sin él.')) return;
    router.delete(route('service-desk.surveys.maturity.destroy', assessment.id), { preserveScroll: true });
};

const questionsByDimension = computed(() => {
    const groups: Record<string, SdMaturityQuestion[]> = {};
    props.questions.forEach((question) => {
        (groups[question.dimension] ??= []).push(question);
    });
    return groups;
});

/** Promedio en vivo del cuestionario que se está llenando. */
const maturityPreview = computed(() => {
    const values = Object.values(maturityForm.answers);
    if (values.length === 0) return null;
    return (values.reduce((sum, value) => sum + Number(value), 0) / values.length).toFixed(2);
});

const scoreTone = (score: number) =>
    score >= 4 ? 'bg-emerald-50 text-emerald-700' : score === 3 ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700';

const avgTone = (value: number | null, target: number) => (value === null ? 'text-zinc-400' : value >= target ? 'text-emerald-600' : 'text-red-600');
</script>

<template>
    <Head title="Cuestionarios · Service Desk" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-4 pb-8">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Cuestionarios</h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    Los dos instrumentos que no salen de los logs: percepción del usuario atendido (Likert 1–5) y adopción de prácticas ITIL 4
                    declarada por el personal de TI (escala ordinal 1–5).
                </p>
            </div>

            <ModuleTabs />

            <!-- Resumen -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-card rounded-xl border p-4 shadow-sm">
                    <p class="text-muted-foreground text-xs">Satisfacción promedio (meta {{ stats.targets.satisfaction }})</p>
                    <p class="text-2xl font-bold" :class="avgTone(stats.satisfaction_avg, stats.targets.satisfaction)">
                        {{ stats.satisfaction_avg === null ? '—' : stats.satisfaction_avg + ' / 5' }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">{{ stats.surveys_count }} encuesta(s) en el periodo</p>
                </div>
                <div class="bg-card rounded-xl border p-4 shadow-sm">
                    <p class="text-muted-foreground text-xs">Usuarios satisfechos (4–5)</p>
                    <p class="text-2xl font-bold text-zinc-900">{{ stats.satisfaction_pct === null ? '—' : stats.satisfaction_pct + '%' }}</p>
                </div>
                <div class="bg-card rounded-xl border p-4 shadow-sm">
                    <p class="text-muted-foreground text-xs">Estandarización del flujo (meta {{ stats.targets.estandarizacion }})</p>
                    <p class="text-2xl font-bold" :class="avgTone(stats.maturity_by_dimension.estandarizacion, stats.targets.estandarizacion)">
                        {{ stats.maturity_by_dimension.estandarizacion === null ? '—' : stats.maturity_by_dimension.estandarizacion + ' / 5' }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">{{ stats.assessments_count }} cuestionario(s) aplicado(s)</p>
                </div>
                <div class="bg-card rounded-xl border p-4 shadow-sm">
                    <p class="text-muted-foreground text-xs">Otras dimensiones ITIL</p>
                    <p class="mt-1 text-sm text-zinc-700">
                        Conocimiento:
                        <strong>{{ stats.maturity_by_dimension.conocimiento ?? '—' }}</strong>
                    </p>
                    <p class="text-sm text-zinc-700">
                        Priorización:
                        <strong>{{ stats.maturity_by_dimension.priorizacion ?? '—' }}</strong>
                    </p>
                </div>
            </div>

            <!-- Periodo + pestañas -->
            <div class="bg-card flex flex-wrap items-end gap-3 rounded-xl border p-4 shadow-sm">
                <div class="space-y-1.5">
                    <Label class="text-xs">Desde</Label>
                    <Input v-model="filters.from" type="date" class="h-9 w-[160px]" />
                </div>
                <div class="space-y-1.5">
                    <Label class="text-xs">Hasta</Label>
                    <Input v-model="filters.to" type="date" class="h-9 w-[160px]" />
                </div>
                <Button class="gap-2 bg-indigo-600 text-white hover:bg-indigo-700" @click="applyFilters"> <Filter class="h-4 w-4" /> Aplicar </Button>

                <div class="ml-auto flex items-center gap-1 rounded-lg border p-1">
                    <button
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="tab === 'satisfaccion' ? 'bg-indigo-600 text-white' : 'text-zinc-600 hover:bg-zinc-100'"
                        @click="tab = 'satisfaccion'"
                    >
                        Satisfacción de usuarios
                    </button>
                    <button
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="tab === 'madurez' ? 'bg-indigo-600 text-white' : 'text-zinc-600 hover:bg-zinc-100'"
                        @click="tab = 'madurez'"
                    >
                        Madurez ITIL 4 (personal TI)
                    </button>
                </div>
            </div>

            <!-- Satisfacción -->
            <div v-if="tab === 'satisfaccion'" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-muted-foreground text-sm">{{ pendingTickets.length }} ticket(s) atendido(s) sin encuesta registrada.</p>
                    <Button class="gap-2 bg-indigo-600 text-white hover:bg-indigo-700" @click="openSurveyForm">
                        <Plus class="h-4 w-4" /> Registrar encuesta
                    </Button>
                </div>

                <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full table-auto border-collapse">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Usuario</th>
                                    <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Ticket</th>
                                    <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Percepción global</th>
                                    <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Atención</th>
                                    <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Tiempo</th>
                                    <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Solución</th>
                                    <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Comentario</th>
                                    <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="survey in surveys" :key="survey.id" class="hover:bg-muted/30 border-t align-top transition-colors">
                                    <td class="p-4">
                                        <p class="text-sm font-semibold text-zinc-900">{{ survey.respondent_name ?? '—' }}</p>
                                        <p class="text-xs text-zinc-500">{{ survey.respondent_area ?? '' }}</p>
                                        <p class="text-xs text-zinc-400">{{ formatDateTime(survey.answered_at) }}</p>
                                    </td>
                                    <td class="p-4 text-sm text-zinc-700">
                                        <span v-if="survey.ticket" class="font-mono text-xs">{{ survey.ticket.code }}</span>
                                        <p v-if="survey.ticket" class="text-xs text-zinc-500">{{ survey.ticket.title }}</p>
                                        <span v-else class="text-zinc-400">Sin ticket</span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="rounded-md px-2 py-1 text-sm font-bold" :class="scoreTone(survey.score)">
                                            {{ survey.score }} / 5
                                        </span>
                                    </td>
                                    <td class="p-4 text-center text-sm text-zinc-700">{{ survey.q_attention ?? '—' }}</td>
                                    <td class="p-4 text-center text-sm text-zinc-700">{{ survey.q_response_time ?? '—' }}</td>
                                    <td class="p-4 text-center text-sm text-zinc-700">{{ survey.q_solution ?? '—' }}</td>
                                    <td class="max-w-xs p-4 text-sm text-zinc-600">{{ survey.comments ?? '—' }}</td>
                                    <td class="p-4 text-center">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-lg text-zinc-400 hover:bg-red-50 hover:text-red-600"
                                            @click="destroySurvey(survey)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="surveys.length === 0">
                                    <td colspan="8" class="p-12 text-center">
                                        <div class="flex flex-col items-center gap-3 text-zinc-400">
                                            <Smile class="h-10 w-10 opacity-30" />
                                            <p class="font-medium">Sin encuestas en el periodo</p>
                                            <p class="text-sm">Sin respuestas no es posible calcular la calidad percibida del servicio.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Madurez -->
            <div v-else class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-muted-foreground text-sm">{{ questions.length }} ítem(s) activos en el cuestionario.</p>
                    <Button class="gap-2 bg-indigo-600 text-white hover:bg-indigo-700" @click="openMaturityForm">
                        <Plus class="h-4 w-4" /> Aplicar cuestionario
                    </Button>
                </div>

                <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full table-auto border-collapse">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Respondente</th>
                                    <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Rol</th>
                                    <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Periodo</th>
                                    <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Promedio</th>
                                    <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Comentarios</th>
                                    <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="assessment in assessments" :key="assessment.id" class="hover:bg-muted/30 border-t align-top">
                                    <td class="p-4">
                                        <p class="text-sm font-semibold text-zinc-900">{{ assessment.respondent_name }}</p>
                                        <p class="text-xs text-zinc-400">{{ formatDateTime(assessment.answered_at) }}</p>
                                    </td>
                                    <td class="p-4 text-sm text-zinc-700">{{ assessment.respondent_role ?? '—' }}</td>
                                    <td class="p-4 text-center font-mono text-sm text-zinc-700">{{ assessment.period }}</td>
                                    <td class="p-4 text-center">
                                        <span class="rounded-md px-2 py-1 text-sm font-bold" :class="scoreTone(Math.round(assessment.average ?? 0))">
                                            {{ assessment.average ?? '—' }} / 5
                                        </span>
                                    </td>
                                    <td class="max-w-xs p-4 text-sm text-zinc-600">{{ assessment.comments ?? '—' }}</td>
                                    <td class="p-4 text-center">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-lg text-zinc-400 hover:bg-red-50 hover:text-red-600"
                                            @click="destroyAssessment(assessment)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="assessments.length === 0">
                                    <td colspan="6" class="p-12 text-center">
                                        <div class="flex flex-col items-center gap-3 text-zinc-400">
                                            <ClipboardList class="h-10 w-10 opacity-30" />
                                            <p class="font-medium">Sin cuestionarios aplicados</p>
                                            <p class="text-sm">El nivel de estandarización del flujo se calcula con estas respuestas.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: encuesta de satisfacción -->
        <Dialog v-model:open="showSurveyForm">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[560px]">
                <DialogHeader>
                    <DialogTitle>Registrar encuesta de satisfacción</DialogTitle>
                </DialogHeader>

                <form class="mt-2 space-y-4" @submit.prevent="saveSurvey">
                    <div class="space-y-1.5">
                        <Label>Ticket atendido</Label>
                        <select v-model="surveyForm.ticket_id" class="h-9 w-full rounded-md border px-3 text-sm" @change="onTicketPicked">
                            <option value="">Sin ticket asociado</option>
                            <option v-for="ticket in pendingTickets" :key="ticket.id" :value="ticket.id">
                                {{ ticket.code }} — {{ ticket.title }}
                            </option>
                        </select>
                        <p v-if="surveyForm.errors.ticket_id" class="text-xs text-red-500">{{ surveyForm.errors.ticket_id }}</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label>Usuario <span class="text-red-500">*</span></Label>
                            <Input v-model="surveyForm.respondent_name" placeholder="Nombre de quien responde" />
                            <p v-if="surveyForm.errors.respondent_name" class="text-xs text-red-500">{{ surveyForm.errors.respondent_name }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Área</Label>
                            <Input v-model="surveyForm.respondent_area" placeholder="Ej. Logística" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Percepción global del servicio <span class="text-red-500">*</span></Label>
                        <select v-model.number="surveyForm.score" class="h-9 w-full rounded-md border px-3 text-sm">
                            <option v-for="option in LIKERT" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label class="text-xs">Trato y atención recibida</Label>
                            <select v-model="surveyForm.q_attention" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option value="">Sin respuesta</option>
                                <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">Tiempo de atención</Label>
                            <select v-model="surveyForm.q_response_time" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option value="">Sin respuesta</option>
                                <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">Solución entregada</Label>
                            <select v-model="surveyForm.q_solution" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option value="">Sin respuesta</option>
                                <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs">Comunicación durante el caso</Label>
                            <select v-model="surveyForm.q_communication" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option value="">Sin respuesta</option>
                                <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Comentarios</Label>
                        <textarea v-model="surveyForm.comments" rows="2" class="w-full rounded-md border px-3 py-2 text-sm" />
                    </div>

                    <div class="space-y-1.5">
                        <Label>Fecha de respuesta</Label>
                        <Input v-model="surveyForm.answered_at" type="datetime-local" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="showSurveyForm = false">Cancelar</Button>
                        <Button type="submit" :disabled="surveyForm.processing" class="bg-indigo-600 text-white hover:bg-indigo-700">
                            {{ surveyForm.processing ? 'Guardando…' : 'Registrar encuesta' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal: cuestionario de madurez -->
        <Dialog v-model:open="showMaturityForm">
            <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-[760px]">
                <DialogHeader>
                    <DialogTitle>Cuestionario de adopción de prácticas ITIL 4</DialogTitle>
                </DialogHeader>

                <form class="mt-2 space-y-5" @submit.prevent="saveMaturity">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="space-y-1.5">
                            <Label>Respondente <span class="text-red-500">*</span></Label>
                            <Input v-model="maturityForm.respondent_name" placeholder="Nombre" />
                            <p v-if="maturityForm.errors.respondent_name" class="text-xs text-red-500">
                                {{ maturityForm.errors.respondent_name }}
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Rol</Label>
                            <Input v-model="maturityForm.respondent_role" placeholder="Ej. Analista de soporte" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Periodo <span class="text-red-500">*</span></Label>
                            <Input v-model="maturityForm.period" type="month" />
                            <p v-if="maturityForm.errors.period" class="text-xs text-red-500">{{ maturityForm.errors.period }}</p>
                        </div>
                    </div>

                    <p v-if="maturityForm.errors.answers" class="text-xs text-red-500">{{ maturityForm.errors.answers }}</p>

                    <div v-for="(items, dimension) in questionsByDimension" :key="dimension" class="space-y-2">
                        <p class="text-xs font-bold tracking-wider text-indigo-600 uppercase">
                            {{ DIMENSION_LABELS[dimension] ?? dimension }}
                        </p>
                        <div v-for="question in items" :key="question.id" class="rounded-lg border p-3">
                            <p class="text-sm text-zinc-800">
                                <span class="font-mono text-xs text-zinc-400">{{ question.code }}</span> — {{ question.statement }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-3">
                                <label v-for="n in 5" :key="n" class="flex items-center gap-1.5 text-sm text-zinc-600">
                                    <input v-model.number="maturityForm.answers[question.id]" type="radio" :value="n" class="h-4 w-4" />
                                    {{ n }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Comentarios</Label>
                        <textarea v-model="maturityForm.comments" rows="2" class="w-full rounded-md border px-3 py-2 text-sm" />
                    </div>

                    <div class="flex items-center justify-between gap-3 border-t pt-3">
                        <p class="text-muted-foreground text-sm">
                            Promedio de esta aplicación: <strong class="text-zinc-800">{{ maturityPreview ?? '—' }} / 5</strong>
                        </p>
                        <div class="flex gap-2">
                            <Button type="button" variant="outline" @click="showMaturityForm = false">Cancelar</Button>
                            <Button type="submit" :disabled="maturityForm.processing" class="bg-indigo-600 text-white hover:bg-indigo-700">
                                {{ maturityForm.processing ? 'Guardando…' : 'Guardar cuestionario' }}
                            </Button>
                        </div>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
