<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respuesta a un item del cuestionario de madurez, en escala ordinal 1..5.
 */
class ServiceDeskMaturityAnswer extends Model
{
    use HasFactory;

    protected $table = 'service_desk_maturity_answers';

    protected $fillable = [
        'assessment_id',
        'question_id',
        'score',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ServiceDeskMaturityAssessment::class, 'assessment_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ServiceDeskMaturityQuestion::class, 'question_id');
    }
}
