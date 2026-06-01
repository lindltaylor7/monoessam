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
        Schema::table('structures', function (Blueprint $table) {
            $table->dropForeign(['cafe_id']);
            $table->dropColumn('cafe_id');
            $table->foreignId('serviceable_id')->nullable()->constrained('serviceables')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('structures', function (Blueprint $table) {
            $table->dropForeign(['serviceable_id']);
            $table->dropColumn('serviceable_id');
            $table->foreignId('cafe_id')->nullable()->constrained('cafes')->onDelete('cascade');
        });
    }
};
