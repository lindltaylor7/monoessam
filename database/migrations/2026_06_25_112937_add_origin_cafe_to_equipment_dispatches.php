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
        Schema::table('equipment_dispatches', function (Blueprint $table) {
            $table->foreignId('origin_cafe_id')
                ->nullable()
                ->after('origin_headquarter_id')
                ->constrained('cafes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_dispatches', function (Blueprint $table) {
            $table->dropForeign(['origin_cafe_id']);
            $table->dropColumn('origin_cafe_id');
        });
    }
};
