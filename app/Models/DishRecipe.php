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
     * Completa costo y calorías de cada insumo y los totales de la receta con el mismo criterio
     * que food/Quebrados.vue, que los recalcula en el navegador:
     *
     *  - Costo: si la línea no tiene precio guardado, se toma el del proveedor activo (cost_price
     *    por Kg) y el costo es gramos / 1000 * precio.
     *  - Calorías: kcal por 100 g del insumo (ver caloriesPer100g) aplicadas al producto final,
     *    porque la merma no aporta calorías.
     *
     * Hace falta porque el recetario migrado tiene total_cost, total_calories, cost y calories
     * en 0: el editor de platos los recalcula, pero cycles mostraba el 0 guardado.
     *
     * Espera los ingredientes ya mapeados (gross_weight, final_product, cost, unit_price fuera
     * del pivot) y con assignments, dosification, atwaterFactor y nutritionalFactors cargados.
     */
    public function applyLiveTotals(): void
    {
        $totalCost = 0.0;
        $totalCalories = 0.0;

        foreach ($this->ingredients as $ingredient) {
            if (!(float) $ingredient->unit_price) {
                $active = $ingredient->assignments
                    ?->first(fn ($a) => $a->is_active && (float) $a->cost_price > 0);
                $price = $active ? (float) $active->cost_price : 0.0;

                $ingredient->unit_price = $price;
                $ingredient->cost = ((float) $ingredient->gross_weight / 1000) * $price;
            }

            $ingredient->calories = ((float) $ingredient->final_product * self::caloriesPer100g($ingredient)) / 100;

            $totalCost += (float) $ingredient->cost;
            $totalCalories += $ingredient->calories;
        }

        $this->total_cost = $totalCost;
        $this->total_calories = $totalCalories;
    }

    /**
     * kcal por 100 g del insumo, igual que calculateIngredientCalories() de Quebrados.vue:
     * fórmula Atwater con el factor asignado; si no lo tiene (o da 0 por falta de macros), la
     * suma de factores nutricionales; y si tampoco, la energía de la tabla de composición.
     */
    public static function caloriesPer100g(Ingredient $ingredient): float
    {
        $dosification = $ingredient->dosification;
        $atwater = $ingredient->atwaterFactor;

        if ($dosification && $atwater) {
            // Los carbohidratos disponibles (total menos fibra) son la base correcta; si el
            // insumo aún no los tiene cargados se usa el total.
            $carbohydrate = $dosification->carbohydrate_available !== null
                ? $dosification->carbohydrate_available
                : $dosification->carbohydrate;

            $kcal = self::toGramsPer100g($dosification->protein) * (float) $atwater->protein_kcal
                + self::toGramsPer100g($dosification->lipid) * (float) $atwater->fat_kcal
                + self::toGramsPer100g($carbohydrate) * (float) $atwater->carb_kcal;

            if ($kcal > 0) {
                return $kcal;
            }
        }

        if ($ingredient->nutritionalFactors?->isNotEmpty()) {
            return $ingredient->nutritionalFactors->sum(
                fn ($f) => (float) $f->nfactorcal * (float) $f->composition
            );
        }

        return (float) ($dosification?->energy ?: $ingredient->energy ?: 0);
    }

    /**
     * Un nutriente en g/100 g nunca supera 100: lo importado por Excel quedó en mg/kg
     * (~10000x) y lo cargado a mano ya está en g/100 g, así que se detecta por valor.
     */
    private static function toGramsPer100g($value): float
    {
        $value = (float) $value;

        return $value > 100 ? $value / 10000 : $value;
    }
}
