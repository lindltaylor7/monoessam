<?php

namespace App\Services\ServiceDesk;

use App\Models\ServiceDeskMaturityAnswer;
use App\Models\ServiceDeskMaturityAssessment;
use App\Models\ServiceDeskSatisfactionSurvey;
use App\Models\ServiceDeskTicket;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula y valida los siete indicadores de la matriz de operacionalizacion del estudio.
 *
 * Dos ideas sostienen la clase:
 *
 *  1. Un indicador no es solo un numero: viaja con su ficha (variable, dimension, unidad,
 *     formula, fuente de datos), su meta, el tamano de muestra con el que se calculo y un
 *     semaforo. Asi el tablero puede mostrar lo mismo que la matriz del marco metodologico.
 *
 *  2. Antes de leer el numero hay que poder confiar en el. `validations()` revisa la
 *     integridad de la fuente (tickets sin hitos, fechas incoherentes, cobertura de
 *     auditoria y de encuestas, muestras insuficientes) y es lo que convierte al modulo en
 *     una herramienta de validacion y no en un contador.
 *
 * La poblacion de tickets de un periodo se define siempre por `reported_at`: es la fecha
 * del hecho, no la de carga, de modo que un registro tardio no mueve indicadores pasados.
 */
class IndicatorCalculator
{
    private Carbon $from;

    private Carbon $to;

    /** @var Collection<int, ServiceDeskTicket> */
    private Collection $tickets;

    public function __construct(?string $from = null, ?string $to = null)
    {
        $this->to = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        $this->from = $from ? Carbon::parse($from)->startOfDay() : $this->to->copy()->subDays(89)->startOfDay();

        if ($this->from->greaterThan($this->to)) {
            [$this->from, $this->to] = [$this->to->copy()->startOfDay(), $this->from->copy()->endOfDay()];
        }

        $this->tickets = ServiceDeskTicket::with(['audit', 'survey', 'category'])
            ->whereBetween('reported_at', [$this->from, $this->to])
            ->get();
    }

    public function from(): Carbon
    {
        return $this->from;
    }

    public function to(): Carbon
    {
        return $this->to;
    }

    /**
     * Todo lo que consume el tablero en una sola estructura.
     */
    public function payload(): array
    {
        return [
            'period'      => [
                'from'  => $this->from->toDateString(),
                'to'    => $this->to->toDateString(),
                'days'  => $this->from->diffInDays($this->to) + 1,
            ],
            'summary'     => $this->summary(),
            'indicators'  => $this->indicators(),
            'validations' => $this->validations(),
            'breakdown'   => $this->breakdown(),
            'trend'       => $this->trend(),
        ];
    }

    // ── Indicadores ───────────────────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    public function indicators(): array
    {
        return [
            $this->standardizationIndicator(),
            $this->kedbUsageIndicator(),
            $this->classificationIndicator(),
            $this->mttrIndicator(),
            $this->responseTimeIndicator(),
            $this->slaComplianceIndicator(),
            $this->satisfactionIndicator(),
        ];
    }

    /**
     * V1 / Adopcion de Practicas ITIL 4 - Nivel de estandarizacion del flujo de incidencias.
     * Promedio de los items de dimension "estandarizacion" del cuestionario al personal de TI.
     */
    private function standardizationIndicator(): array
    {
        $assessments = ServiceDeskMaturityAssessment::whereBetween('answered_at', [$this->from, $this->to])->pluck('id');

        $average = $assessments->isEmpty() ? null : ServiceDeskMaturityAnswer::whereIn('assessment_id', $assessments)
            ->whereHas('question', fn ($q) => $q->where('dimension', 'estandarizacion'))
            ->avg('score');

        return $this->indicator([
            'key'       => 'estandarizacion',
            'variable'  => 'V1',
            'dimension' => 'Adopcion de Practicas ITIL 4',
            'name'      => 'Nivel de estandarizacion del flujo de incidencias',
            'unit'      => 'Escala ordinal (1 a 5)',
            'source'    => 'Cuestionario a personal TI',
            'formula'   => 'Promedio de los items de la dimension Estandarizacion respondidos en el periodo',
            'value'     => $average === null ? null : round((float) $average, 2),
            'target'    => (float) config('servicedesk.targets.estandarizacion'),
            'comparator' => 'gte',
            'scale'     => 5,
            'format'    => 'scale',
            'sample'    => $assessments->count(),
            'sample_label' => 'cuestionarios aplicados',
            'min_sample' => (int) config('servicedesk.min_sample.maturity'),
        ]);
    }

