<?php

namespace App\Http\Controllers;

use App\Models\Dealership;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DealershipController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('dealerships/Index', [
            'dealerships' => Dealership::withCount(['contracts', 'mines'])->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ruc' => 'nullable|string|max:255',
            'fiscal_address' => 'nullable|string|max:255',
            'legal_address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        Dealership::create($validated);

        return to_route('dealerships.index')->with('success', 'Concesionaria creada correctamente.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ruc' => 'nullable|string|max:255',
            'fiscal_address' => 'nullable|string|max:255',
            'legal_address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $dealership = Dealership::findOrFail($id);
        $dealership->update($validated);

        return to_route('dealerships.index')->with('success', 'Concesionaria actualizada correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $dealership = Dealership::findOrFail($id);
        $dealership->delete();

        return to_route('dealerships.index')->with('success', 'Concesionaria eliminada correctamente.');
    }
}
