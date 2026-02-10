<?php

namespace App\Http\Controllers;

use App\Models\Ingredient_city_provider;
use App\Models\Provider;
use App\Models\Ingredient;
use App\Models\City;
use App\Models\Measurement_unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProviderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('providers/Index', [
            'ingredient_city_providers' => Ingredient_city_provider::with(['ingredient', 'provider', 'city', 'measurement_unit'])->get(),
            'providers' => Provider::all(),
            'cities' => City::all(),
            'measurement_units' => Measurement_unit::all(),
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
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
        ]);

        Provider::create($request->all());

        return to_route('providers.index');
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
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
        ]);

        $provider = Provider::findOrFail($id);
        $provider->update($request->all());

        return to_route('providers.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $provider = Provider::findOrFail($id);
        $provider->delete();

        return to_route('providers.index');
    }

    public function assign(Request $request)
    {
        $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'provider_id' => 'required|exists:providers,id',
            'city_id' => 'required|exists:cities,id',
            'cost_price' => 'required|numeric',
            'measurement_unit_id' => 'nullable|exists:measurement_units,id',
        ]);

        Ingredient_city_provider::create($request->all());

        return to_route('providers.index');
    }

    public function updateAssignment(Request $request, $id)
    {
        $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'provider_id' => 'required|exists:providers,id',
            'city_id' => 'required|exists:cities,id',
            'cost_price' => 'required|numeric',
            'measurement_unit_id' => 'nullable|exists:measurement_units,id',
        ]);

        $assignment = Ingredient_city_provider::findOrFail($id);
        $assignment->update($request->all());

        return to_route('providers.index');
    }

    public function deleteAssignment($id)
    {
        $assignment = Ingredient_city_provider::findOrFail($id);
        $assignment->delete();

        return to_route('providers.index');
    }
}
