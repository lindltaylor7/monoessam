<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoria de incidencia. Sostiene la dimension "Priorizacion y Categorizacion": sin un
 * catalogo cerrado no se puede medir si un ticket fue categorizado correctamente.
 */
class ServiceDeskCategory extends Model
{
    use HasFactory;

    protected $table = 'service_desk_categories';

    protected $fillable = [
        'code',
        'name',
        'description',
        'parent_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(ServiceDeskTicket::class, 'category_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(ServiceDeskKedbArticle::class, 'category_id');
    }
}
