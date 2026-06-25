<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_financials', function (Blueprint $table) {
            // 'monthly' | 'daily'
            $table->string('salary_type', 10)->nullable()->after('salary');
        });
    }

    public function down(): void
    {
        Schema::table('staff_financials', function (Blueprint $table) {
            $table->dropColumn('salary_type');
        });
    }
};
