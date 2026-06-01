<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use App\Models\Mine;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('services/Index', [
            'services' => Service::all()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required',
            'description' => 'nullable|string|max:1000',
        ]);

        Service::create($request->only('name', 'code', 'description', 'type'));

        return to_route('services');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $service = Service::find($id);
        $service->delete();
    }

    public function updatePrices(Request $request)
    {
        $services = $request->services;

        foreach ($services as $service) {
            $cafe = Cafe::find($service['pivot']['serviceable_id']);
            if ($cafe) {
                $cafe->services()->updateExistingPivot($service['id'], ['price' => $service['pivot']['price']]);
            }
        }

        return to_route('dinners');
    }

    public function list()
    {
        $services = Service::with('cafes')->get();

        return response()->json($services);
    }

    public function cafesData()
    {
        $mines = Mine::with([
            'units',
            'units.cafes',
            'units.cafes.services',
        ])->get();

        return response()->json([
            'mines'    => $mines,
            'services' => Service::orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function syncCafeServices(Request $request, Cafe $cafe)
    {
        $services = $request->services ?? [];

        $sync = collect($services)->mapWithKeys(fn($s) => [
            $s['id'] => ['price' => $s['price'] ?? 0],
        ])->toArray();

        $cafe->services()->sync($sync);

        return response()->json(['success' => true]);
    }
}
