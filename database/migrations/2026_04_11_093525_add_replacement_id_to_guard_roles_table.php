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
        Schema::table('guard_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('replacement_id')->nullable()->after('staff_id');
            $table->foreign('replacement_id')->references('id')->on('staff')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guard_roles', function (Blueprint $table) {
            $table->dropForeign(['replacement_id']);
            $table->dropColumn('replacement_id');
        });
    }
};
