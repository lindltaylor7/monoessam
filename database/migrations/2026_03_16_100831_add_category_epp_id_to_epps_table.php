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
        Schema::table('epps', function (Blueprint $table) {
            $table->unsignedBigInteger('category_epp_id')->nullable()->after('id');
            $table->foreign('category_epp_id')->references('id')->on('category_epps')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('epps', function (Blueprint $table) {
            $table->dropForeign(['category_epp_id']);
            $table->dropColumn('category_epp_id');
        });
    }
};
