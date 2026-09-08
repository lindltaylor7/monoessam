<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Añade "β caroteno equivalentes totales" (INFOODS CARTBQ) a la tabla de dosificación
     * nutricional. Es la única columna nueva que introduce el orden de la Tabla Peruana de
     * Composición de Alimentos usado en el reporte "Dosificación Nutricional"; el resto de
     * campos ya existían (`retinol` se reutiliza como "Vitamina A eq. totales" / VITA).
     */
    public function up(): void
    {
        Schema::table('dosifications', function (Blueprint $table) {
            $table->decimal('carotene', 11, 2)->nullable()->after('iron');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dosifications', function (Blueprint $table) {
            $table->dropColumn('carotene');
        });
    }
};
