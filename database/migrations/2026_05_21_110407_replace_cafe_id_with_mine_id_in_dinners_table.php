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
        Schema::table('dinners', function (Blueprint $table) {
            $table->dropForeign(['cafe_id']);
            $table->dropColumn('cafe_id');

            $table->unsignedBigInteger('mine_id')->nullable()->after('subdealership_id');
            $table->foreign('mine_id')->references('id')->on('mines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dinners', function (Blueprint $table) {
            $table->dropForeign(['mine_id']);
            $table->dropColumn('mine_id');

            $table->unsignedBigInteger('cafe_id')->nullable()->after('subdealership_id');
            $table->foreign('cafe_id')->references('id')->on('cafes')->nullOnDelete();
        });
    }
};
