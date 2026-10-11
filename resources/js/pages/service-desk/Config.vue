<script setup lang="ts">
import ModuleTabs from '@/components/service-desk/ModuleTabs.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { DIMENSION_LABELS, PRIORITY_LABELS, formatMinutes, priorityClass } from '@/lib/service-desk';
import type { SdCategory, SdMaturityQuestion, SdSla } from '@/types/service-desk';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Target, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    categories: SdCategory[];
    slas: SdSla[];
    questions: SdMaturityQuestion[];
    matrix: Record<string, Record<string, number>>;
    targets: Record<string, number>;
    minSample: Record<string, number>;
    minCoverage: Record<string, number>;
    dimensions: string[];
}>();

const IMPACT_NAMES: Record<string, string> = { 1: 'Alto', 2: 'Medio', 3: 'Bajo' };
const URGENCY_NAMES: Record<string, string> = { 1: 'Alta', 2: 'Media', 3: 'Baja' };

const TARGET_LABELS: Record<string, string> = {
    estandarizacion: 'Nivel de estandarización del flujo (1–5)',
    kedb_usage: 'Uso de la KEDB (%)',
    classification: 'Clasificación correcta (%)',
    mttr: 'MTTR máximo (minutos)',
    mttr_response: 'MTTRsp máximo (minutos)',
    sla_compliance: 'Cumplimiento de SLA (%)',
    satisfaction: 'Satisfacción del usuario (1–5)',
};

// ── Categorías ────────────────────────────────────────────────────────────
const showCategory = ref(false);
const editingCategory = ref<number | null>(null);

const categoryForm = useForm({ code: '', name: '', description: '', parent_id: '' as number | string, is_active: true });

const openCategory = (category?: SdCategory) => {
    categoryForm.reset();
    categoryForm.clearErrors();
    editingCategory.value = category?.id ?? null;
    if (category) {
        categoryForm.code = category.code ?? '';
        categoryForm.name = category.name;
        categoryForm.description = category.description ?? '';
        categoryForm.parent_id = category.parent_id ?? '';
        categoryForm.is_active = category.is_active ?? true;
    }
    showCategory.value = true;
};

const saveCategory = () => {
    const options = { preserveScroll: true, onSuccess: () => (showCategory.value = false) };
    if (editingCategory.value) {
        categoryForm.put(route('service-desk.config.categories.update', editingCategory.value), options);
    } else {
        categoryForm.post(route('service-desk.config.categories.store'), options);
    }
};

const destroyCategory = (category: SdCategory) => {
    if (!confirm(`¿Eliminar la categoría "${category.name}"? Si tiene tickets solo se desactivará.`)) return;
    router.delete(route('service-desk.config.categories.destroy', category.id), { preserveScroll: true });
};

// ── SLA ───────────────────────────────────────────────────────────────────
const editingSla = ref<SdSla | null>(null);
const slaForm = useForm({ name: '', response_minutes: 0, resolution_minutes: 0, description: '', is_active: true });

const openSla = (sla: SdSla) => {
    editingSla.value = sla;
    slaForm.clearErrors();
    slaForm.name = sla.name;
    slaForm.response_minutes = sla.response_minutes;
    slaForm.resolution_minutes = sla.resolution_minutes;
    slaForm.description = sla.description ?? '';
    slaForm.is_active = sla.is_active;
};

const saveSla = () => {
    if (!editingSla.value) return;
    slaForm.put(route('service-desk.config.slas.update', editingSla.value.id), {
        preserveScroll: true,
        onSuccess: () => (editingSla.value = null),
    });
};

// ── Ítems del cuestionario ────────────────────────────────────────────────
const showQuestion = ref(false);
const editingQuestion = ref<number | null>(null);

const questionForm = useForm({ code: '', dimension: props.dimensions[0] ?? 'estandarizacion', statement: '', is_active: true });

const openQuestion = (question?: SdMaturityQuestion) => {
    questionForm.reset();
    questionForm.clearErrors();
    editingQuestion.value = question?.id ?? null;
    if (question) {
        questionForm.code = question.code;
        questionForm.dimension = question.dimension;
        questionForm.statement = question.statement;
        questionForm.is_active = question.is_active;
    }
    showQuestion.value = true;
};

