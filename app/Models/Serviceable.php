<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Serviceable extends Model
{
    use HasFactory;
    protected $fillable = ['serviceable_id','serviceable_type','service_id','price'];
    public function dish_categories(): BelongsToMany
    {
        return $this->belongsToMany(Dish_category::class,'dish_category_serviceables','serviceable_id','dish_category_id')
            ->withPivot('serving_amount', 'measurement_unit_id', 'serving_percentaje', 'lower_limit_cost', 'total_lower_limit_cost', 'upper_limit_cost', 'total_upper_limit_cost')
            ->withTimestamps();
    }

    public function serviceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Ciudad de abastecimiento del lugar al que pertenece el servicio: Comedor (o su Unidad) o
     * Unidad. Un servicio colgado de una Mina no tiene ciudad propia y devuelve null.
     */
    public function supplyCityId(): ?int
    {
        $place = $this->serviceable;

        return match (true) {
            $place instanceof Cafe => $place->supplyCityId(),
            $place instanceof Unit => $place->supply_city_id,
            default => null,
        };
    }
}
