<script setup lang="ts">
import ModuleTabs from '@/components/service-desk/ModuleTabs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMinutes, severityTone, statusTone } from '@/lib/service-desk';
import type { SdIndicator, SdTrendPoint, SdValidation } from '@/types/service-desk';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, FileSpreadsheet, Filter, Info, ShieldCheck, XCircle } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

const props = defineProps<{
    period: { from: string; to: string; days: number };
    summary: Record<string, number>;
    indicators: SdIndicator[];
    validations: SdValidation[];
    breakdown: {
        priority: { label: string; total: number }[];
        status: { label: string; total: number }[];
        category: { label: string; total: number }[];
    };
    trend: SdTrendPoint[];
    filters: { from: string; to: string };
}>();

// Metadatos de la matriz de operacionalización que no dependen de los datos: describen el
// diseño del instrumento, no el resultado del periodo.
const VARIABLES = {
    V1: { title: 'V1: Gestión de Incidencias basada en ITIL 4', role: 'Independiente', nature: 'Cualitativa / Cualicuantitativa' },
    V2: { title: 'V2: Eficiencia del Área de Sistemas', role: 'Dependiente', nature: 'Cuantitativa / Cualitativa' },
};

const range = reactive({ from: props.filters.from, to: props.filters.to });

const applyRange = () => {
    router.get(route('service-desk.indicators.index'), { ...range }, { preserveState: true, preserveScroll: true });
};

const exportUrl = computed(() => route('service-desk.indicators.export', { from: range.from, to: range.to }));

/** Filas de la matriz con el rowspan de cada variable, como en la tabla del marco metodológico. */
const matrixRows = computed(() =>
    props.indicators.map((indicator, index) => {
        const first = props.indicators.findIndex((i) => i.variable === indicator.variable) === index;
        return {
            indicator,
            isFirstOfVariable: first,
            variableSpan: props.indicators.filter((i) => i.variable === indicator.variable).length,
        };
    }),
);

const blocking = computed(() => props.validations.filter((check) => check.severity === 'error' && check.count > 0));
const warnings = computed(() => props.validations.filter((check) => check.severity === 'warning' && check.count > 0));

const compliance = computed(() => {
    const evaluated = props.indicators.filter((i) => i.status !== 'sin_datos');
    if (evaluated.length === 0) return null;
    return Math.round((evaluated.filter((i) => i.status === 'cumple').length * 100) / evaluated.length);
});

/** Barra de progreso del indicador: qué tanto del objetivo se alcanzó (acotada al 100%). */
const progress = (indicator: SdIndicator): number => {
    if (indicator.value === null || indicator.target === 0) return 0;
    const ratio = indicator.comparator === 'gte' ? indicator.value / indicator.target : indicator.target / indicator.value;
    return Math.max(0, Math.min(100, Math.round(ratio * 100)));
};

const severityIcon = (severity: string) => ({ ok: CheckCircle2, info: Info, warning: AlertTriangle, error: XCircle })[severity] ?? Info;

const trendValue = (value: number | null, kind: 'minutes' | 'percent') => {
    if (value === null) return '—';
    return kind === 'minutes' ? formatMinutes(value) : `${value.toFixed(1)}%`;
};
</script>

