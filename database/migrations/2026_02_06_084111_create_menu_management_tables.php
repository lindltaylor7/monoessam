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
        // Template for meal structures (e.g., Almuerzo = Entrada + Fondo + ...)
        Schema::create('menu_structures', function (Blueprint $table) {
            $table->id();
            $table->string('meal_type'); // Desayuno, Almuerzo, Cena, Refrigerio
            $table->foreignId('dish_category_id')->constrained('dish_categories')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->decimal('cost_percentage', 5, 2)->default(0); // Optional: target cost %
            $table->timestamps();
        });

        // Weekly planning header
        Schema::create('weekly_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('borrador'); // borrador, aprobado
            $table->foreignId('user_id')->constrained('users'); // who planned it
            $table->timestamps();
        });

        // Dishes planned for each day/meal
        Schema::create('weekly_program_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_program_id')->constrained('weekly_programs')->cascadeOnDelete();
            $table->date('date');
            $table->string('meal_type');
            $table->foreignId('dish_category_id')->constrained('dish_categories');
            $table->foreignId('dish_id')->constrained('dishes');
            $table->timestamps();
        });

        // Portions/Rations count per day and meal type
        Schema::create('daily_portions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_program_id')->constrained('weekly_programs')->cascadeOnDelete();
            $table->date('date');
            $table->string('meal_type');
            $table->integer('portions_count');
            $table->timestamps();
        });

        // Purchase Order (Quebrado result)
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_program_id')->constrained('weekly_programs')->cascadeOnDelete();
            $table->string('status')->default('pendiente'); // pendiente, enviada
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Consolidated ingredients requested in the PO
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients');
            $table->decimal('total_amount', 14, 4);
            $table->string('unit'); // unit of purchase e.g. KG, LT
            $table->decimal('estimated_cost', 14, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('daily_portions');
        Schema::dropIfExists('weekly_program_items');
        Schema::dropIfExists('weekly_programs');
        Schema::dropIfExists('menu_structures');
    }
};
