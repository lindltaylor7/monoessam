<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Incidencia del Service Desk. Es la unidad de analisis de la investigacion: de aqui salen
 * MTTR, MTTRsp, cumplimiento de SLA y uso de KEDB, y sobre ella se hace la auditoria de
 * clasificacion por impacto/urgencia.
 */
class ServiceDeskTicket extends Model
{
    use HasFactory;

    protected $table = 'service_desk_tickets';

    public const STATUS_NUEVO = 'nuevo';
    public const STATUS_ASIGNADO = 'asignado';
    public const STATUS_EN_PROCESO = 'en_proceso';
    public const STATUS_EN_ESPERA = 'en_espera';
    public const STATUS_RESUELTO = 'resuelto';
    public const STATUS_CERRADO = 'cerrado';

    public const STATUSES = [
        self::STATUS_NUEVO,
        self::STATUS_ASIGNADO,
        self::STATUS_EN_PROCESO,
        self::STATUS_EN_ESPERA,
        self::STATUS_RESUELTO,
        self::STATUS_CERRADO,
    ];

    /** Estados en los que el ticket ya no consume tiempo de atencion. */
    public const CLOSED_STATUSES = [self::STATUS_RESUELTO, self::STATUS_CERRADO];

    public const CHANNELS = ['portal', 'telefono', 'correo', 'presencial', 'whatsapp'];

    /**
     * Matriz de prioridad de ITIL: [impacto][urgencia] => prioridad.
     * Impacto 1=Alto 2=Medio 3=Bajo; Urgencia 1=Alta 2=Media 3=Baja.
     * Prioridad 1=Critica 2=Alta 3=Media 4=Baja.
     */
    public const PRIORITY_MATRIX = [
        1 => [1 => 1, 2 => 2, 3 => 3],
        2 => [1 => 2, 2 => 3, 3 => 4],
        3 => [1 => 3, 2 => 4, 3 => 4],
    ];

    protected $fillable = [
        'code',
        'title',
        'description',
        'requester_user_id',
        'requester_name',
        'requester_email',
        'requester_area',
        'unit_id',
        'channel',
        'category_id',
        'impact',
        'urgency',
        'priority',
        'sla_id',
        'status',
        'assigned_to',
        'reported_at',
        'response_due_at',
        'resolution_due_at',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'resolution_notes',
        'kedb_article_id',
        'resolved_with_kedb',
        'reopened_count',
    ];

    protected $casts = [
        'impact'             => 'integer',
        'urgency'            => 'integer',
        'priority'           => 'integer',
        'reopened_count'     => 'integer',
        'resolved_with_kedb' => 'boolean',
        'reported_at'        => 'datetime',
        'response_due_at'    => 'datetime',
        'resolution_due_at'  => 'datetime',
        'first_response_at'  => 'datetime',
        'resolved_at'        => 'datetime',
        'closed_at'          => 'datetime',
    ];

