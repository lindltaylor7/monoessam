<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unico en dish_recipe_ingredients (dish_recipe_id, ingredient_id).
 *
 * El par ya era unico en los hechos: la relacion se maneja con sync() /
 * syncWithoutDetaching(), que indexan el pivote por ingredient_id y nunca
 * insertan el mismo insumo dos veces en una receta. Lo que faltaba era que la
 * base lo supiera.
 *
 * Hace falta ahora porque la importacion del recetario paso a leer por bloques
 * (169,737 filas no entran en memoria de una sola pasada) y escribe el pivote
 * con upsert, que necesita un indice unico para saber que fila actualizar. Sin
 * el, cada bloque insertaria duplicados en vez de actualizar.
 *
 * Verificado antes de crearlo: 0 pares repetidos en la tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dish_recipe_ingredients', function (Blueprint $table) {
            $table->unique(['dish_recipe_id', 'ingredient_id'], 'dri_recipe_ingredient_unique');
        });
    }

    public function down(): void
    {
        Schema::table('dish_recipe_ingredients', function (Blueprint $table) {
            $table->dropUnique('dri_recipe_ingredient_unique');
        });
    }
};
