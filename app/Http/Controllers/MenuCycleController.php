<?php

namespace App\Http\Controllers;

use App\Models\MenuCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuCycleController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'serviceable_id' => 'required', // Assuming serviceables table is where the id comes from
            'days' => 'required|integer|min:1|max:31',
            'cycle_data' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $menuCycle = MenuCycle::updateOrCreate(
                ['serviceable_id' => $validated['serviceable_id']],
                [
                    'days' => $validated['days'],
                    'cycle_data' => $validated['cycle_data'],
                ]
            );

            DB::commit();

            return redirect()->back()->with('success', 'Ciclo guardado correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Error al guardar el ciclo: ' . $e->getMessage()]);
        }
    }

    public function export($serviceable_id)
    {
        $cycle = MenuCycle::where('serviceable_id', $serviceable_id)->firstOrFail();
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\CycleExport($cycle), 'Ciclo_Servicio_' . $serviceable_id . '.xlsx');
    }
}
