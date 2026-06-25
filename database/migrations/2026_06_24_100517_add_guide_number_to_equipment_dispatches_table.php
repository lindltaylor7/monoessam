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
            $table->string('guide_number', 20)->nullable()->after('dispatch_number')->index();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_dispatches', function (Blueprint $table) {
            $table->dropColumn('guide_number');
        });
    }
};
