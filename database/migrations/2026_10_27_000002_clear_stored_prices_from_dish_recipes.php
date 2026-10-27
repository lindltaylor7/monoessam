<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El quebrado deja de guardar precios: un mismo quebrado se usa en unidades que se abastecen
 * desde ciudades distintas (Lima, Huancayo...), así que el costo se calcula siempre con el
 * proveedor activo de la ciudad que consulta (ver Ingredient_city_provider::activeFor).
 *
 * Los precios que quedaron grabados en las recetas tenían prioridad sobre el del proveedor y
 * "casaban" el quebrado con una ciudad. Se respaldan en dish_recipe_price_backups antes de
 * ponerlos en 0, por si hace falta compararlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dish_recipe_price_backups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dish_recipe_id');
            // null = fila del total de la receta (dish_recipes.total_cost).
            $table->unsignedBigInteger('ingredient_id')->nullable();
            $table->decimal('unit_price', 12, 4)->nullable();
            $table->decimal('cost', 12, 4)->nullable();
            $table->timestamp('backed_up_at')->nullable();

            $table->index('dish_recipe_id');
        });

        $now = now();

        DB::table('dish_recipe_ingredients')
            ->where(fn ($q) => $q->where('unit_price', '<>', 0)->orWhere('cost', '<>', 0))
            ->orderBy('id')
            ->select('id', 'dish_recipe_id', 'ingredient_id', 'unit_price', 'cost')
            ->chunk(1000, function ($rows) use ($now) {
                DB::table('dish_recipe_price_backups')->insert($rows->map(fn ($r) => [
                    'dish_recipe_id' => $r->dish_recipe_id,
                    'ingredient_id'  => $r->ingredient_id,
                    'unit_price'     => $r->unit_price,
                    'cost'           => $r->cost,
                    'backed_up_at'   => $now,
                ])->all());
            });

        DB::table('dish_recipes')
            ->where('total_cost', '<>', 0)
            ->orderBy('id')
            ->select('id', 'total_cost')
            ->chunk(1000, function ($rows) use ($now) {
                DB::table('dish_recipe_price_backups')->insert($rows->map(fn ($r) => [
                    'dish_recipe_id' => $r->id,
                    'ingredient_id'  => null,
                    'unit_price'     => null,
                    'cost'           => $r->total_cost,
                    'backed_up_at'   => $now,
                ])->all());
            });

        DB::table('dish_recipe_ingredients')
            ->where(fn ($q) => $q->where('unit_price', '<>', 0)->orWhere('cost', '<>', 0))
            ->update(['unit_price' => 0, 'cost' => 0]);

        DB::table('dish_recipes')->where('total_cost', '<>', 0)->update(['total_cost' => 0]);
    }

    public function down(): void
    {
        // Los precios respaldados no se restauran solos: volver a grabarlos reintroduciría el
        // conflicto entre ciudades. Quedan en la tabla hasta que se decida qué hacer con ellos.
        Schema::dropIfExists('dish_recipe_price_backups');
    }
};
