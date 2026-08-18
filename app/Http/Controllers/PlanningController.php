<?php

namespace App\Http\Controllers;

use App\Models\WeeklyProgram;
use App\Models\WeeklyProgramItem;
use App\Models\DailyPortion;
use App\Models\Cafe;
use App\Models\Dish_category;
use App\Models\MenuStructure;
use App\Models\Serviceable;
use App\Models\Service;
use App\Services\QuebradosService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class PlanningController extends Controller
{
    protected $quebradosService;

    public function __construct(QuebradosService $quebradosService)
    {
        $this->quebradosService = $quebradosService;
    }

    public function index()
    {
        $menuCycles = \App\Models\MenuCycle::orderBy('id', 'desc')->get();

        // Batch-fetch serviceables/services instead of querying inside the map() loop.
        $serviceableIds = $menuCycles->pluck('serviceable_id')->filter()->unique();
        $serviceables = Serviceable::whereIn('id', $serviceableIds)->get()->keyBy('id');
        $serviceIds = $serviceables->pluck('service_id')->filter()->unique();
        $services = Service::whereIn('id', $serviceIds)->get()->keyBy('id');

        $menuCycles = $menuCycles->map(function ($cycle) use ($serviceables, $services) {
            $serviceable = $serviceables->get($cycle->serviceable_id);
            $mealType = 'N/A';
            $cafeId = null;
            if ($serviceable) {
                $service = $services->get($serviceable->service_id);
                if ($service) {
                    $mealType = $service->name;
                }
                if ($serviceable->serviceable_type === \App\Models\Cafe::class) {
                    $cafeId = $serviceable->serviceable_id;
                }
            }
            $cycle->meal_type = $mealType;
            $cycle->cafe_id = $cafeId;
            return $cycle;
        });

        return Inertia::render('planning/Index', [
            'cafes' => Cafe::all(),
            'programs' => WeeklyProgram::with(['cafe', 'structure'])->get(),
            'dish_categories' => Dish_category::all(),
            'menu_structure' => MenuStructure::with('dish_category')->get(),
            'structures' => \App\Models\Structure::with('costs')->get(),
            'menu_cycles' => $menuCycles,
            'mines' => \App\Models\Mine::with(['units', 'units.cafes', 'units.cafes.services'])->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cafe_id' => 'required|exists:cafes,id',
            'structure_id' => 'nullable|exists:structures,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'items' => 'required|array',
            'portions' => 'required|array',
        ]);

        $program = WeeklyProgram::create([
            'cafe_id' => $validated['cafe_id'],
            'structure_id' => $validated['structure_id'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'user_id' => Auth::id(),
            'status' => 'borrador',
        ]);

        foreach ($validated['items'] as $item) {
            // Empty grid cells (no dish assigned yet) are sent too, since the form allows
            // saving a plan before every cell is filled in — skip them rather than inserting
            // a row with a null dish_id, which the column doesn't allow.
            if (empty($item['dish_id'])) {
                continue;
            }

            WeeklyProgramItem::create([
                'weekly_program_id' => $program->id,
                'date' => $item['date'],
                'meal_type' => $item['meal_type'],
                'dish_category_id' => $item['dish_category_id'],
                'dish_id' => $item['dish_id'],
            ]);
        }

        foreach ($validated['portions'] as $portion) {
            DailyPortion::create([
                'weekly_program_id' => $program->id,
                'date' => $portion['date'],
                'meal_type' => $portion['meal_type'],
                'portions_count' => $portion['portions_count'],
            ]);
        }

        return redirect()->route('planning.index')->with('success', 'Plan guardado correctamente');
    }

    public function generatePurchaseOrder($id)
    {
        $program = WeeklyProgram::findOrFail($id);
        $order = $this->quebradosService->generatePurchaseOrder($program);

        return redirect()->route('purchase_orders.show', $order->id)->with('success', 'Pedido (Quebrado) generado con éxito');
    }
}
