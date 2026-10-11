<script setup lang="ts">
import ModuleTabs from '@/components/service-desk/ModuleTabs.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    CHANNEL_LABELS,
    IMPACT_OPTIONS,
    PRIORITY_LABELS,
    STATUS_LABELS,
    URGENCY_OPTIONS,
    calculatePriority,
    formatDateTime,
    formatMinutes,
    priorityClass,
    statusClass,
    toLocalInput,
} from '@/lib/service-desk';
import type { SdCategory, SdNamed, SdTicket } from '@/types/service-desk';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlarmClock,
    CheckCircle2,
    CircleSlash,
    Clock,
    Inbox,
    MessageSquarePlus,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Ticket as TicketIcon,
    Trash2,
    UserPlus,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface Paginator {
    data: SdTicket[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

const props = defineProps<{
    tickets: Paginator;
    filters: Record<string, string>;
    categories: SdCategory[];
    agents: SdNamed[];
    units: SdNamed[];
    articles: { id: number; code: string; title: string }[];
    stats: Record<string, number>;
    statuses: string[];
    channels: string[];
}>();

// ── Filtros ───────────────────────────────────────────────────────────────
const filters = reactive({ ...props.filters });
let debounce: ReturnType<typeof setTimeout> | undefined;

const applyFilters = () => {
    router.get(route('service-desk.index'), { ...filters }, { preserveState: true, preserveScroll: true, replace: true });
};

watch(
    () => ({ ...filters }),
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(applyFilters, 350);
    },
    { deep: true },
);

const clearFilters = () => {
    Object.keys(filters).forEach((key) => (filters[key as keyof typeof filters] = ''));
};

const hasFilters = computed(() => Object.values(filters).some((value) => value !== '' && value !== null));

// ── Alta / edición ────────────────────────────────────────────────────────
const showForm = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    title: '',
    description: '',
    requester_user_id: '' as number | string,
    requester_name: '',
    requester_email: '',
    requester_area: '',
    unit_id: '' as number | string,
    channel: 'portal',
    category_id: '' as number | string,
    impact: 3,
    urgency: 3,
    assigned_to: '' as number | string,
    reported_at: toLocalInput(null),
});

// La prioridad no se captura: se deriva de la matriz y se muestra para que el usuario vea
// el efecto de su clasificación antes de guardar.
const previewPriority = computed(() => calculatePriority(Number(form.impact), Number(form.urgency)));

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.reported_at = toLocalInput(null);
    showForm.value = true;
};

const openEdit = (ticket: SdTicket) => {
    editingId.value = ticket.id;
    form.title = ticket.title;
    form.description = ticket.description ?? '';
    form.requester_user_id = ticket.requester_user_id ?? '';
    form.requester_name = ticket.requester_name ?? '';
    form.requester_email = ticket.requester_email ?? '';
    form.requester_area = ticket.requester_area ?? '';
    form.unit_id = ticket.unit_id ?? '';
    form.channel = ticket.channel;
    form.category_id = ticket.category_id ?? '';
    form.impact = ticket.impact;
    form.urgency = ticket.urgency;
    form.assigned_to = ticket.assigned_to ?? '';
    form.reported_at = toLocalInput(ticket.reported_at);
    showForm.value = true;
};

const submitForm = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
            if (editingId.value) refreshDetail(editingId.value);
        },
    };

    if (editingId.value) {
        form.put(route('service-desk.tickets.update', editingId.value), options);
    } else {
        form.post(route('service-desk.tickets.store'), options);
    }
};

// ── Detalle del ticket ────────────────────────────────────────────────────
const detail = ref<SdTicket | null>(null);
const loadingDetail = ref(false);

const openDetail = async (ticket: SdTicket) => {
    loadingDetail.value = true;
    detail.value = ticket;
    await refreshDetail(ticket.id);
    loadingDetail.value = false;
};

const refreshDetail = async (id: number) => {
    const response = await fetch(route('service-desk.tickets.show', id), { headers: { Accept: 'application/json' } });
    if (!response.ok) return;
    const payload = await response.json();
    detail.value = payload.ticket;
};

const closeDetail = () => {
    detail.value = null;
    assignForm.reset();
    respondForm.reset();
    resolveForm.reset();
};

// Acciones del ticket. Todas recargan el detalle para que la bitácora quede al día.
const actionOptions = (id: number, after?: () => void) => ({
    preserveScroll: true,
    onSuccess: () => {
        refreshDetail(id);
        after?.();
    },
});

