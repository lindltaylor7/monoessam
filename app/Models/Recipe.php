<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recipe extends Model
{
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
                    if ($quantity === null || $quantity == $stored || round($quantity, 2) != $stored) {
                        continue;
                    }
                    if ((float) $ingredient->solid_waste == 0 && (float) $ingredient->final_product == $stored) {
                        $ingredient->final_product = $quantity;
                    }
                    $ingredient->gross_weight = $quantity;
                }
            }
        }
    }
}