    protected $appends = [
        'priority_label',
        'status_label',
        'response_minutes',
        'resolution_minutes',
        'sla_response_met',
        'sla_resolution_met',
        'sla_met',
        'is_overdue',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceDeskCategory::class, 'category_id');
    }

    public function sla(): BelongsTo
    {
        return $this->belongsTo(ServiceDeskSla::class, 'sla_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function kedbArticle(): BelongsTo
    {
        return $this->belongsTo(ServiceDeskKedbArticle::class, 'kedb_article_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ServiceDeskTicketEvent::class, 'ticket_id')->orderBy('created_at');
    }

    public function audit(): HasOne
    {
        return $this->hasOne(ServiceDeskTicketAudit::class, 'ticket_id');
    }

    public function survey(): HasOne
    {
        return $this->hasOne(ServiceDeskSatisfactionSurvey::class, 'ticket_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Ventana de analisis: los indicadores se calculan siempre sobre la fecha de reporte,
     * no sobre created_at, para que una carga historica no distorsione el periodo.
     */
    public function scopeReportedBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('reported_at', [
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay(),
        ]);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::CLOSED_STATUSES);
    }

    // ── Logica de dominio ─────────────────────────────────────────────────────

    /**
     * Prioridad segun la matriz ITIL. Valores fuera de 1..3 caen a la celda menos severa,
     * que es el comportamiento esperado cuando el dato llega incompleto desde un import.
     */
    public static function calculatePriority(int $impact, int $urgency): int
    {
        return self::PRIORITY_MATRIX[$impact][$urgency] ?? 4;
    }

    /**
     * Correlativo visible del ticket. Se calcula a partir del maximo existente y no del
     * conteo, para que borrar un ticket no genere codigos repetidos.
     */
    public static function nextCode(): string
    {
        $last = static::query()->max('code');
        $number = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'TCK-' . str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Fija la prioridad y congela los vencimientos de SLA del ticket. Se llama al crear y
     * cada vez que cambia impacto/urgencia, porque un reclasificado cambia el compromiso.
     */
    public function applyPriorityAndSla(): void
    {
        $this->priority = self::calculatePriority((int) $this->impact, (int) $this->urgency);

        $sla = ServiceDeskSla::where('priority', $this->priority)->where('is_active', true)->first();

        if (! $sla) {
            $this->sla_id = null;
            $this->response_due_at = null;
            $this->resolution_due_at = null;

            return;
        }

        $reported = $this->reported_at instanceof Carbon ? $this->reported_at : Carbon::parse($this->reported_at);

        $this->sla_id = $sla->id;
        $this->response_due_at = $reported->copy()->addMinutes($sla->response_minutes);
        $this->resolution_due_at = $reported->copy()->addMinutes($sla->resolution_minutes);
    }

    // ── Accesores para el tablero de indicadores ──────────────────────────────

    /** Minutos hasta la primera respuesta (insumo del MTTRsp). */
    public function getResponseMinutesAttribute(): ?float
    {
        if (! $this->reported_at || ! $this->first_response_at) {
            return null;
        }

        return round($this->reported_at->diffInSeconds($this->first_response_at) / 60, 2);
    }

    /** Minutos hasta la resolucion (insumo del MTTR). */
    public function getResolutionMinutesAttribute(): ?float
    {
        if (! $this->reported_at || ! $this->resolved_at) {
            return null;
        }

        return round($this->reported_at->diffInSeconds($this->resolved_at) / 60, 2);
    }

    public function getSlaResponseMetAttribute(): ?bool
    {
        if (! $this->response_due_at || ! $this->first_response_at) {
            return null;
        }

        return $this->first_response_at->lessThanOrEqualTo($this->response_due_at);
    }

    public function getSlaResolutionMetAttribute(): ?bool
    {
        if (! $this->resolution_due_at || ! $this->resolved_at) {
            return null;
        }

        return $this->resolved_at->lessThanOrEqualTo($this->resolution_due_at);
    }

    /**
     * Cumplimiento integral: el ticket cuenta como cumplido solo si respeto el tiempo de
     * respuesta y el de resolucion. Null mientras falte alguno de los dos hitos.
     */
    public function getSlaMetAttribute(): ?bool
    {
        $response = $this->sla_response_met;
        $resolution = $this->sla_resolution_met;

        if ($response === null || $resolution === null) {
            return null;
        }

        return $response && $resolution;
    }

    /** Ticket abierto con el compromiso de resolucion ya vencido. */
    public function getIsOverdueAttribute(): bool
    {
        if (in_array($this->status, self::CLOSED_STATUSES, true) || ! $this->resolution_due_at) {
            return false;
        }

        return now()->greaterThan($this->resolution_due_at);
    }

    public function getPriorityLabelAttribute(): string
    {
        return ['1' => 'Critica', '2' => 'Alta', '3' => 'Media', '4' => 'Baja'][(string) $this->priority] ?? 'Baja';
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            self::STATUS_NUEVO      => 'Nuevo',
            self::STATUS_ASIGNADO   => 'Asignado',
            self::STATUS_EN_PROCESO => 'En proceso',
            self::STATUS_EN_ESPERA  => 'En espera',
            self::STATUS_RESUELTO   => 'Resuelto',
            self::STATUS_CERRADO    => 'Cerrado',
        ][$this->status] ?? $this->status;
    }
}
