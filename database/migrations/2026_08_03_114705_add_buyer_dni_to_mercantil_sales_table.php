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
        Schema::table('mercantil_sales', function (Blueprint $table) {
            // DNI del comprador — obligatorio a nivel de app cuando payment_condition es
            // "credito" (queda registrado a quién se le fía), opcional para "contado".
            $table->string('buyer_dni', 20)->nullable()->after('payment_condition');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mercantil_sales', function (Blueprint $table) {
            $table->dropColumn('buyer_dni');
        });
    }
};
