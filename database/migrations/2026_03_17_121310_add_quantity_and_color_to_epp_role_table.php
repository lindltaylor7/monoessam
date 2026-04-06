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
        Schema::table('epp_role', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->after('cafe_id');
            $table->foreignId('color_id')->nullable()->after('quantity')->constrained('colors')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('epp_role', function (Blueprint $table) {
            $table->dropForeign(['color_id']);
            $table->dropColumn(['quantity', 'color_id']);
        });
    }
};