    /**
     * V1 / Gestion del Conocimiento - % de tickets resueltos apoyandose en la KEDB.
     */
    private function kedbUsageIndicator(): array
    {
        $resolved = $this->tickets->filter(fn ($t) => $t->resolved_at !== null);
        $withKedb = $resolved->filter(fn ($t) => (bool) $t->resolved_with_kedb);

        return $this->indicator([
            'key'       => 'kedb_usage',
            'variable'  => 'V1',
            'dimension' => 'Gestion del Conocimiento',
            'name'      => 'Uso de la base de conocimientos (KEDB)',
            'unit'      => '% de tickets resueltos con KEDB',
            'source'    => 'Service Desk Logs',
            'formula'   => '(Tickets resueltos con articulo KEDB / Tickets resueltos) x 100',
            'value'     => $this->percentage($withKedb->count(), $resolved->count()),
            'target'    => (float) config('servicedesk.targets.kedb_usage'),
            'comparator' => 'gte',
            'format'    => 'percent',
            'sample'    => $resolved->count(),
            'sample_label' => 'tickets resueltos',
            'min_sample' => (int) config('servicedesk.min_sample.tickets'),
            'numerator' => $withKedb->count(),
            'denominator' => $resolved->count(),
        ]);
    }

    /**
     * V1 / Priorizacion y Categorizacion - % de tickets correctamente clasificados.
     * Solo entran al calculo los tickets que efectivamente fueron auditados; la cobertura
     * de esa auditoria se reporta aparte en validations().
     */
    private function classificationIndicator(): array
    {
        $audited = $this->tickets->filter(fn ($t) => $t->audit !== null);
        $correct = $audited->filter(fn ($t) => (bool) $t->audit->is_correct);

        return $this->indicator([
            'key'       => 'classification',
            'variable'  => 'V1',
            'dimension' => 'Priorizacion y Categorizacion',
            'name'      => 'Clasificacion correcta de tickets por impacto/urgencia',
            'unit'      => '% de tickets categorizados correctamente',
            'source'    => 'Auditoria de Logs',
            'formula'   => '(Tickets auditados como correctos / Tickets auditados) x 100',
            'value'     => $this->percentage($correct->count(), $audited->count()),
            'target'    => (float) config('servicedesk.targets.classification'),
            'comparator' => 'gte',
            'format'    => 'percent',
            'sample'    => $audited->count(),
            'sample_label' => 'tickets auditados',
            'min_sample' => (int) config('servicedesk.min_sample.tickets'),
            'numerator' => $correct->count(),
            'denominator' => $audited->count(),
        ]);
    }

    /**
     * V2 / Tiempos de Respuesta y Solucion - Tiempo Medio de Reparacion (MTTR).
     */
    private function mttrIndicator(): array
    {
        $values = $this->tickets
            ->map(fn ($t) => $t->resolution_minutes)
            ->filter(fn ($m) => $m !== null && $m >= 0)
            ->values();

        return $this->indicator([
            'key'       => 'mttr',
            'variable'  => 'V2',
            'dimension' => 'Tiempos de Respuesta y Solucion',
            'name'      => 'Tiempo Medio de Reparacion (MTTR)',
            'unit'      => 'Horas / Minutos',
            'source'    => 'Service Desk Logs',
            'formula'   => 'Suma(resolved_at - reported_at) / Tickets resueltos',
            'value'     => $values->isEmpty() ? null : round((float) $values->avg(), 2),
            'target'    => (float) config('servicedesk.targets.mttr'),
            'comparator' => 'lte',
            'format'    => 'duration',
            'sample'    => $values->count(),
            'sample_label' => 'tickets resueltos con fechas validas',
            'min_sample' => (int) config('servicedesk.min_sample.tickets'),
        ]);
    }

