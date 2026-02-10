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
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_id')->constrained('dishes')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('quantity', 8, 4); // Exact quantity
            $table->foreignId('unit_id')->constrained('measurement_units');
            $table->decimal('yield_factor', 5, 2)->default(1.00); // For waste calculation (mermas)
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['dish_id', 'ingredient_id']); // Prevent duplicate ingredient in same dish
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
