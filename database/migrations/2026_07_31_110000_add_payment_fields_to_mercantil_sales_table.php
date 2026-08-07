<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mercantil_sales', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->default('efectivo')->after('sale_type_id');
            $table->string('payment_condition')->nullable()->default('contado')->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('mercantil_sales', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_condition']);
        });
    }
};
