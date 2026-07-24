<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_equipments', function (Blueprint $table) {
            $table->string('cargo_path')->nullable()->after('responsible_id');
        });

        Schema::table('kitchen_equipments', function (Blueprint $table) {
            $table->string('cargo_path')->nullable()->after('responsible_id');
        });

        Schema::table('equipment_histories', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('staff_id');
        });
    }

    public function down(): void
    {
        Schema::table('computer_equipments', function (Blueprint $table) {
            $table->dropColumn('cargo_path');
        });

        Schema::table('kitchen_equipments', function (Blueprint $table) {
            $table->dropColumn('cargo_path');
        });

        Schema::table('equipment_histories', function (Blueprint $table) {
            $table->dropColumn('file_path');
        });
    }
};
