<?php

namespace App\Http\Controllers;

use App\Models\Mine;
use App\Http\Requests\StoreMineRequest;
use Illuminate\Http\Request;

class MineController extends Controller
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
        $mine = Mine::create([
            'name'      => $request->name,
            'latitude'  => $request->latitude  ?? null,
            'longitude' => $request->longitude ?? null,
            'address'   => $request->address   ?? null,
        ]);

        return response()->json($mine);
    }

    public function update(Request $request, Mine $mine)
    {
        $mine->update($request->only(['name', 'latitude', 'longitude', 'address']));
        return response()->json($mine);
    }

    /**
     * Display the specified resource.
     */
    public function show(Mine $mine)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Mine $mine)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Mine $mine)
    {
        //
    }

    public function search($word = null)
    {
        $query = Mine::with(['units', 'units.cafes']);
        if ($word) {
            $query->where('name', 'like', '%' . $word . '%');
        }
        $mines = $query->get();

        return response()->json($mines);
    }

    public function mineServiceables(Request $request)
    {
        $mine = Mine::find($request->placeId);

        $selectedIds = array_map('intval', array_keys(array_filter($request->services)));

        $serviceables = $mine->services()->sync($selectedIds);

        return to_route('management');
    }
}