const assignForm = useForm({ assigned_to: '' as number | string, note: '' });
const respondForm = useForm({ note: '', responded_at: '' });
const statusForm = useForm({ status: '', note: '' });
const resolveForm = useForm({
    resolution_notes: '',
    resolved_with_kedb: false,
    kedb_article_id: '' as number | string,
    resolved_at: '',
});
const reopenForm = useForm({ note: '' });

const submitAssign = () => {
    if (!detail.value) return;
    assignForm.post(
        route('service-desk.tickets.assign', detail.value.id),
        actionOptions(detail.value.id, () => assignForm.reset()),
    );
};

const submitRespond = () => {
    if (!detail.value) return;
    respondForm.post(
        route('service-desk.tickets.respond', detail.value.id),
        actionOptions(detail.value.id, () => respondForm.reset()),
    );
};

const submitStatus = (status: string) => {
    if (!detail.value) return;
    statusForm.status = status;
    statusForm.post(
        route('service-desk.tickets.status', detail.value.id),
        actionOptions(detail.value.id, () => statusForm.reset()),
    );
};

const submitResolve = () => {
    if (!detail.value) return;
    resolveForm.post(
        route('service-desk.tickets.resolve', detail.value.id),
        actionOptions(detail.value.id, () => resolveForm.reset()),
    );
};

const submitClose = () => {
    if (!detail.value) return;
    router.post(route('service-desk.tickets.close', detail.value.id), {}, actionOptions(detail.value.id));
};

const submitReopen = () => {
    if (!detail.value) return;
    reopenForm.post(
        route('service-desk.tickets.reopen', detail.value.id),
        actionOptions(detail.value.id, () => reopenForm.reset()),
    );
};

const destroy = (ticket: SdTicket) => {
    if (!confirm(`¿Eliminar el ticket ${ticket.code}? Se perderá su bitácora y dejará de contar en los indicadores.`)) return;
    router.delete(route('service-desk.tickets.destroy', ticket.id), { preserveScroll: true });
};

const isOpen = (ticket: SdTicket) => !['resuelto', 'cerrado'].includes(ticket.status);

