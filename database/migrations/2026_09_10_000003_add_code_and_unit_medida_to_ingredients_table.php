<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
            $table->string('unit_medida', 20)->nullable()->after('code');
        });

        // Backfill: por cada insumo (inputs), busca el/los ingredientes cuyo `name` coincide
        // sin distinguir mayúsculas/minúsculas y copia el código y la unidad de medida.
        DB::table('inputs')
            ->select('code', 'name', 'unit_of_measure')
            ->orderBy('id')
            ->chunk(500, function ($inputs) {
                foreach ($inputs as $input) {
                    $name = trim(mb_strtolower($input->name));
                    if ($name === '') {
                        continue;
                    }

                    DB::table('ingredients')
                        ->whereRaw('LOWER(TRIM(name)) = ?', [$name])
                        ->update([
                            'code'        => $input->code,
                            'unit_medida' => $input->unit_of_measure,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['code', 'unit_medida']);
        });
    }
};
