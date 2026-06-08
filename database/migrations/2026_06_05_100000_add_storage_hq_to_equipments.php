<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_equipments', function (Blueprint $table) {
            $table->foreignId('storage_headquarter_id')
                ->nullable()
                ->after('responsible_id')
                ->constrained('headquarters')
                ->nullOnDelete();
        });

        Schema::table('kitchen_equipments', function (Blueprint $table) {
            $table->foreignId('storage_headquarter_id')
                ->nullable()
                ->after('responsible_id')
                ->constrained('headquarters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('computer_equipments', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Headquarter::class, 'storage_headquarter_id');
            $table->dropColumn('storage_headquarter_id');
        });

        Schema::table('kitchen_equipments', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Headquarter::class, 'storage_headquarter_id');
            $table->dropColumn('storage_headquarter_id');
        });
    }
};
