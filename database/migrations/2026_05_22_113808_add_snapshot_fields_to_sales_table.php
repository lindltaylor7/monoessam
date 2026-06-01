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
            $table->string('business_name', 50)->nullable()->after('business_id');
            $table->string('cafe_name', 100)->nullable()->after('business_name');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->after('cafe_name');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['business_name', 'cafe_name', 'user_id']);
        });
    }
};
