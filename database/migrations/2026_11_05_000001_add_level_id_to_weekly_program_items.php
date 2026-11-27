<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_program_items', function (Blueprint $table) {
            // Nivel de receta (Master/Staff/Empleado/Obrero) con el que se programó el plato. Viene del
            // ciclo de menú, que costea cada celda con la receta de su nivel; sin él, los reportes y la
            // orden de compra usaban un único nivel elegido al generarlos. Nullable: las programaciones
            // anteriores no lo tienen y siguen usando el nivel que se elige en el reporte.
            $table->foreignId('level_id')->nullable()->after('dish_id')->constrained('levels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('weekly_program_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('level_id');
        });
    }
};
