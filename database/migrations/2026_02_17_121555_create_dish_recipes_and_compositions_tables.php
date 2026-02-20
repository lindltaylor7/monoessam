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
        Schema::create('dish_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_id')->constrained('dishes')->cascadeOnDelete();
            $table->string('name')->default('Receta Estándar');
            $table->timestamps();
        });

        Schema::create('dish_recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_recipe_id')->constrained('dish_recipes')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();

            // Calculation columns moved from satellite tables to pivot
            $table->decimal('gross_weight', 10, 2)->default(0);
            $table->decimal('solid_waste', 10, 2)->default(0);
            $table->decimal('liquid_waste', 10, 2)->default(0);
            $table->decimal('calories', 10, 2)->default(0);
            $table->decimal('cost', 10, 4)->default(0);
            $table->decimal('net_weight', 10, 2)->default(0); // final product

            $table->timestamps();
        });

        Schema::create('dish_recipe_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_recipe_id')->constrained('dish_recipes')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dish_recipe_levels');
        Schema::dropIfExists('dish_recipe_ingredients');
        Schema::dropIfExists('dish_recipes');
    }
};
