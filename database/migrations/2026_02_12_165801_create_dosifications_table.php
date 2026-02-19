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
        Schema::create('dosifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ingredient_id');
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->cascadeOnDelete();
            $table->decimal('energy',8,2)->nullable();
            $table->decimal('water',8,2)->nullable();
            $table->decimal('protein',8,2)->nullable();
            $table->decimal('lipid',8,2)->nullable();
            $table->decimal('carbohydrate',8,2)->nullable();
            $table->decimal('fiber',8,2)->nullable();
            $table->decimal('ash',8,2)->nullable();
            $table->decimal('calcium',8,2)->nullable();
            $table->decimal('phosphorus',8,2)->nullable();
            $table->decimal('iron',8,2)->nullable();
            $table->decimal('retinol',8,2)->nullable();
            $table->decimal('thiamine',8,2)->nullable();
            $table->decimal('riboflavin',8,2)->nullable();
            $table->decimal('niacin',8,2)->nullable();
            $table->decimal('a_asc',8,2)->nullable();
            $table->decimal('sodium',8,2)->nullable();
            $table->decimal('potassium',8,2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dosifications');
    }
};
