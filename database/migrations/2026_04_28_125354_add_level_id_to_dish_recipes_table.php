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
            $table->unsignedBigInteger('level_id')->nullable()->after('dish_id');
            $table->foreign('level_id')->references('id')->on('levels')->onDelete('cascade');
        });
        
        // Migrate existing data from pivot table
        DB::statement('
            UPDATE dish_recipes dr
            JOIN dish_recipe_levels drl ON dr.id = drl.dish_recipe_id
            SET dr.level_id = drl.level_id
        ');
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
