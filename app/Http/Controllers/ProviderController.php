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
            'ingredient_city_providers' => Ingredient_city_provider::has('ingredient')
                ->has('provider')
                ->has('city')
                ->with(['ingredient', 'provider', 'city', 'measurement_unit'])
                ->get(),
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

    public function toggleActiveAssignment(Request $request, $id)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $assignment = Ingredient_city_provider::findOrFail($id);

        DB::transaction(function () use ($assignment, $request) {
            if ($request->boolean('is_active')) {
                Ingredient_city_provider::where('ingredient_id', $assignment->ingredient_id)
                    ->where('city_id', $assignment->city_id)
                    ->where('id', '!=', $assignment->id)
                    ->update(['is_active' => false]);
            }

            $assignment->update(['is_active' => $request->boolean('is_active')]);
        });

        return redirect()->back();
    }

    /**
     * Insumos que se usan en algún quebrado y no tienen proveedor activo con precio en la ciudad
     * elegida. Son los que en Quebrados y Ciclos aparecen "sin precio" o con precio prestado de
     * otra ciudad; se listan con las ciudades donde sí tienen precio para completar el hueco.
     */
    public function coverage(Request $request)
    {
        $validated = $request->validate([
            'city_id' => 'required|exists:cities,id',
        ]);
        $cityId = (int) $validated['city_id'];

        $covered = Ingredient_city_provider::where('city_id', $cityId)
            ->where('is_active', true)
            ->where('cost_price', '>', 0)
            ->select('ingredient_id');

        $ingredients = Ingredient::whereIn('id', DB::table('dish_recipe_ingredients')->select('ingredient_id'))
            ->whereNotIn('id', $covered)
            ->with(['assignments' => fn ($q) => $q->with('city:id,name')])
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($ingredients->map(fn ($ingredient) => [
            'id' => $ingredient->id,
            'name' => $ingredient->name,
            // Tiene proveedores cargados en la ciudad pero ninguno activo: basta con activar uno.
            'has_providers_in_city' => $ingredient->assignments->contains('city_id', $cityId),
            'priced_in' => $ingredient->assignments
                ->filter(fn ($a) => $a->is_active && (float) $a->cost_price > 0 && $a->city)
                ->map(fn ($a) => $a->city->name)
                ->unique()
                ->values(),
        ]));
    }

    public function importAssignment(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv',
            'city_id' => 'required|exists:cities,id',
        ]);

        $file = $request->file('excel_file');
        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\IngredientCityProviderImport($request->city_id), $file);

        // La importación no activa proveedores: el activo de cada insumo en la ciudad lo elige el
        // usuario, y solo ese se usa para costear quebrados, ciclos y órdenes de compra.
        return redirect()->back()->with('success', 'Datos importados correctamente. Recuerde activar el proveedor de cada insumo en esta ciudad: solo el activo se usa para costear.');
    }

    public function importWithIds(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('excel_file');
        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\IngredientCityProviderIdsImport(), $file);

        return redirect()->back()->with('success', 'Datos importados correctamente por ID.');
    }
}
