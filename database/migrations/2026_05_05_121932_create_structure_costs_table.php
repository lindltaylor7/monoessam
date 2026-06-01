<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('structure_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->onDelete('cascade');
            $table->foreignId('dish_category_id')->nullable();
            $table->string('name')->nullable(); // En caso se guarde sin relacion directa
            $table->integer('order');
            $table->decimal('reference_volume', 10, 2)->nullable();
            $table->string('measurement_unit')->nullable();
            $table->decimal('ration', 10, 2)->nullable();
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('structure_costs');
    }
};
