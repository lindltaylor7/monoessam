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
        Schema::table('products', function (Blueprint $table) {
            // Remove unique from sku — per-cafe SKUs may repeat across cafes
            $table->dropUnique(['sku']);

            // Add cafe_id foreign key
            $table->foreignId('cafe_id')->after('id')->constrained()->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['cafe_id']);
            $table->dropColumn('cafe_id');
            $table->unique('sku');
        });
    }
};
