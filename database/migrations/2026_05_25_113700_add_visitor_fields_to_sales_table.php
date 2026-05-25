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
            $table->boolean('is_visitor')->default(false)->after('status');
            $table->unsignedBigInteger('mine_id')->nullable()->after('is_visitor');
            $table->foreign('mine_id')->references('id')->on('mines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['mine_id']);
            $table->dropColumn(['is_visitor', 'mine_id']);
        });
    }
};
