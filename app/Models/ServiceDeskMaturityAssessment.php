<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aplicacion del cuestionario de madurez ITIL 4 a una persona de TI en un periodo (YYYY-MM).
 * Cada aplicacion guarda una respuesta por item activo.
 */
class ServiceDeskMaturityAssessment extends Model
{
    use HasFactory;

    protected $table = 'service_desk_maturity_assessments';

    protected $fillable = [
        'user_id',
        'respondent_name',
        'respondent_role',
        'period',
        'answered_at',
        'comments',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ServiceDeskMaturityAnswer::class, 'assessment_id');
    }

    /** Promedio global del cuestionario (escala ordinal 1..5). */
    public function averageScore(): ?float
    {
        $avg = $this->answers()->avg('score');

        return $avg === null ? null : round((float) $avg, 2);
    }
}