const saveQuestion = () => {
    const options = { preserveScroll: true, onSuccess: () => (showQuestion.value = false) };
    if (editingQuestion.value) {
        questionForm.put(route('service-desk.config.questions.update', editingQuestion.value), options);
    } else {
        questionForm.post(route('service-desk.config.questions.store'), options);
    }
};

const destroyQuestion = (question: SdMaturityQuestion) => {
    if (!confirm(`¿Eliminar el ítem ${question.code}? Si ya fue respondido solo se desactivará.`)) return;
    router.delete(route('service-desk.config.questions.destroy', question.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Configuración · Service Desk" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-4 pb-8">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Configuración del Service Desk</h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    Catálogo de categorías, acuerdos de nivel de servicio, matriz de priorización y cuestionario de madurez. Es la referencia con la
                    que se interpretan los indicadores.
                </p>
            </div>

            <ModuleTabs />

            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Matriz -->
                <div class="bg-card rounded-xl border p-4 shadow-sm">
                    <h2 class="font-semibold text-zinc-900">Matriz de priorización (ITIL)</h2>
                    <p class="text-muted-foreground mb-3 text-sm">
                        La prioridad nunca se captura a mano: se deriva del cruce impacto × urgencia al registrar o reclasificar el ticket.
                    </p>
                    <table class="w-full table-auto border-collapse text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="p-2 text-left text-xs font-bold text-zinc-500 uppercase">Impacto \ Urgencia</th>
                                <th
                                    v-for="(name, urgency) in URGENCY_NAMES"
                                    :key="urgency"
                                    class="p-2 text-center text-xs font-bold text-zinc-500 uppercase"
                                >
                                    {{ name }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, impact) in matrix" :key="impact" class="border-t">
                                <td class="p-2 font-medium text-zinc-700">{{ IMPACT_NAMES[impact] }}</td>
                                <td v-for="(priority, urgency) in row" :key="urgency" class="p-2 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                        :class="priorityClass(priority)"
                                    >
                                        {{ PRIORITY_LABELS[priority] }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Metas -->
                <div class="bg-card rounded-xl border p-4 shadow-sm">
                    <div class="mb-3 flex items-center gap-2">
                        <Target class="h-5 w-5 text-indigo-600" />
                        <div>
                            <h2 class="font-semibold text-zinc-900">Metas vigentes</h2>
                            <p class="text-muted-foreground text-sm">Definidas en <code class="text-xs">config/servicedesk.php</code>.</p>
                        </div>
                    </div>
                    <dl class="space-y-1.5 text-sm">
                        <div v-for="(value, key) in targets" :key="key" class="flex justify-between gap-3 border-b pb-1.5">
                            <dt class="text-zinc-600">{{ TARGET_LABELS[key] ?? key }}</dt>
                            <dd class="font-semibold text-zinc-900">{{ value }}</dd>
                        </div>
                    </dl>
                    <p class="text-muted-foreground mt-3 text-xs">
                        Muestra mínima: {{ minSample.tickets }} tickets · {{ minSample.surveys }} encuestas · {{ minSample.maturity }} cuestionarios.
                        Cobertura mínima: {{ minCoverage.audit }}% de auditoría y {{ minCoverage.survey }}% de encuestas.
                    </p>
                </div>
            </div>

            <!-- SLA -->
            <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                <div class="border-b p-4">
                    <h2 class="font-semibold text-zinc-900">Acuerdos de nivel de servicio</h2>
                    <p class="text-muted-foreground text-sm">
                        Los cambios aplican a los tickets que se registren desde ahora: los vencimientos ya calculados no se reescriben.
                    </p>
                </div>
                <table class="w-full table-auto border-collapse">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Prioridad</th>
                            <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Política</th>
                            <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Respuesta</th>
                            <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Resolución</th>
                            <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Estado</th>
                            <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="sla in slas" :key="sla.id" class="hover:bg-muted/30 border-t">
                            <td class="p-4">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                    :class="priorityClass(sla.priority)"
                                >
                                    {{ PRIORITY_LABELS[sla.priority] }}
                                </span>
                            </td>
                            <td class="p-4">
                                <p class="text-sm font-semibold text-zinc-900">{{ sla.name }}</p>
                                <p class="text-xs text-zinc-500">{{ sla.description ?? '—' }}</p>
                            </td>
                            <td class="p-4 text-center text-sm text-zinc-700">{{ formatMinutes(sla.response_minutes) }}</td>
                            <td class="p-4 text-center text-sm text-zinc-700">{{ formatMinutes(sla.resolution_minutes) }}</td>
                            <td class="p-4 text-center">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                    :class="
                                        sla.is_active
                                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                                            : 'bg-zinc-100 text-zinc-500 ring-zinc-400/20'
                                    "
                                >
                                    {{ sla.is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-zinc-500 hover:bg-indigo-50 hover:text-indigo-600"
                                    @click="openSla(sla)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Categorías -->
            <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b p-4">
                    <div>
                        <h2 class="font-semibold text-zinc-900">Categorías de incidencia</h2>
                        <p class="text-muted-foreground text-sm">Un catálogo cerrado es lo que hace auditable la categorización.</p>
                    </div>
                    <Button variant="outline" class="gap-2" @click="openCategory()"><Plus class="h-4 w-4" /> Nueva categoría</Button>
                </div>
                <table class="w-full table-auto border-collapse">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Código</th>
                            <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Nombre</th>
                            <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Descripción</th>
                            <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Tickets</th>
                            <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Estado</th>
                            <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="category in categories" :key="category.id" class="hover:bg-muted/30 border-t">
                            <td class="p-4 font-mono text-xs text-zinc-500">{{ category.code }}</td>
                            <td class="p-4 text-sm font-semibold text-zinc-900">{{ category.name }}</td>
                            <td class="p-4 text-sm text-zinc-600">{{ category.description ?? '—' }}</td>
                            <td class="p-4 text-center text-sm text-zinc-700">{{ category.tickets_count ?? 0 }}</td>
                            <td class="p-4 text-center">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                    :class="
                                        category.is_active
                                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                                            : 'bg-zinc-100 text-zinc-500 ring-zinc-400/20'
                                    "
                                >
                                    {{ category.is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-zinc-500 hover:bg-indigo-50 hover:text-indigo-600"
                                        @click="openCategory(category)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-zinc-500 hover:bg-red-50 hover:text-red-600"
                                        @click="destroyCategory(category)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Cuestionario -->
            <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b p-4">
                    <div>
                        <h2 class="font-semibold text-zinc-900">Cuestionario de madurez ITIL 4</h2>
                        <p class="text-muted-foreground text-sm">
                            Los ítems de la dimensión «Estandarización del flujo» son los que calculan el indicador de la V1.
                        </p>
                    </div>
                    <Button variant="outline" class="gap-2" @click="openQuestion()"><Plus class="h-4 w-4" /> Nuevo ítem</Button>
                </div>
                <table class="w-full table-auto border-collapse">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Código</th>
                            <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Dimensión</th>
                            <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Enunciado</th>
                            <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Estado</th>
                            <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="question in questions" :key="question.id" class="hover:bg-muted/30 border-t align-top">
                            <td class="p-4 font-mono text-xs text-zinc-500">{{ question.code }}</td>
                            <td class="p-4 text-sm text-zinc-700">{{ DIMENSION_LABELS[question.dimension] ?? question.dimension }}</td>
                            <td class="p-4 text-sm text-zinc-600">{{ question.statement }}</td>
                            <td class="p-4 text-center">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                    :class="
                                        question.is_active
                                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                                            : 'bg-zinc-100 text-zinc-500 ring-zinc-400/20'
                                    "
                                >
                                    {{ question.is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-zinc-500 hover:bg-indigo-50 hover:text-indigo-600"
                                        @click="openQuestion(question)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-zinc-500 hover:bg-red-50 hover:text-red-600"
                                        @click="destroyQuestion(question)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal categoría -->
        <Dialog v-model:open="showCategory">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>{{ editingCategory ? 'Editar categoría' : 'Nueva categoría' }}</DialogTitle>
                </DialogHeader>
                <form class="mt-2 space-y-4" @submit.prevent="saveCategory">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Código <span class="text-red-500">*</span></Label>
                            <Input v-model="categoryForm.code" placeholder="Ej. HW" />
                            <p v-if="categoryForm.errors.code" class="text-xs text-red-500">{{ categoryForm.errors.code }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Categoría padre</Label>
                            <select v-model="categoryForm.parent_id" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option value="">Sin padre</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Nombre <span class="text-red-500">*</span></Label>
                        <Input v-model="categoryForm.name" />
                        <p v-if="categoryForm.errors.name" class="text-xs text-red-500">{{ categoryForm.errors.name }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Descripción</Label>
                        <textarea v-model="categoryForm.description" rows="2" class="w-full rounded-md border px-3 py-2 text-sm" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-zinc-700">
                        <input v-model="categoryForm.is_active" type="checkbox" class="h-4 w-4 rounded border" /> Activa
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="showCategory = false">Cancelar</Button>
                        <Button type="submit" :disabled="categoryForm.processing" class="bg-indigo-600 text-white hover:bg-indigo-700"
                            >Guardar</Button
                        >
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal SLA -->
        <Dialog :open="editingSla !== null" @update:open="(value: boolean) => !value && (editingSla = null)">
            <DialogContent v-if="editingSla" class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>Editar {{ PRIORITY_LABELS[editingSla.priority] }}</DialogTitle>
                </DialogHeader>
                <form class="mt-2 space-y-4" @submit.prevent="saveSla">
                    <div class="space-y-1.5">
                        <Label>Nombre <span class="text-red-500">*</span></Label>
                        <Input v-model="slaForm.name" />
                        <p v-if="slaForm.errors.name" class="text-xs text-red-500">{{ slaForm.errors.name }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Minutos para responder</Label>
                            <Input v-model.number="slaForm.response_minutes" type="number" min="1" />
                            <p v-if="slaForm.errors.response_minutes" class="text-xs text-red-500">{{ slaForm.errors.response_minutes }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Minutos para resolver</Label>
                            <Input v-model.number="slaForm.resolution_minutes" type="number" min="1" />
                            <p v-if="slaForm.errors.resolution_minutes" class="text-xs text-red-500">{{ slaForm.errors.resolution_minutes }}</p>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Descripción</Label>
                        <textarea v-model="slaForm.description" rows="2" class="w-full rounded-md border px-3 py-2 text-sm" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-zinc-700">
                        <input v-model="slaForm.is_active" type="checkbox" class="h-4 w-4 rounded border" /> Política activa
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="editingSla = null">Cancelar</Button>
                        <Button type="submit" :disabled="slaForm.processing" class="bg-indigo-600 text-white hover:bg-indigo-700">Guardar</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal ítem -->
        <Dialog v-model:open="showQuestion">
            <DialogContent class="sm:max-w-[520px]">
                <DialogHeader>
                    <DialogTitle>{{ editingQuestion ? 'Editar ítem' : 'Nuevo ítem del cuestionario' }}</DialogTitle>
                </DialogHeader>
                <form class="mt-2 space-y-4" @submit.prevent="saveQuestion">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Código <span class="text-red-500">*</span></Label>
                            <Input v-model="questionForm.code" placeholder="Ej. EST-06" />
                            <p v-if="questionForm.errors.code" class="text-xs text-red-500">{{ questionForm.errors.code }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Dimensión <span class="text-red-500">*</span></Label>
                            <select v-model="questionForm.dimension" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option v-for="dimension in dimensions" :key="dimension" :value="dimension">
                                    {{ DIMENSION_LABELS[dimension] ?? dimension }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Enunciado <span class="text-red-500">*</span></Label>
                        <textarea v-model="questionForm.statement" rows="3" class="w-full rounded-md border px-3 py-2 text-sm" />
                        <p v-if="questionForm.errors.statement" class="text-xs text-red-500">{{ questionForm.errors.statement }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-zinc-700">
                        <input v-model="questionForm.is_active" type="checkbox" class="h-4 w-4 rounded border" /> Activo
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="showQuestion = false">Cancelar</Button>
                        <Button type="submit" :disabled="questionForm.processing" class="bg-indigo-600 text-white hover:bg-indigo-700"
                            >Guardar</Button
                        >
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
