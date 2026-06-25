<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
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
        $unit = Unit::create([
            'name'      => $request->name,
            'mine_id'   => $request->mine_id,
            'latitude'  => $request->latitude  ?? null,
            'longitude' => $request->longitude ?? null,
            'address'   => $request->address   ?? null,
        ]);
        return response()->json($unit);
    }

    public function update(Request $request, Unit $unit)
    {
        $unit->update($request->only(['name', 'latitude', 'longitude', 'address']));
        return response()->json($unit);
    }

    /**
     * Display the specified resource.
     */
    public function show(Unit $unit)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Unit $unit)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Unit $unit)
    {
        //
    }

    public function search($mineId, $word = null)
    {
        $query = Unit::where('mine_id', $mineId)->with('cafes');
        if ($word) {
            $query->where('name', 'like', '%' . $word . '%');
        }
        $units = $query->get();

        return response()->json($units);
    }

    public function unitServiceables(Request $request)
    {
        $unit = Unit::find($request->placeId);

        $selectedIds = array_map('intval', array_keys(array_filter($request->services)));

        $serviceables = $unit->services()->sync($selectedIds);

        return to_route('management');
    }
}
