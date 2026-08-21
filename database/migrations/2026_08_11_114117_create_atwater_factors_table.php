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
        Schema::create('atwater_factors', function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->unsignedSmallInteger('order')->default(0);
            $table->decimal('protein_kcal', 6, 2)->nullable();
            $table->decimal('protein_kj', 6, 2)->nullable();
            $table->decimal('fat_kcal', 6, 2)->nullable();
            $table->decimal('fat_kj', 6, 2)->nullable();
            $table->decimal('carb_kcal', 6, 2)->nullable();
            $table->decimal('carb_kj', 6, 2)->nullable();
            $table->string('footnote')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atwater_factors');
    }
};
