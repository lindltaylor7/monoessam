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
            $table->dropConstrainedForeignId('provider_id');
            $table->foreignId('cloth_provider_id')->after('headquarter_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cloth_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cloth_provider_id');
            $table->foreignId('provider_id')->after('business_id')->constrained()->onDelete('cascade');
        });
    }
};
