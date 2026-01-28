<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Cloth;
use App\Models\Color;
use App\Models\Cafe;
use App\Models\ClothInventory;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index()
    {
        $clothes = Cloth::all();
        $colors = Color::all();
        $cafes = Cafe::all();
        $inventory = ClothInventory::with(['cloth', 'color', 'cafe'])->get();

        return Inertia::render('inventory/Index', [
            'clothes' => $clothes,
            'colors' => $colors,
            'cafes' => $cafes,
            'inventory' => $inventory
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'cloth_id' => 'required|exists:cloths,id',
            'color_id' => 'required|exists:colors,id',
            'cafe_id' => 'nullable|exists:cafes,id',
            'quantity' => 'required|integer|min:0',
            'action' => 'required|in:set,add'
        ]);

        $inventory = ClothInventory::firstOrNew([
            'cloth_id' => $validated['cloth_id'],
            'color_id' => $validated['color_id'],
            'cafe_id' => $validated['cafe_id']
        ]);

        if ($validated['action'] === 'add') {
            $inventory->quantity += $validated['quantity'];
        } else {
            $inventory->quantity = $validated['quantity'];
        }

        $inventory->save();

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
}
