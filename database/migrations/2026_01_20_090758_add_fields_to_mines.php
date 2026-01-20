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
        Schema::table('mines', function (Blueprint $table) {
            $table->unsignedBigInteger('dealership_id')->nullable();
            $table->foreign('dealership_id')->references('id')->on('dealerships')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mines', function (Blueprint $table) {
            $table->dropForeign(['dealership_id']);
            $table->dropColumn('dealership_id');
        });
    }
};
