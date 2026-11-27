<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Minas / Unidades / Comedores archivados: ya no operan, pero conservan su historia
 * (programaciones, ciclos de menú migrados del sistema antiguo). Siguen visibles en los
 * módulos de alimentación y en reportes; los módulos operativos los ocultan con
 * el scope `operational()` (ver App\Models\Concerns\Archivable).
 *
 * Archiva de entrada todo lo que vino marcado como "z(Hist)" desde BdTiburon,
 * en cascada hacia abajo (mina → unidades → comedores).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['mines', 'units', 'cafes'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->timestamp('archived_at')->nullable()->index();
            });
        }

        $now = now();

        $mineIds = DB::table('mines')->where('name', 'like', 'z(Hist)%')->pluck('id');
        DB::table('mines')->whereIn('id', $mineIds)->update(['archived_at' => $now]);

        $unitIds = DB::table('units')
            ->whereIn('mine_id', $mineIds)
            ->orWhere('name', 'like', 'z(Hist)%')
            ->pluck('id');
        DB::table('units')->whereIn('id', $unitIds)->update(['archived_at' => $now]);

        DB::table('cafes')
            ->whereIn('unit_id', $unitIds)
            ->orWhere('name', 'like', 'z(Hist)%')
            ->update(['archived_at' => $now]);
    }

    public function down(): void
    {
        foreach (['cafes', 'units', 'mines'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropIndex(['archived_at']);
                $t->dropColumn('archived_at');
            });
        }
    }
};
