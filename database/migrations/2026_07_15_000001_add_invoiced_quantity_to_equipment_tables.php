<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `quantity` es el stock EN VIVO (baja al despachar). Las facturas deben mostrar siempre
     * lo que se registró originalmente, sin importar cuánto se haya despachado después —
     * para eso se guarda `invoiced_quantity`, fijo desde que se crea el registro.
     */
    public function up(): void
    {
        Schema::table('computer_equipments', function (Blueprint $table) {
            $table->unsignedInteger('invoiced_quantity')->nullable()->after('quantity');
        });
        Schema::table('kitchen_equipments', function (Blueprint $table) {
            $table->unsignedInteger('invoiced_quantity')->nullable()->after('quantity');
        });

        DB::table('computer_equipments')->update(['invoiced_quantity' => DB::raw('quantity')]);
        DB::table('kitchen_equipments')->update(['invoiced_quantity' => DB::raw('quantity')]);
    }

    public function down(): void
    {
        Schema::table('computer_equipments', function (Blueprint $table) {
            $table->dropColumn('invoiced_quantity');
        });
        Schema::table('kitchen_equipments', function (Blueprint $table) {
            $table->dropColumn('invoiced_quantity');
        });
    }
};
