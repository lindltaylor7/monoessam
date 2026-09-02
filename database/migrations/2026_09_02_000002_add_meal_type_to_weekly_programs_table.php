<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_programs', function (Blueprint $table) {
            // Servicio (Desayuno/Almuerzo/Cena/Refrigerio) al que pertenece la programación.
            // Una programación siempre es de un único servicio; se elige en la matriz de planificación.
            $table->string('meal_type')->nullable()->after('structure_id');
        });

        // Backfill: para programaciones ya guardadas, tomar el meal_type predominante de sus items.
        $rows = \DB::table('weekly_program_items')
            ->select('weekly_program_id', 'meal_type', \DB::raw('COUNT(*) as c'))
            ->groupBy('weekly_program_id', 'meal_type')
            ->orderByDesc('c')
            ->get()
            ->groupBy('weekly_program_id');

        foreach ($rows as $programId => $group) {
            \DB::table('weekly_programs')
                ->where('id', $programId)
                ->whereNull('meal_type')
                ->update(['meal_type' => $group->first()->meal_type]);
        }
    }

    public function down(): void
    {
        Schema::table('weekly_programs', function (Blueprint $table) {
            $table->dropColumn('meal_type');
        });
    }
};
