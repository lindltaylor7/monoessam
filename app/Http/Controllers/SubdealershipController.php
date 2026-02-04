<?php

namespace App\Http\Controllers;

use App\Models\Subdealership;
use App\Http\Requests\StoreSubdealershipRequest;
use App\Http\Requests\UpdateSubdealershipRequest;
use App\Models\Dealership;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubdealershipController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('subdealerships/Index', [
            'subdealerships' => Subdealership::with('dealership')->get(),
            'dealerships' => Dealership::all(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('subdealerships/Create', [
            'dealerships' => \App\Models\Dealership::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $subdealership = Subdealership::create($request->all());

        return redirect()->back()->with('success', 'Subdealership created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Subdealership $subdealership)
    {
        return Inertia::render('subdealerships/Show', [
            'subdealership' => $subdealership->load('dealership', 'dinners', 'units', 'mines'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Subdealership $subdealership)
    {
        return Inertia::render('subdealerships/Edit', [
            'subdealership' => $subdealership,
            'dealerships' => \App\Models\Dealership::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSubdealershipRequest $request, Subdealership $subdealership)
    {
        $subdealership->update($request->validated());

        return redirect()->route('subdealerships.index')->with('success', 'Subdealership updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        Subdealership::destroy($id);
    }
}
