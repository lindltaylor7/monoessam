<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ProductBatch extends Model
{
    use HasFactory;

    /** Días de anticipación para marcar un lote como "por vencer". */
    public const EXPIRING_SOON_DAYS = 7;

    protected $fillable = [
        'product_id',
        'batch_code',
        'quantity',
        'expiration_date',
        'received_at',
        'notes',
    ];

    // Formato explícito Y-m-d: sin esto, Carbon serializa un cast `date` a ISO-8601 completo con
    // hora y "Z" (aplicando la zona horaria de la app), lo que corre la fecha un día para el
    // frontend (formatDate() espera "YYYY-MM-DD" y solo separa por "-").
    protected $casts = [
        'quantity'        => 'integer',
        'expiration_date' => 'date:Y-m-d',
        'received_at'     => 'date:Y-m-d',
    ];

    protected $appends = ['expiration_status'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 'expired' | 'expiring_soon' | 'ok' | null (sin fecha de vencimiento registrada).
     */
    public function getExpirationStatusAttribute(): ?string
    {
        if (!$this->expiration_date) {
            return null;
        }

        $today = Carbon::today();
        if ($this->expiration_date->lt($today)) {
            return 'expired';
        }

        if ($this->expiration_date->lte($today->copy()->addDays(self::EXPIRING_SOON_DAYS))) {
            return 'expiring_soon';
        }

        return 'ok';
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiration_date')->whereDate('expiration_date', '<', Carbon::today());
    }

    public function scopeExpiringSoon(Builder $query): Builder
    {
        return $query->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '>=', Carbon::today())
            ->whereDate('expiration_date', '<=', Carbon::today()->addDays(self::EXPIRING_SOON_DAYS));
    }
}
