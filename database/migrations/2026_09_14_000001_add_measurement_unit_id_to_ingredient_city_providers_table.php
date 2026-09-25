<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "UM" column on the Proveedores screen and the measurement_unit() relation on
 * Ingredient_city_provider have never had a backing column — the real ingredient_city_providers
 * table (see 2026_09_11_114519_create_ingredient_city_providers_table_if_missing.php) only has
 * ingredient_id/provider_id/city_id/cost_price. This adds it so per-provider unit prices (imported
 * or assigned manually) can actually be stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ingredient_city_providers', 'measurement_unit_id')) {
            return;
        }

        Schema::table('ingredient_city_providers', function (Blueprint $table) {
            $table->unsignedBigInteger('measurement_unit_id')->nullable()->after('cost_price');
            $table->foreign('measurement_unit_id')->references('id')->on('measurement_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ingredient_city_providers', function (Blueprint $table) {
            $table->dropForeign(['measurement_unit_id']);
            $table->dropColumn('measurement_unit_id');
        });
    }
};
