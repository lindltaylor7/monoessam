<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_program_items', function (Blueprint $table) {
            // Porcentaje de comensales que toma este plato: las raciones efectivas del plato
            // son portions_count (raciones del servicio ese día) * percentage / 100.
            $table->decimal('percentage', 5, 2)->default(100)->after('dish_id');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_program_items', function (Blueprint $table) {
            $table->dropColumn('percentage');
        });
    }
};
