<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Acuerdo de nivel de servicio por prioridad. Los minutos de respuesta y resolucion son el
 * patron contra el que se mide la "Tasa de cumplimiento de SLA de atencion".
 */
class ServiceDeskSla extends Model
{
    use HasFactory;

    protected $table = 'service_desk_slas';

    protected $fillable = [
        'name',
        'priority',
        'response_minutes',
        'resolution_minutes',
        'description',
        'is_active',
    ];

    protected $casts = [
        'priority'           => 'integer',
        'response_minutes'   => 'integer',
        'resolution_minutes' => 'integer',
        'is_active'          => 'boolean',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(ServiceDeskTicket::class, 'sla_id');
    }
}
