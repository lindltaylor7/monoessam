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
        Schema::table('cloth_invoice_items', function (Blueprint $table) {
            $table->foreignId('cloth_id')->nullable()->change();
            $table->foreignId('epp_id')->nullable()->after('cloth_id')->constrained('epps')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cloth_invoice_items', function (Blueprint $table) {
            $table->foreignId('cloth_id')->nullable(false)->change();
            $table->dropForeign(['epp_id']);
            $table->dropColumn('epp_id');
        });
    }
};
