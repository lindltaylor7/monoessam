<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Cloth;
use App\Models\Color;
use App\Models\Cafe;
use App\Models\ClothInventory;
use App\Models\ComputerEquipment;
use App\Models\KitchenEquipment;
use App\Models\Ingredient;
use App\Models\InventoryStock;
use App\Models\Headquarter;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index()
    {
        $colors = Color::all();
        $cafes = Cafe::with('unit')->get();
        $headquarters = Headquarter::with('business')->get();

        // New polymorphic stocks - we might want to paginate this too if it grows large
        $stocks = InventoryStock::with(['stockable', 'cafe', 'headquarter'])->get();

        return Inertia::render('inventory/Index', [
            'colors' => $colors,
            'cafes' => $cafes,
            'headquarters' => $headquarters,
            'stocks' => $stocks
        ]);
    }

    public function searchItems(Request $request)
    {
        $type = $request->input('type', 'cloth');
        $query = $request->input('query', '');

        $modelMap = [
            'cloth' => Cloth::class,
            'computer' => ComputerEquipment::class,
            'kitchen' => KitchenEquipment::class,
            'ingredient' => Ingredient::class,
        ];

        $modelClass = $modelMap[$type] ?? Cloth::class;

        $q = $modelClass::query();

        if ($type === 'computer' || $type === 'kitchen') {
            $q->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('brand', 'like', "%{$query}%")
                    ->orWhere('model', 'like', "%{$query}%")
                    ->orWhere('series', 'like', "%{$query}%")
                    ->orWhere('code', 'like', "%{$query}%");
            });
        } else {
            $q->where('name', 'like', "%{$query}%");
        }

        $items = $q->limit(10)->get();

        // Format label for dropdown
        $items->transform(function ($item) use ($type) {
            $label = $item->name;
            if (($type === 'computer' || $type === 'kitchen') && !$label) {
                $label = trim("{$item->brand} {$item->model}");
            } elseif ($type === 'computer' || $type === 'kitchen') {
                $label = "{$item->name} - {$item->brand} {$item->model}";
            }
            return [
                'id' => $item->id,
                'name' => $label ?: 'Sin Nombre'
            ];
        });

        return response()->json($items);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'stockable_id' => 'required|integer',
            'stockable_type' => 'required|string',
            'headquarter_id' => 'nullable|exists:headquarters,id',
            'cafe_id' => 'nullable|exists:cafes,id',
            'quantity' => 'required|numeric|min:0',
            'action' => 'required|in:set,add'
        ]);

        $modelMap = [
            'cloth' => Cloth::class,
            'computer' => ComputerEquipment::class,
            'kitchen' => KitchenEquipment::class,
            'ingredient' => Ingredient::class,
        ];

        $modelClass = $modelMap[$validated['stockable_type']] ?? $validated['stockable_type'];

        $stock = InventoryStock::firstOrNew([
            'stockable_id' => $validated['stockable_id'],
            'stockable_type' => $modelClass,
            'headquarter_id' => $validated['headquarter_id'] ?? null,
            'cafe_id' => $validated['cafe_id'] ?? null,
        ]);

        if ($validated['action'] === 'add') {
            $stock->quantity += $validated['quantity'];
        } else {
            $stock->quantity = $validated['quantity'];
        }

        $stock->save();

        return back()->with('success', 'Inventario actualizado');
    }

    public function storeColor(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:colors,name',
            'hex_code' => 'nullable|string'
        ]);

        Color::create($request->only('name', 'hex_code'));

        return back()->with('success', 'Color creado');
    }

    public function storeItem(Request $request)
    {
        $type = $request->input('type');

        if ($type === 'computer') {
            $validated = $request->validate([
                'name' => 'nullable|string',
                'description' => 'nullable|string',
                'brand' => 'nullable|string',
                'model' => 'nullable|string',
                'presentation' => 'nullable|string',
                'color' => 'nullable|string',
            ]);
            ComputerEquipment::create($validated);
        } elseif ($type === 'kitchen') {
            $validated = $request->validate([
                'name' => 'required|string',
                'brand' => 'required|string',
                'model' => 'required|string',
                'size' => 'required|string',
                'description' => 'nullable|string',
                'color' => 'nullable|string',
                'current_type' => 'nullable|string',
                'series' => 'nullable|string',
                'manual' => 'nullable|string',
                'code' => 'nullable|string',
                'status' => 'nullable|string',
            ]);
            KitchenEquipment::create($validated);
        }

        return back()->with('success', 'Equipo registrado correctamente');
    }
}