    /**
     * V2 / Tiempos de Respuesta y Solucion - Tiempo Medio de Respuesta (MTTRsp).
     */
    private function responseTimeIndicator(): array
    {
        $values = $this->tickets
            ->map(fn ($t) => $t->response_minutes)
            ->filter(fn ($m) => $m !== null && $m >= 0)
            ->values();

        return $this->indicator([
            'key'       => 'mttr_response',
            'variable'  => 'V2',
            'dimension' => 'Tiempos de Respuesta y Solucion',
            'name'      => 'Tiempo Medio de Respuesta (MTTRsp)',
            'unit'      => 'Minutos',
            'source'    => 'Service Desk Logs',
            'formula'   => 'Suma(first_response_at - reported_at) / Tickets con primera respuesta',
            'value'     => $values->isEmpty() ? null : round((float) $values->avg(), 2),
            'target'    => (float) config('servicedesk.targets.mttr_response'),
            'comparator' => 'lte',
            'format'    => 'minutes',
            'sample'    => $values->count(),
            'sample_label' => 'tickets con primera respuesta',
            'min_sample' => (int) config('servicedesk.min_sample.tickets'),
        ]);
    }

    /**
     * V2 / Cumplimiento de Niveles de Servicio - Tasa de cumplimiento de SLA.
     * Un ticket cumple solo si respeto ambos compromisos (respuesta y resolucion).
     */
    private function slaComplianceIndicator(): array
    {
        $measurable = $this->tickets->filter(fn ($t) => $t->sla_met !== null);
        $met = $measurable->filter(fn ($t) => $t->sla_met === true);

        return $this->indicator([
            'key'       => 'sla_compliance',
            'variable'  => 'V2',
            'dimension' => 'Cumplimiento de Niveles de Servicio',
            'name'      => 'Tasa de cumplimiento de SLA de atencion',
            'unit'      => 'Porcentaje (%)',
            'source'    => 'Service Desk Logs',
            'formula'   => '(Tickets dentro de SLA de respuesta y resolucion / Tickets medibles) x 100',
            'value'     => $this->percentage($met->count(), $measurable->count()),
            'target'    => (float) config('servicedesk.targets.sla_compliance'),
            'comparator' => 'gte',
            'format'    => 'percent',
            'sample'    => $measurable->count(),
            'sample_label' => 'tickets con SLA medible',
            'min_sample' => (int) config('servicedesk.min_sample.tickets'),
            'numerator' => $met->count(),
            'denominator' => $measurable->count(),
        ]);
    }

    /**
     * V2 / Calidad Percibida del Servicio - Percepcion global del usuario (Likert 1..5).
     */
    private function satisfactionIndicator(): array
    {
        $surveys = ServiceDeskSatisfactionSurvey::whereBetween('answered_at', [$this->from, $this->to])->get();

        $satisfied = $surveys->filter(fn ($s) => $s->score >= 4)->count();

        return $this->indicator([
            'key'       => 'satisfaction',
            'variable'  => 'V2',
            'dimension' => 'Calidad Percibida del Servicio',
            'name'      => 'Percepcion global de satisfaccion del usuario',
            'unit'      => 'Escala Likert (1 a 5)',
            'source'    => 'Cuestionario a usuarios',
            'formula'   => 'Promedio de la percepcion global declarada por los usuarios atendidos',
            'value'     => $surveys->isEmpty() ? null : round((float) $surveys->avg('score'), 2),
            'target'    => (float) config('servicedesk.targets.satisfaction'),
            'comparator' => 'gte',
            'scale'     => 5,
            'format'    => 'scale',
            'sample'    => $surveys->count(),
            'sample_label' => 'encuestas respondidas',
            'min_sample' => (int) config('servicedesk.min_sample.surveys'),
            'extra'     => [
                'satisfechos_pct' => $this->percentage($satisfied, $surveys->count()),
            ],
        ]);
    }

