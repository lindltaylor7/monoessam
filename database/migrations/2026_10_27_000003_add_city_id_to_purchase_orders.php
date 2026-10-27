<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ciudad de abastecimiento con la que se genera el quebrado (PO): la del comedor de sus
 * programaciones. Define qué proveedor y precio se muestran en el detalle. Las órdenes
 * anteriores quedan en null y siguen usando la ciudad por defecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('weekly_program_id')->constrained('cities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });
    }
};
