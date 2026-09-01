<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amplía `dinners.dni` para admitir además del DNI (8 dígitos) el Carné de Extranjería,
     * que puede tener hasta 12 caracteres alfanuméricos. Se fija el límite explícito en 12.
     */
    public function up(): void
    {
        Schema::table('dinners', function (Blueprint $table) {
            $table->string('dni', 12)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dinners', function (Blueprint $table) {
            $table->string('dni')->nullable()->change();
        });
    }
};
