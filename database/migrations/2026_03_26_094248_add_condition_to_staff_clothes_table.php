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
        Schema::table('staff_clothes', function (Blueprint $table) {
            $table->enum('condition', ['Nuevo', 'En Almacén'])->default('Nuevo')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_clothes', function (Blueprint $table) {
            $table->dropColumn('condition');
        });
    }
};
