<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Maestro de insumos importado del Excel de compras: código SAP/interno, descripción,
        // unidad de medida (KGM, NIU, ... nomenclatura SUNAT) y los tres importes de la planilla
        // (unidad / costo / total). Los importes van en decimal(14,4) porque el Excel los
        // maneja con 4 decimales.
        Schema::create('inputs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->string('unit_of_measure', 20)->nullable();
            $table->decimal('unit', 14, 4)->default(0);
            $table->decimal('cost', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inputs');
    }
};
