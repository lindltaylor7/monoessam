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
        Schema::table('dish_recipes', function (Blueprint $table) {
            $table->decimal('total_gross_weight', 10, 2)->default(0)->after('name');
            $table->decimal('total_waste_weight', 10, 2)->default(0)->after('total_gross_weight');
            $table->decimal('total_calories', 10, 2)->default(0)->after('total_waste_weight');
            $table->decimal('total_cost', 10, 4)->default(0)->after('total_calories');
            $table->decimal('total_net_weight', 10, 2)->default(0)->after('total_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dish_recipes', function (Blueprint $table) {
            $table->dropColumn([
                'total_gross_weight',
                'total_waste_weight',
                'total_calories',
                'total_cost',
                'total_net_weight',
            ]);
        });
    }
};
