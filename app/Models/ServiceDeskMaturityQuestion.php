<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item del cuestionario de adopcion de practicas ITIL 4 aplicado al personal de TI.
 * La dimension agrupa los items; el indicador de estandarizacion promedia los de
 * `estandarizacion`, y el tablero muestra ademas el promedio de las otras dos.
 */
class ServiceDeskMaturityQuestion extends Model
{
    use HasFactory;

    protected $table = 'service_desk_maturity_questions';

    public const DIMENSIONS = ['estandarizacion', 'conocimiento', 'priorizacion'];

    protected $fillable = [
        'code',
        'dimension',
        'statement',
        'order',
        'is_active',
    ];

    protected $casts = [
        'order'     => 'integer',
        'is_active' => 'boolean',
    ];

    public function answers(): HasMany
    {
        return $this->hasMany(ServiceDeskMaturityAnswer::class, 'question_id');
    }
}
