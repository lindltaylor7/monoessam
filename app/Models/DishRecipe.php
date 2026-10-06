<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DishRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'dish_id',
        'name',
        'total_gross_weight',
        'total_waste_weight',
        'total_calories',
        'total_cost',
        'total_net_weight',
        'level_id',
    ];

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'dish_recipe_ingredients')
            ->using(DishRecipeIngredient::class)
            ->withPivot([
                'gross_weight',
                'solid_waste',
                'liquid_waste',
                'calories',
                'cost',
                'unit_price',
                'net_weight'
            ])
            ->withTimestamps();
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'level_id');
    }

    /**
     * Completa el precio/costo de cada insumo y el total_cost de la receta con el mismo
     * criterio que food/Quebrados.vue: si la línea no tiene precio guardado, se toma el del
     * proveedor activo (cost_price por Kg) y el costo es gramos / 1000 * precio.
     *
     * Hace falta porque el recetario migrado tiene total_cost, cost y unit_price en 0: el
     * editor de platos lo recalcula en el navegador, pero cycles mostraba el 0 guardado.
     *
     * Espera los ingredientes ya mapeados (gross_weight, cost, unit_price fuera del pivot)
     * y con `assignments` cargado.
     */
    public function applyLiveCosts(): void
    {
        $total = 0.0;

        foreach ($this->ingredients as $ingredient) {
            if (!(float) $ingredient->unit_price) {
                $active = $ingredient->assignments
                    ?->first(fn ($a) => $a->is_active && (float) $a->cost_price > 0);
                $price = $active ? (float) $active->cost_price : 0.0;

                $ingredient->unit_price = $price;
                $ingredient->cost = ((float) $ingredient->gross_weight / 1000) * $price;
            }

            $total += (float) $ingredient->cost;
        }

        $this->total_cost = $total;
    }
}
