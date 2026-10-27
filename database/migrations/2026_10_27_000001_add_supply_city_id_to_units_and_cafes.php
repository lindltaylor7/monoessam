<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ciudad desde la que se abastece de insumos una Unidad (y, si hace falta, un Comedor puntual).
 * Los precios existen por (insumo, ciudad, proveedor) en ingredient_city_providers; con esto los
 * Ciclos de Menú, la Programación y la Orden de Compra saben qué precios usar sin preguntar.
 * El Comedor hereda la ciudad de su Unidad cuando no tiene una propia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->foreignId('supply_city_id')->nullable()->after('mine_id')->constrained('cities')->nullOnDelete();
        });

        Schema::table('cafes', function (Blueprint $table) {
            $table->foreignId('supply_city_id')->nullable()->after('unit_id')->constrained('cities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cafes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supply_city_id');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supply_city_id');
        });
    }
};
