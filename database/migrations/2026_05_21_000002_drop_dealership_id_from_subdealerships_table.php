<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subdealerships', function (Blueprint $table) {
            $table->dropForeign(['dealership_id']);
            $table->dropColumn('dealership_id');
        });
    }

    public function down(): void
    {
        Schema::table('subdealerships', function (Blueprint $table) {
            $table->foreignId('dealership_id')->nullable()->constrained('dealerships')->nullOnDelete();
        });
    }
};
