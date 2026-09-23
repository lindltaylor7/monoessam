<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recipe extends Model
{
    /** Las cantidades históricas de `recipes.quantity` están expresadas en kilos. */
    private const GRAMS_PER_KILO = 1000;

    protected $fillable = [
        'dish_id',
        'ingredient_id',
        'quantity',
        'unit_id',
        'yield_factor',
        'notes'
    ];

    public function dish()
    {
        return $this->belongsTo(Dish::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit()
    {
        return $this->belongsTo(Measurement_unit::class, 'unit_id');
    }

    /**
     * dish_recipe_ingredients.gross_weight es decimal(10,2), pero las cantidades se
     * migraron desde esta tabla (quantity decimal(8,4)) y quedaron redondeadas
     * (0.0140 → 0.01, 0.0007 → 0.00). Para mostrarlas con su precisión real, se toma
     * `quantity` de aquí cuando el valor guardado es justo ese redondeo; si el usuario
     * editó la cantidad desde la UI (ya no coincide), se respeta lo guardado.
     *
     * Además, esas cantidades migradas están en **kilos**, mientras que la vista de
     * quebrados trabaja internamente en gramos (materia prima, desecho, producto final
     * y calorías). Por eso los pesos de las filas migradas se convierten a gramos y se
     * expone `source_quantity` / `source_unit` para que el front siga mostrando en el
     * input el mismo número que está en la BD, pero con la unidad correcta (Kg).
     * Espera que los ingredientes ya tengan gross_weight/final_product asignados.
     */
    public static function applyPreciseQuantities(iterable $dishes): void
    {
        $dishes = collect($dishes);
        if ($dishes->isEmpty()) {
            return;
        }

        $quantities = static::whereIn('dish_id', $dishes->pluck('id'))
            ->get(['dish_id', 'ingredient_id', 'quantity'])
            ->mapWithKeys(fn ($r) => [$r->dish_id . '-' . $r->ingredient_id => (float) $r->quantity]);

        foreach ($dishes as $dish) {
            foreach ($dish->recipes as $recipe) {
                foreach ($recipe->ingredients as $ingredient) {
                    $quantity = $quantities[$dish->id . '-' . $ingredient->id] ?? null;
                    $stored = (float) $ingredient->gross_weight;
                    // Si el peso guardado ya no es el redondeo de la cantidad migrada, la fila
                    // fue editada desde la UI y por tanto ya está en gramos: no se toca.
                    if ($quantity === null || round($quantity, 2) != $stored) {
                        continue;
                    }

                    $waste = (float) $ingredient->solid_waste;
                    $net = (float) $ingredient->final_product;

                    $ingredient->gross_weight = $quantity * self::GRAMS_PER_KILO;
                    $ingredient->solid_waste = $waste * self::GRAMS_PER_KILO;
                    $ingredient->liquid_waste = (float) $ingredient->liquid_waste * self::GRAMS_PER_KILO;
                    // El neto sufrió el mismo redondeo que el bruto; cuando coinciden se
                    // reconstruye desde la cantidad precisa.
                    $ingredient->final_product = ($waste == 0.0 && $net == $stored ? $quantity : $net) * self::GRAMS_PER_KILO;
                    $ingredient->source_quantity = $quantity;
                    $ingredient->source_unit = 'Kg';
                }
            }
        }
    }
}
