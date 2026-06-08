<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_equipments', function (Blueprint $table) {
            $table->string('series')->nullable()->after('color');
            $table->string('code')->nullable()->after('series');
            $table->tinyInteger('status')->default(0)->after('code');
            $table->foreignId('responsible_id')->nullable()->after('status')
                ->constrained('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('computer_equipments', function (Blueprint $table) {
            $table->dropForeign(['responsible_id']);
            $table->dropColumn(['series', 'code', 'status', 'responsible_id']);
        });
    }
};
