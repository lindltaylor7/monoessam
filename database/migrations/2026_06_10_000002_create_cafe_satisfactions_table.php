<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cafe_satisfactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->cascadeOnDelete();
            $table->unsignedTinyInteger('score'); // 1 (muy insatisfecho) .. 5 (muy satisfecho)
            $table->date('date');
            $table->string('service')->nullable(); // Desayuno, Almuerzo, Cena, Refrigerio
            $table->timestamps();

            $table->index(['cafe_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cafe_satisfactions');
    }
};