    /**
     * Completa la ficha del indicador con semaforo, brecha y etiqueta legible.
     */
    private function indicator(array $data): array
    {
        $value = $data['value'] ?? null;
        $target = (float) $data['target'];
        $comparator = $data['comparator'];
        $tolerance = (float) config('servicedesk.warning_tolerance');

        if ($value === null) {
            $status = 'sin_datos';
        } elseif ($comparator === 'gte') {
            $status = $value >= $target ? 'cumple' : ($value >= $target * (1 - $tolerance) ? 'riesgo' : 'incumple');
        } else {
            $status = $value <= $target ? 'cumple' : ($value <= $target * (1 + $tolerance) ? 'riesgo' : 'incumple');
        }

        $minSample = (int) ($data['min_sample'] ?? 0);
        $sample = (int) ($data['sample'] ?? 0);

        return array_merge($data, [
            'value'          => $value,
            'display'        => $this->format($value, $data['format'] ?? 'number'),
            'target_display' => $this->format($target, $data['format'] ?? 'number'),
            'status'         => $status,
            'gap'            => $value === null ? null : round($value - $target, 2),
            'reliable'       => $sample >= $minSample,
            'sample_notice'  => $sample >= $minSample
                ? null
                : "Muestra insuficiente: {$sample} de {$minSample} casos minimos para considerar el resultado concluyente.",
        ]);
    }

    // ── Validacion de la fuente de datos ──────────────────────────────────────

