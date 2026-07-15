<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock real de equipos por café/unidad — reemplaza el cálculo de "disponible"
     * derivado del historial de guías (EquipmentDispatch). Cada guía sigue siendo un
     * documento fijo e independiente; esta tabla es la única fuente de verdad de
     * cuánto hay AHORA en cada café/unidad.
     */
    public function up(): void
    {
        Schema::create('equipment_stocks', function (Blueprint $table) {
            $table->id();
            $table->morphs('stockable'); // ComputerEquipment|KitchenEquipment
            $table->foreignId('cafe_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('unit_id')->nullable()->constrained()->onDelete('cascade');
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['stockable_type', 'stockable_id', 'cafe_id', 'unit_id'], 'equipment_stocks_location_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_stocks');
    }
};
