<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicate recipes per (dish_id, level_id), keeping the one with
        // the most ingredients (newest id as tie-breaker)
        $duplicateGroups = DB::table('dish_recipes')
            ->select('dish_id', 'level_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('level_id')
            ->groupBy('dish_id', 'level_id')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicateGroups as $group) {
            $recipes = DB::table('dish_recipes')
                ->where('dish_id', $group->dish_id)
                ->where('level_id', $group->level_id)
                ->get()
                ->map(function ($recipe) {
                    $recipe->ingredients_count = DB::table('dish_recipe_ingredients')
                        ->where('dish_recipe_id', $recipe->id)
                        ->count();
                    return $recipe;
                })
                ->sortByDesc(fn ($r) => [$r->ingredients_count, $r->id])
                ->values();

            $idsToDelete = $recipes->slice(1)->pluck('id');

            DB::table('dish_recipe_ingredients')->whereIn('dish_recipe_id', $idsToDelete)->delete();
            DB::table('dish_recipes')->whereIn('id', $idsToDelete)->delete();
        }

        Schema::table('dish_recipes', function (Blueprint $table) {
            $table->unique(['dish_id', 'level_id'], 'dish_recipes_dish_level_unique');
        });
    }

    public function down(): void
    {
        Schema::table('dish_recipes', function (Blueprint $table) {
            $table->dropUnique('dish_recipes_dish_level_unique');
        });
    }
};
