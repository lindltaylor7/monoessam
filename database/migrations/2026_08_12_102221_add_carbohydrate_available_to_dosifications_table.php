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
        Schema::table('dosifications', function (Blueprint $table) {
            $table->decimal('carbohydrate_available', 8, 2)->nullable()->after('carbohydrate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dosifications', function (Blueprint $table) {
            $table->dropColumn('carbohydrate_available');
        });
    }
};