const slaBadge = (ticket: SdTicket) => {
    if (ticket.is_overdue) return { label: 'SLA vencido', class: 'bg-red-50 text-red-700 ring-red-600/20' };
    if (ticket.sla_met === true) return { label: 'Dentro de SLA', class: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' };
    if (ticket.sla_met === false) return { label: 'Fuera de SLA', class: 'bg-red-50 text-red-700 ring-red-600/20' };
    return { label: 'En curso', class: 'bg-zinc-100 text-zinc-600 ring-zinc-400/20' };
};

const statCards = computed(() => [
    { label: 'Tickets (90 días)', value: props.stats.total, icon: TicketIcon, tone: 'text-indigo-600 bg-indigo-50' },
    { label: 'Abiertos', value: props.stats.abiertos, icon: Inbox, tone: 'text-sky-600 bg-sky-50' },
    { label: 'Resueltos', value: props.stats.resueltos, icon: CheckCircle2, tone: 'text-emerald-600 bg-emerald-50' },
    { label: 'Con SLA vencido', value: props.stats.vencidos, icon: AlarmClock, tone: 'text-red-600 bg-red-50' },
    { label: 'Reabiertos', value: props.stats.reabiertos, icon: RotateCcw, tone: 'text-amber-600 bg-amber-50' },
]);
</script>

<template>
    <Head title="Service Desk" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-4 pb-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Service Desk</h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Gestión de incidencias bajo ITIL 4: registro, clasificación por impacto/urgencia, atención dentro de SLA y cierre trazable.
                    </p>
                </div>
                <Button class="gap-2 bg-indigo-600 text-white hover:bg-indigo-700" @click="openCreate">
                    <Plus class="h-4 w-4" /> Nueva incidencia
                </Button>
            </div>

            <ModuleTabs />

            <!-- Resumen -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div v-for="card in statCards" :key="card.label" class="bg-card flex items-center gap-3 rounded-xl border p-4 shadow-sm">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg" :class="card.tone">
                        <component :is="card.icon" class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-zinc-900">{{ card.value ?? 0 }}</p>
                        <p class="text-muted-foreground text-xs">{{ card.label }}</p>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="bg-card flex flex-wrap items-center gap-3 rounded-xl border p-4 shadow-sm">
                <div class="relative max-w-sm min-w-[220px] flex-1">
                    <Search class="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                    <Input v-model="filters.search" placeholder="Buscar por código, título o solicitante…" class="pl-9" />
                </div>
                <select v-model="filters.status" class="h-9 rounded-md border px-3 text-sm">
                    <option value="">Todos los estados</option>
                    <option v-for="status in statuses" :key="status" :value="status">{{ STATUS_LABELS[status] ?? status }}</option>
                </select>
                <select v-model="filters.priority" class="h-9 rounded-md border px-3 text-sm">
                    <option value="">Todas las prioridades</option>
                    <option v-for="(label, value) in PRIORITY_LABELS" :key="value" :value="value">{{ label }}</option>
                </select>
                <select v-model="filters.category_id" class="h-9 rounded-md border px-3 text-sm">
                    <option value="">Todas las categorías</option>
                    <option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
                </select>
                <select v-model="filters.assigned_to" class="h-9 rounded-md border px-3 text-sm">
                    <option value="">Todos los técnicos</option>
                    <option v-for="agent in agents" :key="agent.id" :value="String(agent.id)">{{ agent.name }}</option>
                </select>
                <Input v-model="filters.from" type="date" class="h-9 w-[150px]" title="Reportados desde" />
                <Input v-model="filters.to" type="date" class="h-9 w-[150px]" title="Reportados hasta" />
                <Button v-if="hasFilters" variant="ghost" size="sm" class="gap-1 text-zinc-500" @click="clearFilters">
                    <CircleSlash class="h-4 w-4" /> Limpiar
                </Button>
                <span class="text-muted-foreground ml-auto text-sm">{{ tickets.total }} ticket(s)</span>
            </div>

            <!-- Bandeja -->
            <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Ticket</th>
                                <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Categoría</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Prioridad</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Estado</th>
                                <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Asignado</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Respuesta</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Resolución</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">SLA</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="ticket in tickets.data"
                                :key="ticket.id"
                                class="hover:bg-muted/30 cursor-pointer border-t transition-colors"
                                @click="openDetail(ticket)"
                            >
                                <td class="p-4">
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                            <TicketIcon class="h-4 w-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-mono text-xs text-zinc-500">{{ ticket.code }}</p>
                                            <p class="truncate text-sm font-semibold text-zinc-900">{{ ticket.title }}</p>
                                            <p class="text-xs text-zinc-500">
                                                {{ ticket.requester_name || '—' }} · {{ CHANNEL_LABELS[ticket.channel] ?? ticket.channel }} ·
                                                {{ formatDateTime(ticket.reported_at) }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-sm text-zinc-700">{{ ticket.category?.name ?? '—' }}</td>
                                <td class="p-4 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                        :class="priorityClass(ticket.priority)"
                                    >
                                        {{ PRIORITY_LABELS[ticket.priority] }}
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                        :class="statusClass(ticket.status)"
                                    >
                                        {{ ticket.status_label }}
                                    </span>
                                </td>
                                <td class="p-4 text-sm text-zinc-700">{{ ticket.assignee?.name ?? 'Sin asignar' }}</td>
                                <td class="p-4 text-center text-sm text-zinc-700">{{ formatMinutes(ticket.response_minutes) }}</td>
                                <td class="p-4 text-center text-sm text-zinc-700">{{ formatMinutes(ticket.resolution_minutes) }}</td>
                                <td class="p-4 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                        :class="slaBadge(ticket).class"
                                    >
                                        {{ slaBadge(ticket).label }}
                                    </span>
                                </td>
                                <td class="p-4 text-center" @click.stop>
                                    <div class="flex items-center justify-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-lg text-zinc-500 hover:bg-indigo-50 hover:text-indigo-600"
                                            title="Editar"
                                            @click="openEdit(ticket)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-lg text-zinc-500 hover:bg-red-50 hover:text-red-600"
                                            title="Eliminar"
                                            @click="destroy(ticket)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="tickets.data.length === 0">
                                <td colspan="9" class="p-12 text-center">
                                    <div class="flex flex-col items-center gap-3 text-zinc-400">
                                        <Inbox class="h-10 w-10 opacity-30" />
                                        <p class="font-medium">Sin incidencias</p>
                                        <p class="text-sm">Registre la primera o ajuste los filtros de búsqueda.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Paginación -->
            <div v-if="tickets.last_page > 1" class="flex flex-wrap items-center justify-center gap-1">
                <Button
                    v-for="link in tickets.links"
                    :key="link.label"
                    variant="outline"
                    size="sm"
                    :disabled="!link.url"
                    :class="link.active ? 'bg-indigo-600 text-white hover:bg-indigo-700' : ''"
                    @click="link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                >
                    <span v-html="link.label" />
                </Button>
            </div>
        </div>

        <!-- Alta / edición -->
        <Dialog v-model:open="showForm">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[680px]">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Editar incidencia' : 'Registrar incidencia' }}</DialogTitle>
                </DialogHeader>

                <form class="mt-2 space-y-4" @submit.prevent="submitForm">
                    <div class="space-y-1.5">
                        <Label>Título <span class="text-red-500">*</span></Label>
                        <Input v-model="form.title" placeholder="Ej. No imprime la guía de despacho en almacén" />
                        <p v-if="form.errors.title" class="text-xs text-red-500">{{ form.errors.title }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Descripción</Label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="Síntoma observado, mensaje de error, pasos para reproducirlo…"
                        />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label>Solicitante <span class="text-red-500">*</span></Label>
                            <Input v-model="form.requester_name" placeholder="Nombre de quien reporta" />
                            <p v-if="form.errors.requester_name" class="text-xs text-red-500">{{ form.errors.requester_name }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Área del solicitante</Label>
                            <Input v-model="form.requester_area" placeholder="Ej. Almacén, Planillas, Comedor" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Correo</Label>
                            <Input v-model="form.requester_email" type="email" placeholder="correo@empresa.com" />
                            <p v-if="form.errors.requester_email" class="text-xs text-red-500">{{ form.errors.requester_email }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Unidad</Label>
                            <select v-model="form.unit_id" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option value="">Sin unidad</option>
                                <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="space-y-1.5">
                            <Label>Canal <span class="text-red-500">*</span></Label>
                            <select v-model="form.channel" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option v-for="channel in channels" :key="channel" :value="channel">
                                    {{ CHANNEL_LABELS[channel] ?? channel }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Categoría <span class="text-red-500">*</span></Label>
                            <select v-model="form.category_id" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option value="">Seleccione…</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                            <p v-if="form.errors.category_id" class="text-xs text-red-500">{{ form.errors.category_id }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Fecha de reporte</Label>
                            <Input v-model="form.reported_at" type="datetime-local" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label>Impacto <span class="text-red-500">*</span></Label>
                            <select v-model.number="form.impact" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option v-for="option in IMPACT_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Urgencia <span class="text-red-500">*</span></Label>
                            <select v-model.number="form.urgency" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option v-for="option in URGENCY_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-lg border border-dashed p-3">
                        <span class="text-muted-foreground text-xs">Prioridad resultante (matriz ITIL impacto × urgencia):</span>
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                            :class="priorityClass(previewPriority)"
                        >
                            {{ PRIORITY_LABELS[previewPriority] }}
                        </span>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Asignar a</Label>
                        <select v-model="form.assigned_to" class="h-9 w-full rounded-md border px-3 text-sm">
                            <option value="">Sin asignar</option>
                            <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="showForm = false">Cancelar</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-indigo-600 text-white hover:bg-indigo-700">
                            {{ form.processing ? 'Guardando…' : editingId ? 'Guardar cambios' : 'Registrar incidencia' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Detalle y bitácora -->
        <Dialog :open="detail !== null" @update:open="(value: boolean) => !value && closeDetail()">
            <DialogContent v-if="detail" class="max-h-[92vh] overflow-y-auto sm:max-w-[860px]">
                <DialogHeader>
                    <DialogTitle class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-sm text-zinc-500">{{ detail.code }}</span>
                        <span>{{ detail.title }}</span>
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                            :class="priorityClass(detail.priority)"
                        >
                            {{ PRIORITY_LABELS[detail.priority] }}
                        </span>
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                            :class="statusClass(detail.status)"
                        >
                            {{ detail.status_label }}
                        </span>
                    </DialogTitle>
                </DialogHeader>

                <div class="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                    <div class="space-y-4">
                        <p v-if="detail.description" class="rounded-lg border bg-zinc-50 p-3 text-sm whitespace-pre-line text-zinc-700">
                            {{ detail.description }}
                        </p>

                        <!-- Acciones -->
                        <div v-if="isOpen(detail)" class="space-y-3 rounded-lg border p-3">
                            <p class="text-xs font-bold tracking-wider text-zinc-500 uppercase">Atención</p>

                            <form class="flex flex-wrap items-end gap-2" @submit.prevent="submitAssign">
                                <div class="min-w-[180px] flex-1 space-y-1">
                                    <Label class="text-xs">Asignar a</Label>
                                    <select v-model="assignForm.assigned_to" class="h-9 w-full rounded-md border px-3 text-sm">
                                        <option value="">Seleccione técnico…</option>
                                        <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                                    </select>
                                </div>
                                <Button type="submit" size="sm" variant="outline" class="gap-1" :disabled="!assignForm.assigned_to">
                                    <UserPlus class="h-4 w-4" /> Asignar
                                </Button>
                            </form>

                            <form class="space-y-2" @submit.prevent="submitRespond">
                                <Label class="text-xs">
                                    {{
                                        detail.first_response_at
                                            ? 'Agregar comentario a la bitácora'
                                            : 'Registrar primera respuesta (sella el MTTRsp)'
                                    }}
                                </Label>
                                <textarea
                                    v-model="respondForm.note"
                                    rows="2"
                                    class="w-full rounded-md border px-3 py-2 text-sm"
                                    placeholder="Qué se comunicó o se hizo…"
                                />
                                <div class="flex items-center gap-2">
                                    <Button type="submit" size="sm" variant="outline" class="gap-1" :disabled="!respondForm.note">
                                        <MessageSquarePlus class="h-4 w-4" />
                                        {{ detail.first_response_at ? 'Comentar' : 'Registrar respuesta' }}
                                    </Button>
                                    <Button type="button" size="sm" variant="ghost" @click="submitStatus('en_espera')">Poner en espera</Button>
                                    <Button type="button" size="sm" variant="ghost" @click="submitStatus('en_proceso')">Reanudar</Button>
                                </div>
                            </form>

                            <form class="space-y-2 border-t pt-3" @submit.prevent="submitResolve">
                                <Label class="text-xs">Resolver</Label>
                                <textarea
                                    v-model="resolveForm.resolution_notes"
                                    rows="2"
                                    class="w-full rounded-md border px-3 py-2 text-sm"
                                    placeholder="Solución aplicada…"
                                />
                                <p v-if="resolveForm.errors.resolution_notes" class="text-xs text-red-500">
                                    {{ resolveForm.errors.resolution_notes }}
                                </p>
                                <label class="flex items-center gap-2 text-sm text-zinc-700">
                                    <input v-model="resolveForm.resolved_with_kedb" type="checkbox" class="h-4 w-4 rounded border" />
                                    Se resolvió usando un artículo de la KEDB
                                </label>
                                <select
                                    v-if="resolveForm.resolved_with_kedb"
                                    v-model="resolveForm.kedb_article_id"
                                    class="h-9 w-full rounded-md border px-3 text-sm"
                                >
                                    <option value="">Seleccione el artículo utilizado…</option>
                                    <option v-for="article in articles" :key="article.id" :value="article.id">
                                        {{ article.code }} — {{ article.title }}
                                    </option>
                                </select>
                                <p v-if="resolveForm.errors.kedb_article_id" class="text-xs text-red-500">
                                    {{ resolveForm.errors.kedb_article_id }}
                                </p>
                                <p v-if="resolveForm.errors.resolved_at" class="text-xs text-red-500">{{ resolveForm.errors.resolved_at }}</p>
                                <Button
                                    type="submit"
                                    size="sm"
                                    class="gap-1 bg-emerald-600 text-white hover:bg-emerald-700"
                                    :disabled="!resolveForm.resolution_notes"
                                >
                                    <CheckCircle2 class="h-4 w-4" /> Marcar resuelto
                                </Button>
                            </form>
                        </div>

                        <div v-else class="space-y-3 rounded-lg border p-3">
                            <p class="text-xs font-bold tracking-wider text-zinc-500 uppercase">Cierre</p>
                            <p v-if="detail.resolution_notes" class="text-sm whitespace-pre-line text-zinc-700">{{ detail.resolution_notes }}</p>
                            <div class="flex flex-wrap items-center gap-2">
                                <Button v-if="detail.status === 'resuelto'" size="sm" variant="outline" class="gap-1" @click="submitClose">
                                    <CheckCircle2 class="h-4 w-4" /> Cerrar ticket
                                </Button>
                                <Input v-model="reopenForm.note" placeholder="Motivo de reapertura" class="h-9 max-w-xs" />
                                <Button size="sm" variant="outline" class="gap-1" :disabled="!reopenForm.note" @click="submitReopen">
                                    <RotateCcw class="h-4 w-4" /> Reabrir
                                </Button>
                            </div>
                        </div>

                        <!-- Bitácora -->
                        <div class="rounded-lg border p-3">
                            <p class="mb-3 text-xs font-bold tracking-wider text-zinc-500 uppercase">Bitácora (Service Desk Log)</p>
                            <ol class="space-y-3">
                                <li v-for="event in detail.events ?? []" :key="event.id" class="flex gap-3">
                                    <div class="mt-1 h-2 w-2 shrink-0 rounded-full bg-indigo-500" />
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-zinc-800">
                                            {{ event.type }}
                                            <span v-if="event.from_status || event.to_status" class="text-xs font-normal text-zinc-500">
                                                ({{ event.from_status ?? '—' }} → {{ event.to_status ?? '—' }})
                                            </span>
                                        </p>
                                        <p v-if="event.note" class="text-sm whitespace-pre-line text-zinc-600">{{ event.note }}</p>
                                        <p class="text-xs text-zinc-400">
                                            {{ event.user?.name ?? 'Sistema' }} · {{ formatDateTime(event.created_at) }}
                                        </p>
                                    </div>
                                </li>
                                <li v-if="(detail.events ?? []).length === 0" class="text-sm text-zinc-400">Sin eventos registrados.</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Ficha -->
                    <dl class="space-y-2 rounded-lg border bg-zinc-50 p-3 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">Solicitante</dt>
                            <dd class="text-right font-medium text-zinc-800">{{ detail.requester_name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">Área</dt>
                            <dd class="text-right text-zinc-800">{{ detail.requester_area ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">Categoría</dt>
                            <dd class="text-right text-zinc-800">{{ detail.category?.name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">Impacto / Urgencia</dt>
                            <dd class="text-right text-zinc-800">I{{ detail.impact }} / U{{ detail.urgency }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">SLA aplicado</dt>
                            <dd class="text-right text-zinc-800">{{ detail.sla?.name ?? 'Sin política' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">Reportado</dt>
                            <dd class="text-right text-zinc-800">{{ formatDateTime(detail.reported_at) }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">Vence respuesta</dt>
                            <dd class="text-right text-zinc-800">{{ formatDateTime(detail.response_due_at) }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">1ª respuesta</dt>
                            <dd class="text-right" :class="detail.sla_response_met === false ? 'text-red-600' : 'text-zinc-800'">
                                {{ formatDateTime(detail.first_response_at) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">Vence resolución</dt>
                            <dd class="text-right text-zinc-800">{{ formatDateTime(detail.resolution_due_at) }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">Resuelto</dt>
                            <dd class="text-right" :class="detail.sla_resolution_met === false ? 'text-red-600' : 'text-zinc-800'">
                                {{ formatDateTime(detail.resolved_at) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2 border-t pt-2">
                            <dt class="text-zinc-500">MTTRsp</dt>
                            <dd class="text-right font-semibold text-zinc-900">{{ formatMinutes(detail.response_minutes) }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">MTTR</dt>
                            <dd class="text-right font-semibold text-zinc-900">{{ formatMinutes(detail.resolution_minutes) }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">KEDB</dt>
                            <dd class="text-right text-zinc-800">
                                {{ detail.kedb_article ? detail.kedb_article.code : detail.resolved_with_kedb ? 'Sin artículo' : 'No' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">Auditoría</dt>
                            <dd class="text-right text-zinc-800">
                                {{ detail.audit ? (detail.audit.is_correct ? 'Conforme' : 'Observada') : 'Pendiente' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">Satisfacción</dt>
                            <dd class="text-right text-zinc-800">{{ detail.survey ? `${detail.survey.score} / 5` : 'Sin encuesta' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">Reaperturas</dt>
                            <dd class="text-right text-zinc-800">{{ detail.reopened_count }}</dd>
                        </div>
                    </dl>
                </div>

                <p v-if="loadingDetail" class="flex items-center gap-2 text-xs text-zinc-400"><Clock class="h-3 w-3" /> Actualizando…</p>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
