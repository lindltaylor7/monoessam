<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Añade "% Alcohol" (etanol) a la tabla de dosificación nutricional, última columna del
     * reporte "Dosificación Nutricional".
     */
    public function up(): void
    {
        Schema::table('dosifications', function (Blueprint $table) {
            $table->decimal('alcohol', 11, 2)->nullable()->after('cholesterol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dosifications', function (Blueprint $table) {
            $table->dropColumn('alcohol');
        });
    }
};
