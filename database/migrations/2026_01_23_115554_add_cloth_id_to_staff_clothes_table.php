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
        Schema::table('staff_clothes', function (Blueprint $table) {
            $table->foreignId('cloth_id')->nullable()->constrained()->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_clothes', function (Blueprint $table) {
            $table->dropForeign(['cloth_id']);
            $table->dropColumn('cloth_id');
        });
    }
};
