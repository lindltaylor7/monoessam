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
            $table->timestamp('received_at')->nullable()->after('returned_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete()->after('received_at');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_dispatches', function (Blueprint $table) {
            $table->dropForeign(['received_by']);
            $table->dropColumn(['received_at', 'received_by']);
        });
    }
};
