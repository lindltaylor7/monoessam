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
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['calories', 'liquid_waste', 'solid_waste', 'measurement_unit']);
            $table->double('waste', 10, 2)->nullable()->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->decimal('calories', 7, 2)->nullable();
            $table->decimal('liquid_waste', 7, 4)->nullable();
            $table->decimal('solid_waste', 7, 4)->nullable();
            $table->string('measurement_unit')->nullable();
            $table->dropColumn('waste');
        });
    }
};
