<?php

namespace App\Http\Controllers;

use App\Models\Dish_category;
use App\Models\Level;
use App\Models\MenuCycle;
use App\Models\Mine;
use App\Models\Structure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MenuCycleController extends Controller
{
    public function index()
    {
        return Inertia::render('cycles/Index', [
            'mines'          => Mine::with(['units', 'units.cafes', 'units.cafes.services'])->get(),
            'structures'     => Structure::with('costs')->get(),
            'savedCycles'    => MenuCycle::orderBy('id', 'desc')->get(),
            'dishCategories' => Dish_category::all(),
            'levels'         => Level::all(),
        ]);
    }

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

    public function export(Request $request, $id)
    {
        $cycle = MenuCycle::findOrFail($id);
        $hideKcal = $request->query('hide_kcal') === 'true' || $request->query('hide_kcal') === '1';
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\CycleExport($cycle, $hideKcal), 'Ciclo_' . $cycle->name . '.xlsx');
    }
}
