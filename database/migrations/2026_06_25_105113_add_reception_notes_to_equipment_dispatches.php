<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_dispatches', function (Blueprint $table) {
            $table->text('reception_notes')->nullable()->after('received_by');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_dispatches', function (Blueprint $table) {
            $table->dropColumn('reception_notes');
        });
    }
};
