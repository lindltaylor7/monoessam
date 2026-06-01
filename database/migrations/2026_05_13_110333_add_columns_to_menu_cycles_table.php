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
        Schema::table('menu_cycles', function (Blueprint $table) {
            $table->unsignedBigInteger('serviceable_id')->nullable();
            $table->integer('days')->default(7);
            $table->json('cycle_data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_cycles', function (Blueprint $table) {
            $table->dropColumn(['serviceable_id', 'days', 'cycle_data']);
        });
    }
};
