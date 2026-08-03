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
            // Subdealership a la que se factura la venta al crédito, y el comensal (Dinner)
            // identificado por el DNI, si se encontró o se registró uno nuevo. Ambos nullable:
            // una venta al crédito puede quedar sin comensal vinculado (dni suelto en buyer_dni).
            $table->foreignId('subdealership_id')->nullable()->after('buyer_dni')->constrained()->nullOnDelete();
            $table->foreignId('dinner_id')->nullable()->after('subdealership_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mercantil_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subdealership_id');
            $table->dropConstrainedForeignId('dinner_id');
        });
    }
};
