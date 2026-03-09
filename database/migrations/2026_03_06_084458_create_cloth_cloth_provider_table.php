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
        Schema::create('cloth_cloth_provider', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cloth_id')->constrained('cloths')->onDelete('cascade');
            $table->foreignId('cloth_provider_id')->constrained('cloth_providers')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cloth_cloth_provider');
    }
};
