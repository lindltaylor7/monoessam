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
        Schema::table('structures', function (Blueprint $table) {
            $table->decimal('selling_price', 10, 2)->nullable()->after('serviceable_id');
        });

        Schema::table('structure_costs', function (Blueprint $table) {
            $table->decimal('unit_cost_superior', 10, 2)->nullable()->after('unit_cost');
            $table->decimal('total_cost_superior', 10, 2)->nullable()->after('total_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('structures', function (Blueprint $table) {
            $table->dropColumn('selling_price');
        });

        Schema::table('structure_costs', function (Blueprint $table) {
            $table->dropColumn(['unit_cost_superior', 'total_cost_superior']);
        });
    }
};
