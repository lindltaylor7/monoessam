<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Articulo de la KEDB (Known Error Database). `times_used` se incrementa cuando un ticket
 * se resuelve apoyandose en el articulo: es la trazabilidad del indicador "Uso de la base
 * de conocimientos".
 */
class ServiceDeskKedbArticle extends Model
{
    use HasFactory;

    protected $table = 'service_desk_kedb_articles';

    public const STATUS_BORRADOR = 'borrador';
    public const STATUS_PUBLICADO = 'publicado';
    public const STATUS_RETIRADO = 'retirado';

    public const STATUSES = [self::STATUS_BORRADOR, self::STATUS_PUBLICADO, self::STATUS_RETIRADO];

    protected $fillable = [
        'code',
        'title',
        'symptoms',
        'root_cause',
        'workaround',
        'solution',
        'category_id',
        'status',
        'times_used',
        'created_by',
    ];

    protected $casts = [
        'times_used' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceDeskCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(ServiceDeskTicket::class, 'kedb_article_id');
    }

    /**
     * Correlativo KE-000001. Igual que en los tickets, se deriva del maximo y no del conteo.
     */
    public static function nextCode(): string
    {
        $last = static::query()->max('code');
        $number = $last ? ((int) substr($last, 3)) + 1 : 1;

        return 'KE-' . str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }
}