    /**
     * Controles de integridad sobre los Service Desk Logs del periodo. Cada control dice
     * que indicador compromete, para que la correccion sea accionable.
     *
     * @return array<int, array<string, mixed>>
     */
    public function validations(): array
    {
        $resolved = $this->tickets->filter(fn ($t) => $t->resolved_at !== null);
        $closed = $this->tickets->filter(fn ($t) => in_array($t->status, ServiceDeskTicket::CLOSED_STATUSES, true));

        $checks = [];

        $missingResponse = $resolved->filter(fn ($t) => $t->first_response_at === null);
        $checks[] = $this->check(
            'sin_primera_respuesta',
            'Tickets resueltos sin registro de primera respuesta',
            $missingResponse,
            'MTTRsp y Cumplimiento de SLA',
            'Registre la respuesta inicial al atender el ticket: sin ese hito el tiempo de respuesta no es calculable.',
            'error'
        );

        $inconsistent = $this->tickets->filter(function ($t) {
            return ($t->resolved_at && $t->resolved_at->lessThan($t->reported_at))
                || ($t->first_response_at && $t->first_response_at->lessThan($t->reported_at))
                || ($t->closed_at && $t->resolved_at && $t->closed_at->lessThan($t->resolved_at));
        });
        $checks[] = $this->check(
            'fechas_incoherentes',
            'Tickets con secuencia de fechas incoherente',
            $inconsistent,
            'MTTR y MTTRsp',
            'La secuencia valida es reporte -> respuesta -> resolucion -> cierre. Corrija los hitos invertidos.',
            'error'
        );

        $noCategory = $this->tickets->filter(fn ($t) => $t->category_id === null);
        $checks[] = $this->check(
            'sin_categoria',
            'Tickets sin categoria asignada',
            $noCategory,
            'Clasificacion correcta de tickets',
            'Toda incidencia debe quedar categorizada al registrarse; de lo contrario la auditoria no es verificable.',
            'warning'
        );

        $noSla = $this->tickets->filter(fn ($t) => $t->sla_id === null);
        $checks[] = $this->check(
            'sin_sla',
            'Tickets sin politica de SLA vinculada',
            $noSla,
            'Tasa de cumplimiento de SLA',
            'Verifique que exista una politica activa para cada prioridad en la configuracion del modulo.',
            'error'
        );

        $overdue = $this->tickets->filter(fn ($t) => $t->is_overdue);
        $checks[] = $this->check(
            'vencidos_abiertos',
            'Tickets abiertos con SLA de resolucion vencido',
            $overdue,
            'Tasa de cumplimiento de SLA',
            'Atienda o escale estos tickets: al cerrarse ya contaran como incumplimiento.',
            'warning'
        );

        $unaudited = $resolved->filter(fn ($t) => $t->audit === null);
        $auditCoverage = $this->percentage($resolved->count() - $unaudited->count(), $resolved->count());
        $checks[] = $this->check(
            'sin_auditar',
            'Tickets resueltos sin auditoria de clasificacion',
            $unaudited,
            'Clasificacion correcta de tickets',
            'Cobertura de auditoria: ' . ($auditCoverage === null ? 'sin datos' : $auditCoverage . '%') .
                '. Minimo sugerido: ' . config('servicedesk.min_coverage.audit') . '%.',
            $auditCoverage !== null && $auditCoverage < (float) config('servicedesk.min_coverage.audit') ? 'warning' : 'info'
        );

        $noSurvey = $closed->filter(fn ($t) => $t->survey === null);
        $surveyCoverage = $this->percentage($closed->count() - $noSurvey->count(), $closed->count());
        $checks[] = $this->check(
            'sin_encuesta',
            'Tickets atendidos sin encuesta de satisfaccion',
            $noSurvey,
            'Percepcion global de satisfaccion',
            'Cobertura de encuestas: ' . ($surveyCoverage === null ? 'sin datos' : $surveyCoverage . '%') .
                '. Minimo sugerido: ' . config('servicedesk.min_coverage.survey') . '%.',
            $surveyCoverage !== null && $surveyCoverage < (float) config('servicedesk.min_coverage.survey') ? 'warning' : 'info'
        );

        $mismatched = $this->tickets->filter(
            fn ($t) => (int) $t->priority !== ServiceDeskTicket::calculatePriority((int) $t->impact, (int) $t->urgency)
        );
        $checks[] = $this->check(
            'prioridad_fuera_de_matriz',
            'Tickets cuya prioridad no corresponde a la matriz impacto/urgencia',
            $mismatched,
            'Clasificacion correcta de tickets',
            'La prioridad debe derivarse de la matriz ITIL. Reclasifique el ticket para regenerarla.',
            'error'
        );

        $kedbWithoutArticle = $resolved->filter(fn ($t) => $t->resolved_with_kedb && $t->kedb_article_id === null);
        $checks[] = $this->check(
            'kedb_sin_articulo',
            'Tickets marcados como resueltos con KEDB pero sin articulo enlazado',
            $kedbWithoutArticle,
            'Uso de la base de conocimientos',
            'Enlace el articulo utilizado: sin trazabilidad el indicador no es auditable.',
            'error'
        );

        return $checks;
    }

    /**
     * @param  Collection<int, ServiceDeskTicket>  $tickets
     */
    private function check(string $key, string $label, Collection $tickets, string $affects, string $hint, string $severity): array
    {
        $count = $tickets->count();

        return [
            'key'      => $key,
            'label'    => $label,
            'count'    => $count,
            'affects'  => $affects,
            'hint'     => $hint,
            'severity' => $count === 0 ? 'ok' : $severity,
            'samples'  => $tickets->take(10)->map(fn ($t) => [
                'id'    => $t->id,
                'code'  => $t->code,
                'title' => $t->title,
            ])->values()->all(),
        ];
    }

