<?php

namespace App\Http\Controllers;

use App\Models\Mine;
use App\Http\Requests\StoreMineRequest;
use App\Http\Requests\UpdateMineRequest;
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
            'name' => $request->name
        ]);

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
     * Update the specified resource in storage.
     */
    public function update(UpdateMineRequest $request, Mine $mine)
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
