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
        Schema::table('sales', function (Blueprint $table) {
            $table->index('date');
            $table->index(['cafe_id', 'date']);
            $table->index(['date', 'id']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->index('subdealership_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropIndex(['cafe_id', 'date']);
            $table->dropIndex(['date', 'id']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['subdealership_name']);
        });
    }
};
