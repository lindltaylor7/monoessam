<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Superseded by equipment_stocks (ledger por café/unidad). `quantity` sigue
     * intacto: es el número fijo de cada guía y nunca dependió de este campo.
     */
    public function up(): void
    {
        Schema::table('equipment_dispatches', function (Blueprint $table) {
            $table->dropColumn('remaining_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_dispatches', function (Blueprint $table) {
            $table->unsignedInteger('remaining_quantity')->nullable()->after('quantity');
        });
    }
};