<template>
    <Head title="Indicadores · Service Desk" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-4 pb-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Tablero de indicadores</h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Matriz de operacionalización calculada con los datos del módulo: V1 Gestión de Incidencias (ITIL 4) y V2 Eficiencia del Área
                        de Sistemas.
                    </p>
                </div>
                <a :href="exportUrl">
                    <Button variant="outline" class="gap-2"><FileSpreadsheet class="h-4 w-4" /> Exportar Excel</Button>
                </a>
            </div>

            <ModuleTabs />

            <!-- Periodo -->
            <div class="bg-card flex flex-wrap items-end gap-3 rounded-xl border p-4 shadow-sm">
                <div class="space-y-1.5">
                    <Label class="text-xs">Desde</Label>
                    <Input v-model="range.from" type="date" class="h-9 w-[160px]" />
                </div>
                <div class="space-y-1.5">
                    <Label class="text-xs">Hasta</Label>
                    <Input v-model="range.to" type="date" class="h-9 w-[160px]" />
                </div>
                <Button class="gap-2 bg-indigo-600 text-white hover:bg-indigo-700" @click="applyRange"> <Filter class="h-4 w-4" /> Aplicar </Button>
                <div class="text-muted-foreground ml-auto flex flex-wrap items-center gap-4 text-sm">
                    <span>{{ period.days }} días analizados</span>
                    <span>{{ summary.total }} tickets</span>
                    <span v-if="compliance !== null" class="font-semibold text-zinc-700">{{ compliance }}% de indicadores en meta</span>
                </div>
            </div>

            <!-- Alertas de validación -->
            <div
                v-if="blocking.length > 0 || warnings.length > 0"
                class="rounded-xl border p-4 shadow-sm"
                :class="blocking.length > 0 ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50'"
            >
                <div class="flex items-start gap-3">
                    <AlertTriangle class="mt-0.5 h-5 w-5" :class="blocking.length > 0 ? 'text-red-600' : 'text-amber-600'" />
                    <div>
                        <p class="text-sm font-semibold" :class="blocking.length > 0 ? 'text-red-800' : 'text-amber-800'">
                            {{
                                blocking.length > 0
                                    ? 'Hay observaciones críticas en la fuente de datos: los indicadores marcados pueden no ser representativos.'
                                    : 'Hay advertencias de cobertura en la fuente de datos.'
                            }}
                        </p>
                        <ul class="mt-1 space-y-0.5 text-sm" :class="blocking.length > 0 ? 'text-red-700' : 'text-amber-700'">
                            <li v-for="check in [...blocking, ...warnings]" :key="check.key">
                                {{ check.label }}: <span class="font-semibold">{{ check.count }}</span> — afecta a {{ check.affects }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Fichas de indicadores -->
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="indicator in indicators" :key="indicator.key" class="bg-card flex flex-col gap-3 rounded-xl border p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                {{ indicator.variable }} · {{ indicator.dimension }}
                            </p>
                            <p class="mt-1 text-sm font-semibold text-zinc-900">{{ indicator.name }}</p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                            :class="statusTone(indicator.status).badge"
                        >
                            {{ statusTone(indicator.status).label }}
                        </span>
                    </div>

                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-bold text-zinc-900">{{ indicator.display }}</span>
                        <span class="text-muted-foreground text-xs">meta {{ indicator.target_display }}</span>
                    </div>

                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="statusTone(indicator.status).bar"
                            :style="{ width: progress(indicator) + '%' }"
                        />
                    </div>

                    <div class="text-muted-foreground space-y-1 text-xs">
                        <p>{{ indicator.unit }} · Fuente: {{ indicator.source }}</p>
                        <p v-if="indicator.numerator !== undefined">{{ indicator.numerator }} de {{ indicator.denominator }} casos</p>
                        <p>n = {{ indicator.sample }} {{ indicator.sample_label }}</p>
                        <p v-if="indicator.sample_notice" class="font-medium text-amber-600">{{ indicator.sample_notice }}</p>
                    </div>
                </div>
            </div>

            <!-- Matriz de operacionalización -->
            <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                <div class="border-b p-4">
                    <h2 class="font-semibold text-zinc-900">Matriz de operacionalización de variables</h2>
                    <p class="text-muted-foreground text-sm">
                        Cada indicador se calcula sobre su fuente de datos y se contrasta con la meta vigente del área.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="p-3 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Variable</th>
                                <th class="p-3 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Naturaleza</th>
                                <th class="p-3 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Dimensiones</th>
                                <th class="p-3 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Indicadores</th>
                                <th class="p-3 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Unidad de medida</th>
                                <th class="p-3 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Fuente de datos</th>
                                <th class="p-3 text-right text-xs font-bold tracking-wider text-zinc-500 uppercase">Valor</th>
                                <th class="p-3 text-right text-xs font-bold tracking-wider text-zinc-500 uppercase">Meta</th>
                                <th class="p-3 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Validación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in matrixRows" :key="row.indicator.key" class="border-t align-top">
                                <td v-if="row.isFirstOfVariable" :rowspan="row.variableSpan" class="bg-zinc-50/60 p-3">
                                    <p class="font-semibold text-zinc-900">{{ VARIABLES[row.indicator.variable].title }}</p>
                                    <p class="text-xs text-zinc-500 italic">({{ VARIABLES[row.indicator.variable].role }})</p>
                                </td>
                                <td v-if="row.isFirstOfVariable" :rowspan="row.variableSpan" class="bg-zinc-50/60 p-3 text-zinc-700">
                                    {{ VARIABLES[row.indicator.variable].nature }}
                                </td>
                                <td class="p-3 text-zinc-700">{{ row.indicator.dimension }}</td>
                                <td class="p-3">
                                    <p class="font-medium text-zinc-900">{{ row.indicator.name }}</p>
                                    <p class="mt-0.5 text-xs text-zinc-500">{{ row.indicator.formula }}</p>
                                </td>
                                <td class="p-3 text-zinc-700">{{ row.indicator.unit }}</td>
                                <td class="p-3 text-zinc-700">{{ row.indicator.source }}</td>
                                <td class="p-3 text-right font-bold text-zinc-900">{{ row.indicator.display }}</td>
                                <td class="p-3 text-right text-zinc-600">{{ row.indicator.target_display }}</td>
                                <td class="p-3 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                        :class="statusTone(row.indicator.status).badge"
                                    >
                                        {{ statusTone(row.indicator.status).label }}
                                    </span>
                                    <p v-if="!row.indicator.reliable" class="mt-1 text-[11px] text-amber-600">muestra insuficiente</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Validación de la fuente -->
            <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                <div class="flex items-center gap-2 border-b p-4">
                    <ShieldCheck class="h-5 w-5 text-indigo-600" />
                    <div>
                        <h2 class="font-semibold text-zinc-900">Validación de la fuente de datos</h2>
                        <p class="text-muted-foreground text-sm">
                            Controles de integridad sobre los Service Desk Logs del periodo. Un indicador solo es defendible si su fuente pasa estos
                            controles.
                        </p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="p-3 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Control</th>
                                <th class="p-3 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Casos</th>
                                <th class="p-3 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Severidad</th>
                                <th class="p-3 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Indicador afectado</th>
                                <th class="p-3 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Acción recomendada</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="check in validations" :key="check.key" class="border-t align-top">
                                <td class="p-3">
                                    <div class="flex items-start gap-2">
                                        <component
                                            :is="severityIcon(check.severity)"
                                            class="mt-0.5 h-4 w-4 shrink-0"
                                            :class="
                                                check.severity === 'ok'
                                                    ? 'text-emerald-500'
                                                    : check.severity === 'error'
                                                      ? 'text-red-500'
                                                      : 'text-amber-500'
                                            "
                                        />
                                        <div>
                                            <p class="font-medium text-zinc-900">{{ check.label }}</p>
                                            <p v-if="check.samples.length > 0" class="mt-0.5 font-mono text-[11px] text-zinc-500">
                                                {{ check.samples.map((s) => s.code).join(', ') }}{{ check.count > check.samples.length ? '…' : '' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td
                                    class="p-3 text-center font-bold"
                                    :class="check.count > 0 && check.severity !== 'ok' ? 'text-red-600' : 'text-zinc-500'"
                                >
                                    {{ check.count }}
                                </td>
                                <td class="p-3 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                        :class="severityTone(check.severity).badge"
                                    >
                                        {{ severityTone(check.severity).label }}
                                    </span>
                                </td>
                                <td class="p-3 text-zinc-700">{{ check.affects }}</td>
                                <td class="p-3 text-zinc-600">{{ check.hint }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Evolución -->
                <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                    <div class="border-b p-4">
                        <h2 class="font-semibold text-zinc-900">Evolución mensual</h2>
                        <p class="text-muted-foreground text-sm">Tiempos y cumplimiento de SLA por mes de reporte.</p>
                    </div>
                    <table class="w-full table-auto border-collapse text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="p-3 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Mes</th>
                                <th class="p-3 text-right text-xs font-bold tracking-wider text-zinc-500 uppercase">Tickets</th>
                                <th class="p-3 text-right text-xs font-bold tracking-wider text-zinc-500 uppercase">MTTR</th>
                                <th class="p-3 text-right text-xs font-bold tracking-wider text-zinc-500 uppercase">MTTRsp</th>
                                <th class="p-3 text-right text-xs font-bold tracking-wider text-zinc-500 uppercase">SLA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="point in trend" :key="point.month" class="border-t">
                                <td class="p-3 font-medium text-zinc-800">{{ point.month }}</td>
                                <td class="p-3 text-right text-zinc-700">{{ point.tickets }}</td>
                                <td class="p-3 text-right text-zinc-700">{{ trendValue(point.mttr, 'minutes') }}</td>
                                <td class="p-3 text-right text-zinc-700">{{ trendValue(point.mttr_response, 'minutes') }}</td>
                                <td class="p-3 text-right text-zinc-700">{{ trendValue(point.sla, 'percent') }}</td>
                            </tr>
                            <tr v-if="trend.length === 0">
                                <td colspan="5" class="p-6 text-center text-sm text-zinc-400">Sin tickets en el periodo seleccionado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Distribución -->
                <div class="bg-card space-y-4 rounded-xl border p-4 shadow-sm">
                    <div>
                        <h2 class="font-semibold text-zinc-900">Distribución del periodo</h2>
                        <p class="text-muted-foreground text-sm">Cómo se repartieron las incidencias por prioridad y categoría.</p>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-bold tracking-wider text-zinc-500 uppercase">Por prioridad</p>
                        <div class="space-y-1.5">
                            <div v-for="item in breakdown.priority" :key="item.label" class="flex items-center gap-2 text-sm">
                                <span class="w-24 shrink-0 text-zinc-600">{{ item.label }}</span>
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100">
                                    <div
                                        class="h-full rounded-full bg-indigo-500"
                                        :style="{ width: (summary.total ? (item.total * 100) / summary.total : 0) + '%' }"
                                    />
                                </div>
                                <span class="w-8 text-right font-semibold text-zinc-800">{{ item.total }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-bold tracking-wider text-zinc-500 uppercase">Por categoría</p>
                        <div class="space-y-1.5">
                            <div v-for="item in breakdown.category.slice(0, 8)" :key="item.label" class="flex items-center gap-2 text-sm">
                                <span class="w-36 shrink-0 truncate text-zinc-600">{{ item.label }}</span>
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100">
                                    <div
                                        class="h-full rounded-full bg-emerald-500"
                                        :style="{ width: (summary.total ? (item.total * 100) / summary.total : 0) + '%' }"
                                    />
                                </div>
                                <span class="w-8 text-right font-semibold text-zinc-800">{{ item.total }}</span>
                            </div>
                            <p v-if="breakdown.category.length === 0" class="text-sm text-zinc-400">Sin datos en el periodo.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
