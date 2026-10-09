<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pasa los pesos del recetario de decimal(10,2) a decimal(12,5).
 *
 * POR QUE, CON NUMEROS
 *
 * El recetario de Tiburon trae la cantidad por racion en unidad base, y a esa
 * escala dos decimales no alcanzan. Medido sobre el export real del 30-09
 * (169,752 lineas):
 *
 *     74,525 lineas (43.9%)  con cantidad < 0.005
 *     93,589 lineas (55.1%)  entre 0.005 y 1
 *      1,638 lineas ( 1.0%)  >= 1
 *     minimo 0.00001   maximo 960.00000
 *
 * Con decimal(10,2) esas 74,525 lineas se guardaban como 0.00. Casi la mitad
 * del recetario entraba en cero sin un solo error: la receta se abre, lista
 * sus insumos, y las cantidades dicen 0.00. Es el mismo fallo silencioso que
 * el resumen de DishController::import existe para detectar, solo que una capa
 * mas abajo, donde el conteo de "lineas con cantidad" no lo ve porque el valor
 * SI venia con cantidad en el archivo.
 *
 * COMO SE ELIGIO LA PRECISION
 *
 * 5 decimales porque es lo que trae el origen: 168,116 de las 169,752 lineas
 * vienen con 5 decimales y las 1,636 restantes con 3. Ni una necesita mas.
 *
 * 12 digitos totales porque el maximo por linea es 960 y el maximo acumulado
 * de un plato es 4,891.95510 (plato 12077, el de mas lineas, con 50). Con
 * decimal(12,5) el techo queda en 9,999,999.99999, tres ordenes de magnitud
 * sobre el peor caso real.
 *
 * ALCANCE
 *
 * Solo las cuatro columnas de peso que escribe la importacion. No se tocan
 * cost ni unit_price (decimal(10,4)) ni calories ni las mermas: la
 * importacion las deja en cero porque el export de Tiburon no trae ese dato,
 * asi que su precision no es parte de este problema.
 *
 * Ensanchar es compatible hacia atras: todo valor que cabia en decimal(10,2)
 * cabe en decimal(12,5), asi que no hay conversion ni perdida al aplicarla.
 * El down() si puede perder decimales, y por eso solo deberia usarse sobre
 * datos que no vengan de esta importacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dish_recipe_ingredients', function (Blueprint $table) {
            $table->decimal('gross_weight', 12, 5)->default(0)->change();
            $table->decimal('net_weight', 12, 5)->default(0)->change();
        });

        Schema::table('dish_recipes', function (Blueprint $table) {
            $table->decimal('total_gross_weight', 12, 5)->default(0)->change();
            $table->decimal('total_net_weight', 12, 5)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('dish_recipe_ingredients', function (Blueprint $table) {
            $table->decimal('gross_weight', 10, 2)->default(0)->change();
            $table->decimal('net_weight', 10, 2)->default(0)->change();
        });

        Schema::table('dish_recipes', function (Blueprint $table) {
            $table->decimal('total_gross_weight', 10, 2)->default(0)->change();
            $table->decimal('total_net_weight', 10, 2)->default(0)->change();
        });
    }
};
