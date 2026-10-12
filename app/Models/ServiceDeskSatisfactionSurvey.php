<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Encuesta Likert (1..5) al usuario atendido. `score` es la percepcion global que mide el
 * indicador de Calidad Percibida del Servicio; las cuatro preguntas de detalle sirven para
 * explicar el resultado, no entran en el indicador principal.
 */
class ServiceDeskSatisfactionSurvey extends Model
{
    use HasFactory;

    protected $table = 'service_desk_satisfaction_surveys';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'respondent_name',
        'respondent_area',
        'score',
        'q_attention',
        'q_response_time',
        'q_solution',
        'q_communication',
        'comments',
        'answered_at',
    ];

    protected $casts = [
        'score'           => 'integer',
        'q_attention'     => 'integer',
        'q_response_time' => 'integer',
        'q_solution'      => 'integer',
        'q_communication' => 'integer',
        'answered_at'     => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ServiceDeskTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
