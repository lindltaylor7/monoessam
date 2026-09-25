<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a comedor mark one provider as the "active" one to use per ingredient+city (several providers
 * can quote the same ingredient in the same city; only one should be picked for purchasing).
 * Backfills one active provider per (ingredient_id, city_id) group so existing data isn't left with
 * no active provider anywhere: prefers the cheapest priced row, falling back to the first row when
 * every provider in the group has a null cost_price.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ingredient_city_providers', 'is_active')) {
            Schema::table('ingredient_city_providers', function (Blueprint $table) {
                $table->boolean('is_active')->default(false)->after('measurement_unit_id');
            });
        }

        $groups = DB::table('ingredient_city_providers')
            ->select('id', 'ingredient_id', 'city_id', 'cost_price')
            ->get()
            ->groupBy(fn($row) => $row->ingredient_id . ':' . $row->city_id);

        foreach ($groups as $rows) {
            $active = $rows->reduce(function ($carry, $row) {
                if ($carry === null) return $row;
                if ($carry->cost_price !== null && $row->cost_price !== null) {
                    return $row->cost_price < $carry->cost_price ? $row : $carry;
                }
                if ($carry->cost_price !== null) return $carry;
                if ($row->cost_price !== null) return $row;
                return $carry;
            });

            DB::table('ingredient_city_providers')->where('id', $active->id)->update(['is_active' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('ingredient_city_providers', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
