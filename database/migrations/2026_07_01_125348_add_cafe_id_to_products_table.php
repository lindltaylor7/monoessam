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
        // Remove unique from sku — per-cafe SKUs may repeat across cafes. Envuelto en try/catch:
        // el índice único ya no lo crea la migración base actual (drift entre lo que corrió en
        // producción originalmente y el archivo tal como quedó), así que en una migración fresca
        // (p. ej. sqlite en tests) no existe nada que borrar.
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique(['sku']);
            });
        } catch (\Throwable $e) {
            // no-op
        }

        // La migración base actual (create_products_table) ya crea cafe_id directamente —
        // drift entre esta migración y la base tal como quedó editada. Se agrega solo si falta.
        if (!Schema::hasColumn('products', 'cafe_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('cafe_id')->after('id')->constrained()->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['cafe_id']);
            $table->dropColumn('cafe_id');
            $table->unique('sku');
        });
    }
};
