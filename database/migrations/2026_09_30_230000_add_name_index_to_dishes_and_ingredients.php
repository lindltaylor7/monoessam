<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indice por nombre en dishes e ingredients.
 *
 * POR QUE
 *
 * Todo el emparejamiento de la importacion del recetario es por nombre
 * (DishRecipesImport lo hace con firstOrCreate, y RecetarioImport resuelve los
 * insumos igual), pero ninguna de las dos columnas tenia indice: cada busqueda
 * era un scan completo de la tabla. Con 16,950 platos y 1,993 insumos, y 902
 * nombres de insumo distintos que resolver, eso son decenas de millones de
 * filas leidas por corrida para no encontrar nada nuevo.
 *
 * Medido antes de crearlo: la importacion se quedaba girando sin escribir.
 *
 * POR QUE INDICE Y NO UNIQUE
 *
 * ingredients ya trae nombres repetidos: 1,993 filas para 1,963 nombres
 * distintos al 30-09. Un unique no se puede crear sin antes decidir cual de
 * cada par duplicado sobrevive, y eso arrastra las FK de
 * ingredient_city_providers, nutritional_factors y dosifications. El indice
 * resuelve el costo de la busqueda, que es el problema real; la unicidad es
 * una decision aparte que no toca esta migracion.
 *
 * dishes.name admite NULL, asi que el indice tampoco puede ser unique ahi sin
 * decidir antes que hacer con esas filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->index('name', 'dishes_name_index');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->index('name', 'ingredients_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->dropIndex('dishes_name_index');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropIndex('ingredients_name_index');
        });
    }
};
