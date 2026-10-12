<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las líneas de una orden salen del quebrado (QuebradosService), pero el área de compras
 * necesita poder sumar insumos que no estaban en la programación semanal. Esta bandera
 * distingue esas líneas agregadas a mano de las calculadas, para que se vea en el detalle
 * de dónde viene cada cantidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('is_manual');
        });
    }
};
