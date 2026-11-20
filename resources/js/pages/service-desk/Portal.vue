<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { STATUS_LABELS, formatDateTime, statusClass } from '@/lib/service-desk';
import type { SdCategory, SdNamed } from '@/types/service-desk';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, ChevronDown, LifeBuoy, MessageSquare, RotateCcw, Send, Star, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * Portal de autoservicio: canal de entrada "portal" del Service Desk. Lo usa cualquier
 * usuario del ERP para reportar incidencias y seguir las suyas. La bandeja de TI está en
 * `service-desk/Index`; aquí el usuario nunca ve asignado, KEDB ni auditoría.
 */

interface PortalEvent {
    id: number;
    type: string;
    to_status: string | null;
    note: string | null;
    mine: boolean;
    author: string;
    created_at: string;
}

interface PortalTicket {
    id: number;
    code: string;
    title: string;
    description: string | null;
    category: string | null;
    priority: number;
    status: string;
    reported_at: string;
    resolution_due_at: string | null;
    resolved_at: string | null;
    resolution_notes: string | null;
    survey_score: number | null;
    events: PortalEvent[];
}

const props = defineProps<{
    tickets: PortalTicket[];
    categories: SdCategory[];
    units: SdNamed[];
    defaults: { unit_id: number | null };
}>();

const page = usePage();
const flashSuccess = computed(() => (page.props as any).flash?.success as string | undefined);

// Impacto y urgencia en lenguaje del usuario. Los valores son los mismos 1..3 de la matriz
// ITIL (ver IMPACT_OPTIONS en lib/service-desk): TI puede reclasificar después.
const impactOptions = [
    { value: 3, label: 'Solo a mí' },
    { value: 2, label: 'A mi área o a varias personas' },
    { value: 1, label: 'A toda la sede o a un servicio crítico (POS, ventas, red)' },
];

const urgencyOptions = [
    { value: 3, label: 'Puede esperar' },
    { value: 2, label: 'Puedo trabajar, pero con dificultad' },
    { value: 1, label: 'No puedo trabajar' },
];

// ── Nuevo reporte ─────────────────────────────────────────────────────────
const form = useForm({
    title: '',
    description: '',
    category_id: '' as number | string,
    impact: 3,
    urgency: 3,
    requester_area: '',
    unit_id: (props.defaults.unit_id ?? '') as number | string,
});

const submit = () => {
    form.post(route('soporte.tickets.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('title', 'description', 'category_id', 'impact', 'urgency');
        },
    });
};

const selectedCategory = computed(() => props.categories.find((c) => c.id === Number(form.category_id)));

// ── Mis incidencias ───────────────────────────────────────────────────────
const expanded = ref<number | null>(null);
const toggle = (id: number) => {
    expanded.value = expanded.value === id ? null : id;
    commentForm.reset();
    reopenForm.reset();
    rateForm.reset();
    commentForm.clearErrors();
    reopenForm.clearErrors();
    rateForm.clearErrors();
};

const openCount = computed(() => props.tickets.filter((t) => !['resuelto', 'cerrado'].includes(t.status)).length);

const commentForm = useForm({ note: '' });
const sendComment = (ticket: PortalTicket) => {
    commentForm.post(route('soporte.tickets.comment', ticket.id), {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    });
};

const reopenForm = useForm({ note: '' });
const showReopen = ref(false);
const sendReopen = (ticket: PortalTicket) => {
    reopenForm.post(route('soporte.tickets.reopen', ticket.id), {
        preserveScroll: true,
        onSuccess: () => {
            reopenForm.reset();
            showReopen.value = false;
        },
    });
};

const rateForm = useForm({ score: 0, q_response_time: null as number | null, q_solution: null as number | null, comments: '' });
const sendRate = (ticket: PortalTicket) => {
    rateForm.post(route('soporte.tickets.rate', ticket.id), {
        preserveScroll: true,
        onSuccess: () => rateForm.reset(),
    });
};

const rateQuestions = [
    { key: 'q_response_time' as const, label: '¿Te atendieron a tiempo?' },
    { key: 'q_solution' as const, label: '¿Se solucionó tu problema?' },
];

const eventLabel = (event: PortalEvent): string =>
    ({
        creado: 'Incidencia registrada',
        asignado: 'Un técnico tomó tu incidencia',
        respondido: 'Respuesta de la mesa de ayuda',
        comentado: event.mine ? 'Tu mensaje' : 'Mensaje de la mesa de ayuda',
        estado: `Estado: ${STATUS_LABELS[event.to_status ?? ''] ?? event.to_status}`,
        resuelto: 'Marcada como resuelta',
        cerrado: 'Ticket cerrado',
        reabierto: 'Reabierta',
    })[event.type] ?? event.type;

const selectClass =
    'border-input bg-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none';
const textareaClass =
    'border-input bg-background focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none';
</script>

