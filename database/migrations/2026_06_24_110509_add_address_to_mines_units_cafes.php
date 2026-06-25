<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['mines', 'units', 'cafes'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) {
                $table->string('address')->nullable()->after('longitude');
            });
        }
    }

    public function down(): void
    {
        foreach (['mines', 'units', 'cafes'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) {
                $table->dropColumn('address');
            });
        }
    }
};
