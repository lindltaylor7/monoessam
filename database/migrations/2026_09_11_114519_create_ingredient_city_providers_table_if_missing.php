<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The real ingredient-provider pricing table, `ingredient_city_providers` (plural), has never had
 * a tracked migration — it exists in production/beta and local dev with live data, but was created
 * outside Laravel's migration history (best guess: the older singular
 * `2025_07_08_104003_create_ingredient_city_provider_table` migration ran once, then the table was
 * manually renamed to plural to match Eloquent's default convention, without a migration recording
 * it — which is why its foreign key constraints are still named with the old singular prefix).
 *
 * This migration only backfills the table where it's missing (a fresh install, CI, or any other
 * environment that never got the manual rename) so schema history matches reality going forward.
 * It's a no-op everywhere the table already exists.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('ingredient_city_providers')) {
            return;
        }

        Schema::create('ingredient_city_providers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ingredient_id')->nullable();
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->cascadeOnDelete();
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->foreign('city_id')->references('id')->on('cities')->cascadeOnDelete();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: this table may carry live pricing data on environments where this migration
        // actually created it; nothing here should ever drop it automatically.
    }
};
