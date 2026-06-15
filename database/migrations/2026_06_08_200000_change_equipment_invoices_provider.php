<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_invoices', function (Blueprint $table) {
            // Drop old FK to cloth_providers
            $table->dropForeign(['cloth_provider_id']);
            $table->dropColumn('cloth_provider_id');

            // Add FK to the generic providers table (type = 'equipment')
            $table->foreignId('provider_id')->nullable()->after('business_id')
                ->constrained('providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_invoices', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
            $table->dropColumn('provider_id');

            $table->foreignId('cloth_provider_id')->nullable()
                ->constrained('cloth_providers')->nullOnDelete();
        });
    }
};
