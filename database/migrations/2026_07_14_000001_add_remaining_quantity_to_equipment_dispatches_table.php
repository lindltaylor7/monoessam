<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `quantity` debe quedar fijo para siempre como el número original de la guía.
     * `remaining_quantity` es el saldo que realmente se consume cuando ese lote se
     * reenvía (parcial o totalmente) desde un café — así una guía ya emitida nunca
     * se vuelve a alterar por una operación posterior no relacionada.
     */
    public function up(): void
    {
        Schema::table('equipment_dispatches', function (Blueprint $table) {
            $table->unsignedInteger('remaining_quantity')->nullable()->after('quantity');
        });

        DB::table('equipment_dispatches')->update(['remaining_quantity' => DB::raw('quantity')]);
    }

    public function down(): void
    {
        Schema::table('equipment_dispatches', function (Blueprint $table) {
            $table->dropColumn('remaining_quantity');
        });
    }
};
