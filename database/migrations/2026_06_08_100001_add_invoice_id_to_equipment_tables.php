<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_equipments', function (Blueprint $table) {
            $table->foreignId('equipment_invoice_id')->nullable()->after('storage_headquarter_id')
                ->constrained('equipment_invoices')->nullOnDelete();
        });

        Schema::table('kitchen_equipments', function (Blueprint $table) {
            $table->foreignId('equipment_invoice_id')->nullable()->after('storage_headquarter_id')
                ->constrained('equipment_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('computer_equipments', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\EquipmentInvoice::class);
            $table->dropColumn('equipment_invoice_id');
        });

        Schema::table('kitchen_equipments', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\EquipmentInvoice::class);
            $table->dropColumn('equipment_invoice_id');
        });
    }
};
