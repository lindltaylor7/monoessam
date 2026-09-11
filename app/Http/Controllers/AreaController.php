<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Http\Requests\StoreAreaRequest;
use App\Http\Requests\UpdateAreaRequest;
use App\Models\Headquarter;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'cafe_id'         => 'nullable|exists:cafes,id',
            'headquarter_id'  => 'required|exists:headquarters,id',
        ]);

        // Solo campos validados: antes era Area::create($request->all()), el único
        // mass assignment sin filtrar del dominio.
        $area = Area::create([
            'name'    => $validated['name'],
            'cafe_id' => $validated['cafe_id'] ?? null,
        ]);

        Headquarter::findOrFail($validated['headquarter_id'])->areas()->attach([$area->id]);

        return to_route('businesses.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Area $area)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Area $area)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAreaRequest $request, Area $area)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        Area::findOrFail($id)->delete();

        return to_route('businesses.index');
    }
}