<template>
    <Head title="Soporte TI" />

    <AppLayout>
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
            <header class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <LifeBuoy class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-zinc-900">Soporte TI</h1>
                    <p class="text-sm text-zinc-500">Reporta un problema con tu equipo, el sistema, la red o tus accesos y sigue su atención.</p>
                </div>
            </header>

            <div v-if="flashSuccess" class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                <CheckCircle2 class="h-4 w-4 shrink-0" />
                {{ flashSuccess }}
            </div>

            <!-- Formulario de reporte -->
            <section class="bg-card rounded-xl border p-5 shadow-sm">
                <h2 class="mb-4 text-base font-semibold text-zinc-900">Reportar una incidencia</h2>

                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label for="title">¿Qué está pasando?</Label>
                        <Input id="title" v-model="form.title" placeholder="Ej. La impresora de tickets del comedor no imprime" maxlength="255" />
                        <p v-if="form.errors.title" class="text-xs text-red-600">{{ form.errors.title }}</p>
                    </div>

                    <div class="grid gap-1.5 md:col-span-2">
                        <Label for="description">Detalle</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            :class="textareaClass"
                            placeholder="Desde cuándo ocurre, qué mensaje aparece, qué intentaste..."
                        />
                        <p v-if="form.errors.description" class="text-xs text-red-600">{{ form.errors.description }}</p>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="category">Tipo de problema</Label>
                        <select id="category" v-model="form.category_id" :class="selectClass">
                            <option value="" disabled>Selecciona…</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                        <p v-if="selectedCategory?.description" class="text-xs text-zinc-500">{{ selectedCategory.description }}</p>
                        <p v-if="form.errors.category_id" class="text-xs text-red-600">{{ form.errors.category_id }}</p>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="unit">Unidad / sede</Label>
                        <select id="unit" v-model="form.unit_id" :class="selectClass">
                            <option value="">— Sin especificar —</option>
                            <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                        </select>
                    </div>

                    <fieldset class="grid gap-2">
                        <legend class="mb-1 text-sm font-medium">¿A quién afecta?</legend>
                        <label v-for="option in impactOptions" :key="option.value" class="flex items-center gap-2 text-sm text-zinc-700">
                            <input v-model="form.impact" type="radio" name="impact" :value="option.value" class="accent-indigo-600" />
                            {{ option.label }}
                        </label>
                    </fieldset>

                    <fieldset class="grid gap-2">
                        <legend class="mb-1 text-sm font-medium">¿Puedes seguir trabajando?</legend>
                        <label v-for="option in urgencyOptions" :key="option.value" class="flex items-center gap-2 text-sm text-zinc-700">
                            <input v-model="form.urgency" type="radio" name="urgency" :value="option.value" class="accent-indigo-600" />
                            {{ option.label }}
                        </label>
                    </fieldset>

                    <div class="grid gap-1.5">
                        <Label for="area">Área (opcional)</Label>
                        <Input id="area" v-model="form.requester_area" placeholder="Ej. Almacén, Cocina, Administración" maxlength="255" />
                    </div>

                    <div class="flex items-end justify-end">
                        <Button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700">
                            <Send class="mr-2 h-4 w-4" />
                            Enviar reporte
                        </Button>
                    </div>
                </form>
            </section>

            <!-- Mis incidencias -->
            <section class="bg-card rounded-xl border shadow-sm">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h2 class="text-base font-semibold text-zinc-900">Mis incidencias</h2>
                    <span class="text-xs text-zinc-500">{{ openCount }} abierta(s) · {{ tickets.length }} en total</span>
                </div>

                <p v-if="!tickets.length" class="px-5 py-10 text-center text-sm text-zinc-500">Todavía no reportaste ninguna incidencia.</p>

                <ul v-else class="divide-y">
                    <li v-for="ticket in tickets" :key="ticket.id">
                        <button type="button" class="flex w-full items-center gap-3 px-5 py-3 text-left hover:bg-zinc-50" @click="toggle(ticket.id)">
                            <span class="w-24 shrink-0 font-mono text-xs text-zinc-500">{{ ticket.code }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-zinc-900">{{ ticket.title }}</span>
                                <span class="block text-xs text-zinc-500">{{ ticket.category ?? 'Sin categoría' }} · {{ formatDateTime(ticket.reported_at) }}</span>
                            </span>
                            <span class="shrink-0 rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusClass(ticket.status)">
                                {{ STATUS_LABELS[ticket.status] ?? ticket.status }}
                            </span>
                            <ChevronDown class="h-4 w-4 shrink-0 text-zinc-400 transition-transform" :class="{ 'rotate-180': expanded === ticket.id }" />
                        </button>

                        <div v-if="expanded === ticket.id" class="grid gap-5 bg-zinc-50/60 px-5 py-4">
                            <p v-if="ticket.description" class="text-sm whitespace-pre-line text-zinc-700">{{ ticket.description }}</p>

                            <div
                                v-if="ticket.resolution_notes && ['resuelto', 'cerrado'].includes(ticket.status)"
                                class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900"
                            >
                                <p class="mb-1 font-medium">Solución aplicada</p>
                                <p class="whitespace-pre-line">{{ ticket.resolution_notes }}</p>
                            </div>

                            <!-- Bitácora -->
                            <ol class="grid gap-3 border-l border-zinc-200 pl-4">
                                <li v-for="event in ticket.events" :key="event.id" class="relative">
                                    <span
                                        class="absolute top-1.5 -left-[21px] h-2.5 w-2.5 rounded-full"
                                        :class="event.mine ? 'bg-zinc-400' : 'bg-indigo-500'"
                                    />
                                    <p class="text-xs text-zinc-500">{{ formatDateTime(event.created_at) }} · {{ event.author }}</p>
                                    <p class="text-sm font-medium text-zinc-800">{{ eventLabel(event) }}</p>
                                    <p v-if="event.note" class="text-sm whitespace-pre-line text-zinc-600">{{ event.note }}</p>
                                </li>
                            </ol>

                            <!-- Agregar información -->
                            <form v-if="ticket.status !== 'cerrado'" class="grid gap-2" @submit.prevent="sendComment(ticket)">
                                <Label :for="`note-${ticket.id}`" class="flex items-center gap-1.5">
                                    <MessageSquare class="h-4 w-4" /> Agregar información
                                </Label>
                                <textarea :id="`note-${ticket.id}`" v-model="commentForm.note" rows="2" :class="textareaClass" maxlength="1000" />
                                <p v-if="commentForm.errors.note" class="text-xs text-red-600">{{ commentForm.errors.note }}</p>
                                <div class="flex justify-end">
                                    <Button type="submit" size="sm" variant="outline" :disabled="commentForm.processing || !commentForm.note.trim()">Enviar</Button>
                                </div>
                            </form>

                            <!-- No quedó resuelto -->
                            <div v-if="ticket.status === 'resuelto'" class="grid gap-2">
                                <button
                                    v-if="!showReopen"
                                    type="button"
                                    class="flex w-fit items-center gap-1.5 text-sm font-medium text-amber-700 hover:underline"
                                    @click="showReopen = true"
                                >
                                    <RotateCcw class="h-4 w-4" /> El problema continúa
                                </button>
                                <form v-else class="grid gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3" @submit.prevent="sendReopen(ticket)">
                                    <Label :for="`reopen-${ticket.id}`" class="flex items-center gap-1.5 text-amber-900">
                                        <TriangleAlert class="h-4 w-4" /> ¿Qué sigue fallando?
                                    </Label>
                                    <textarea :id="`reopen-${ticket.id}`" v-model="reopenForm.note" rows="2" :class="textareaClass" maxlength="500" />
                                    <p v-if="reopenForm.errors.note" class="text-xs text-red-600">{{ reopenForm.errors.note }}</p>
                                    <div class="flex justify-end gap-2">
                                        <Button type="button" size="sm" variant="ghost" @click="showReopen = false">Cancelar</Button>
                                        <Button type="submit" size="sm" :disabled="reopenForm.processing || !reopenForm.note.trim()">Reabrir ticket</Button>
                                    </div>
                                </form>
                            </div>

                            <!-- Calificación -->
                            <div v-if="['resuelto', 'cerrado'].includes(ticket.status)">
                                <p v-if="ticket.survey_score" class="flex items-center gap-1 text-sm text-zinc-600">
                                    Calificaste la atención:
                                    <Star v-for="n in 5" :key="n" class="h-4 w-4" :class="n <= ticket.survey_score ? 'fill-amber-400 text-amber-400' : 'text-zinc-300'" />
                                </p>
                                <form v-else class="grid gap-3 rounded-lg border bg-white p-3" @submit.prevent="sendRate(ticket)">
                                    <p class="text-sm font-medium text-zinc-800">¿Cómo calificas la atención recibida?</p>
                                    <div class="flex gap-1">
                                        <button v-for="n in 5" :key="n" type="button" :aria-label="`${n} de 5`" @click="rateForm.score = n">
                                            <Star class="h-6 w-6" :class="n <= rateForm.score ? 'fill-amber-400 text-amber-400' : 'text-zinc-300 hover:text-amber-300'" />
                                        </button>
                                    </div>
                                    <div v-for="question in rateQuestions" :key="question.key" class="flex flex-wrap items-center gap-2 text-sm">
                                        <span class="w-48 text-zinc-600">{{ question.label }}</span>
                                        <button
                                            v-for="n in 5"
                                            :key="n"
                                            type="button"
                                            class="h-7 w-7 rounded-md border text-xs"
                                            :class="rateForm[question.key] === n ? 'border-indigo-600 bg-indigo-600 text-white' : 'hover:bg-zinc-100'"
                                            @click="rateForm[question.key] = n"
                                        >
                                            {{ n }}
                                        </button>
                                    </div>
                                    <textarea v-model="rateForm.comments" rows="2" :class="textareaClass" placeholder="Comentario (opcional)" maxlength="1000" />
                                    <p v-if="rateForm.errors.score" class="text-xs text-red-600">{{ rateForm.errors.score }}</p>
                                    <div class="flex justify-end">
                                        <Button type="submit" size="sm" :disabled="rateForm.processing || !rateForm.score">Enviar calificación</Button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
