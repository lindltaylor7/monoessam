<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class City extends Model
{
    use HasFactory;
    
    protected $fillable = ['name'];

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'ingredient_city_providers', 'city_id', 'provider_id');
    }
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_city_providers', 'city_id', 'ingredient_id');
    }

    /**
     * Ciudad de precios cuando el lugar no tiene ciudad de abastecimiento configurada: la que más
     * proveedores activos tiene (el mismo criterio que usaba la Orden de Compra para adivinarla).
     */
    public static function defaultSupplyCityId(): ?int
    {
        return once(fn () => Ingredient_city_provider::where('is_active', true)
            ->selectRaw('city_id, count(*) as total')
            ->groupBy('city_id')
            ->orderByDesc('total')
            ->orderBy('city_id')
            ->value('city_id'));
    }

    /** Ciudades con al menos un precio cargado en Proveedores: las únicas que tiene sentido elegir. */
    public static function withPrices()
    {
        return static::whereIn('id', Ingredient_city_provider::select('city_id'))->orderBy('name')->get(['id', 'name']);
    }
}
