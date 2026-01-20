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
        Schema::create('mine_subdealerships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mine_id');
            $table->foreign('mine_id')->references('id')->on('mines')->cascadeOnDelete();
            $table->unsignedBigInteger('subdealership_id');
            $table->foreign('subdealership_id')->references('id')->on('subdealerships')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mine_subdealerships');
    }
};
