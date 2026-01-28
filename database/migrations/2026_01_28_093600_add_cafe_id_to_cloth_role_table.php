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
        Schema::table('cloth_role', function (Blueprint $table) {
            $table->foreignId('cafe_id')->after('role_id')->nullable()->constrained('cafes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cloth_role', function (Blueprint $table) {
            $table->dropForeign(['cafe_id']);
            $table->dropColumn('cafe_id');
        });
    }
};
