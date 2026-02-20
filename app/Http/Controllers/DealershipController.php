<?php

namespace App\Http\Controllers;

use App\Models\Dealership;
use App\Models\Subdealership;
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
            'dealerships' => Dealership::all(),
            'subdealerships' => Subdealership::with('dealership')->get(),
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
        $dealership = Dealership::create($request->all());

        return to_route('dealerships.index');
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
        //
    }
}
