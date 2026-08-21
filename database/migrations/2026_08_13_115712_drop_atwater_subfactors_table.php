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
        Schema::dropIfExists('atwater_subfactors');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('atwater_subfactors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atwater_factor_id')->nullable()->constrained('atwater_factors')->nullOnDelete();
            $table->string('name');
            $table->decimal('protein_kcal', 6, 2)->nullable();
            $table->decimal('protein_kj', 6, 2)->nullable();
            $table->decimal('fat_kcal', 6, 2)->nullable();
            $table->decimal('fat_kj', 6, 2)->nullable();
            $table->decimal('carb_kcal', 6, 2)->nullable();
            $table->decimal('carb_kj', 6, 2)->nullable();
            $table->timestamps();
        });
    }
};
