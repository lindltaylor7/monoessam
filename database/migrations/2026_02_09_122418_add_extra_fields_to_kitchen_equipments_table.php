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
        Schema::table('kitchen_equipments', function (Blueprint $table) {
            $table->string('color')->nullable();
            $table->string('current_type')->nullable();
            $table->string('series')->nullable();
            $table->text('manual')->nullable();
            $table->string('code')->nullable();
            $table->tinyInteger('status')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_equipments', function (Blueprint $table) {
            $table->dropColumn(['color', 'current_type', 'series', 'manual', 'code', 'status']);
        });
    }
};
