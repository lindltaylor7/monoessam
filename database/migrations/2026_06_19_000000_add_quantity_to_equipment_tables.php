<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_equipments', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->after('unit_price');
        });

        Schema::table('kitchen_equipments', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('computer_equipments', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });

        Schema::table('kitchen_equipments', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
