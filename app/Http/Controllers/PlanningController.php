<?php

namespace App\Http\Controllers;

use App\Models\WeeklyProgram;
use App\Models\WeeklyProgramItem;
use App\Models\DailyPortion;
use App\Models\Cafe;
use App\Models\Dish;
use App\Models\Dish_category;
use App\Models\MenuStructure;
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
        return Inertia::render('planning/Index', [
            'cafes' => Cafe::all(),
            'programs' => WeeklyProgram::with('cafe')->get(),
            'dish_categories' => Dish_category::all(),
            'menu_structure' => MenuStructure::with('dish_category')->get(),
            'dishes' => Dish::with('dish_categories')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cafe_id' => 'required|exists:cafes,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'items' => 'required|array',
            'portions' => 'required|array',
        ]);

        $program = WeeklyProgram::create([
            'cafe_id' => $validated['cafe_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'user_id' => Auth::id(),
            'status' => 'borrador',
        ]);

        foreach ($validated['items'] as $item) {
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
