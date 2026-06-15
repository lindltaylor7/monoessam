<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('cloth_provider_id')->nullable()->constrained('cloth_providers')->nullOnDelete();
            $table->string('document_type')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('date');
            $table->text('notes')->nullable();
            $table->string('invoice_image')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_invoices');
    }
};
