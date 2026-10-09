<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * legacy_key = PlaMProgSem.nNumProgsem de BdTiburonR3. Permite reejecutar la
 * importacion de programaciones sin duplicar y rastrear cada una a su origen.
 * Las creadas desde la pantalla lo dejan en NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('weekly_programs', 'legacy_key')) {
            return;
        }

        Schema::table('weekly_programs', function (Blueprint $table) {
            $table->string('legacy_key', 64)->nullable()->unique()->after('user_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('weekly_programs', 'legacy_key')) {
            return;
        }

        Schema::table('weekly_programs', function (Blueprint $table) {
            $table->dropUnique(['legacy_key']);
            $table->dropColumn('legacy_key');
        });
    }
};
