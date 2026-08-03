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
        if (!Schema::hasColumn('dish_recipes', 'level_id')) {
            Schema::table('dish_recipes', function (Blueprint $table) {
                $table->unsignedBigInteger('level_id')->nullable()->after('dish_id');
                $table->foreign('level_id')->references('id')->on('levels')->onDelete('cascade');
            });

            // Backfill sin JOIN en el UPDATE (sintaxis MySQL-only) para que la migración
            // también corra en sqlite (usado por la suite de tests con RefreshDatabase).
            DB::table('dish_recipe_levels')
                ->orderBy('id')
                ->chunk(200, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('dish_recipes')->where('id', $row->dish_recipe_id)->update(['level_id' => $row->level_id]);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dish_recipes', function (Blueprint $table) {
            $table->dropForeign(['level_id']);
            $table->dropColumn('level_id');
        });
    }
};
