<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('mercantil_id')->nullable()->after('cafe_id')->constrained('mercantiles')->onDelete('cascade');
        });

        // Backfill: one Mercantil per Cafe that currently owns products, under that cafe's unit.
        $cafeIds = DB::table('products')->whereNotNull('cafe_id')->distinct()->pluck('cafe_id');

        foreach ($cafeIds as $cafeId) {
            $cafe = DB::table('cafes')->find($cafeId);
            if (!$cafe) {
                continue;
            }

            $mercantilId = DB::table('mercantiles')->insertGetId([
                'unit_id'    => $cafe->unit_id,
                'name'       => $cafe->name,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('products')->where('cafe_id', $cafeId)->update(['mercantil_id' => $mercantilId]);
        }

        // MODIFY es sintaxis MySQL-only; en otros drivers (sqlite en tests con RefreshDatabase)
        // se omite el NOT NULL a nivel de columna — la validación de la app ya exige
        // mercantil_id como campo requerido, así que no afecta el comportamiento real.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products MODIFY mercantil_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['cafe_id']);
            $table->dropColumn('cafe_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('cafe_id')->nullable()->after('id')->constrained()->onDelete('cascade');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['mercantil_id']);
            $table->dropColumn('mercantil_id');
        });
    }
};
