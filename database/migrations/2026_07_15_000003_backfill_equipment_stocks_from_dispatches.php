<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Puebla equipment_stocks con el mismo criterio que hoy usa StoreController::sendDispatch()
     * para calcular "disponible": despachos activos, ya recepcionados, sumando remaining_quantity.
     * Así el punto de partida del ledger coincide exactamente con lo que ya se ve en el sistema.
     */
    public function up(): void
    {
        $destinations = ['cafe' => 'cafe_id', 'unit' => 'unit_id'];

        foreach ($destinations as $destType => $col) {
            $rows = DB::table('equipment_dispatches')
                ->select('equipable_type', 'equipable_id', 'destination_id', DB::raw('SUM(remaining_quantity) as qty'))
                ->where('destination_type', $destType)
                ->where('status', 'active')
                ->whereNotNull('received_at')
                ->groupBy('equipable_type', 'equipable_id', 'destination_id')
                ->havingRaw('SUM(remaining_quantity) > 0')
                ->get();

            foreach ($rows as $row) {
                DB::table('equipment_stocks')->insert([
                    'stockable_type' => $row->equipable_type,
                    'stockable_id'   => $row->equipable_id,
                    $col             => $row->destination_id,
                    'quantity'       => $row->qty,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('equipment_stocks')->truncate();
    }
};
