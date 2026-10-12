<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auditoria de clasificacion de un ticket. Un auditor revisa si la categoria, el impacto,
 * la urgencia y la prioridad resultante fueron correctos; `is_correct` (AND de los cuatro)
 * es el numerador del indicador "% de tickets categorizados correctamente".
 */
class ServiceDeskTicketAudit extends Model
{
    use HasFactory;

    protected $table = 'service_desk_ticket_audits';

    protected $fillable = [
        'ticket_id',
        'auditor_id',
        'category_correct',
        'impact_correct',
        'urgency_correct',
        'priority_correct',
        'is_correct',
        'suggested_category_id',
        'suggested_impact',
        'suggested_urgency',
        'findings',
        'audited_at',
    ];

    protected $casts = [
        'category_correct' => 'boolean',
        'impact_correct'   => 'boolean',
        'urgency_correct'  => 'boolean',
        'priority_correct' => 'boolean',
        'is_correct'       => 'boolean',
        'audited_at'       => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ServiceDeskTicket::class, 'ticket_id');
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function suggestedCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceDeskCategory::class, 'suggested_category_id');
    }
}
