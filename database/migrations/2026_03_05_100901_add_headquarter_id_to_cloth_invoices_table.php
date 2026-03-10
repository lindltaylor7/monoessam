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
        Schema::table('cloth_invoices', function (Blueprint $table) {
            $table->foreignId('headquarter_id')->after('business_id')->nullable()->constrained()->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cloth_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('headquarter_id');
        });
    }
};
