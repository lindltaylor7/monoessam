<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrada de la bitacora del ticket. Es el "Service Desk Log" que permite auditar como se
 * movio la incidencia: quien la tomo, cuando se respondio y que cambios de estado hubo.
 */
class ServiceDeskTicketEvent extends Model
{
    use HasFactory;

    protected $table = 'service_desk_ticket_events';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'type',
        'from_status',
        'to_status',
        'note',
    ];

    /**
     * Atajo para dejar traza desde los controladores. El usuario se toma de la sesion salvo
     * que se indique otro (p. ej. una carga automatica).
     */
    public static function log(
        ServiceDeskTicket $ticket,
        string $type,
        ?string $note = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?int $userId = null,
    ): self {
        return static::create([
            'ticket_id'   => $ticket->id,
            'user_id'     => $userId ?? auth()->id(),
            'type'        => $type,
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'note'        => $note,
        ]);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ServiceDeskTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
