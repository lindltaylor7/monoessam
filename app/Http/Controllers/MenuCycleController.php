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
            'id' => 'nullable|exists:menu_cycles,id',
            'serviceable_id' => 'required',
            'name' => 'nullable|string|max:255',
            'days' => 'required|integer|min:1|max:31',
            'cycle_data' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            if ($request->id) {
                $menuCycle = MenuCycle::findOrFail($request->id);
                $menuCycle->update([
                    'serviceable_id' => $validated['serviceable_id'],
                    'name' => $validated['name'],
                    'days' => $validated['days'],
                    'cycle_data' => $validated['cycle_data'],
                ]);
            } else {
                $menuCycle = MenuCycle::create([
                    'serviceable_id' => $validated['serviceable_id'],
                    'name' => $validated['name'],
                    'days' => $validated['days'],
                    'cycle_data' => $validated['cycle_data'],
                ]);
            }

            DB::commit();

            return redirect()->back()->with('success', 'Ciclo guardado correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Error al guardar el ciclo: ' . $e->getMessage()]);
        }
    }

    public function export($id)
    {
        $cycle = MenuCycle::findOrFail($id);
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\CycleExport($cycle), 'Ciclo_' . $cycle->name . '.xlsx');
    }
}