    // ── Apoyo para el tablero ─────────────────────────────────────────────────

    public function summary(): array
    {
        $open = $this->tickets->filter(fn ($t) => ! in_array($t->status, ServiceDeskTicket::CLOSED_STATUSES, true));

        return [
            'total'     => $this->tickets->count(),
            'abiertos'  => $open->count(),
            'resueltos' => $this->tickets->filter(fn ($t) => $t->resolved_at !== null)->count(),
            'cerrados'  => $this->tickets->where('status', ServiceDeskTicket::STATUS_CERRADO)->count(),
            'vencidos'  => $this->tickets->filter(fn ($t) => $t->is_overdue)->count(),
            'reabiertos' => $this->tickets->filter(fn ($t) => $t->reopened_count > 0)->count(),
        ];
    }

    /**
     * Distribuciones para los graficos de apoyo: por prioridad, estado y categoria.
     */
    public function breakdown(): array
    {
        $byPriority = collect([1, 2, 3, 4])->map(fn ($p) => [
            'label' => ['1' => 'P1 Critica', '2' => 'P2 Alta', '3' => 'P3 Media', '4' => 'P4 Baja'][(string) $p],
            'total' => $this->tickets->where('priority', $p)->count(),
        ])->all();

        $byStatus = collect(ServiceDeskTicket::STATUSES)->map(fn ($s) => [
            'label' => $s,
            'total' => $this->tickets->where('status', $s)->count(),
        ])->all();

        $byCategory = $this->tickets
            ->groupBy(fn ($t) => $t->category?->name ?? 'Sin categoria')
            ->map(fn ($group, $name) => ['label' => $name, 'total' => $group->count()])
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'priority' => $byPriority,
            'status'   => $byStatus,
            'category' => $byCategory,
        ];
    }

    /**
     * Serie mensual de los indicadores de eficiencia, para ver la evolucion del periodo.
     */
    public function trend(): array
    {
        return $this->tickets
            ->groupBy(fn ($t) => $t->reported_at->format('Y-m'))
            ->sortKeys()
            ->map(function (Collection $group, string $month) {
                $resolutions = $group->map(fn ($t) => $t->resolution_minutes)->filter(fn ($m) => $m !== null && $m >= 0);
                $responses = $group->map(fn ($t) => $t->response_minutes)->filter(fn ($m) => $m !== null && $m >= 0);
                $measurable = $group->filter(fn ($t) => $t->sla_met !== null);

                return [
                    'month'         => $month,
                    'tickets'       => $group->count(),
                    'mttr'          => $resolutions->isEmpty() ? null : round((float) $resolutions->avg(), 2),
                    'mttr_response' => $responses->isEmpty() ? null : round((float) $responses->avg(), 2),
                    'sla'           => $this->percentage($measurable->filter(fn ($t) => $t->sla_met === true)->count(), $measurable->count()),
                ];
            })
            ->values()
            ->all();
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    private function percentage(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round($numerator * 100 / $denominator, 2);
    }

    private function format(?float $value, string $format): string
    {
        if ($value === null) {
            return 'Sin datos';
        }

        return match ($format) {
            'percent'  => number_format($value, 1) . '%',
            'scale'    => number_format($value, 2) . ' / 5',
            'minutes'  => number_format($value, 0) . ' min',
            'duration' => $this->humanDuration($value),
            default    => number_format($value, 2),
        };
    }

    /**
     * El MTTR se mide en minutos pero se lee en horas: la unidad de la matriz es
     * "Horas / Minutos", asi que se muestran ambas.
     */
    private function humanDuration(float $minutes): string
    {
        $hours = intdiv((int) round($minutes), 60);
        $rest = (int) round($minutes) % 60;

        return $hours > 0 ? "{$hours} h {$rest} min" : "{$rest} min";
    }
}
