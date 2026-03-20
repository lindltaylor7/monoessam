<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Cafe;
use App\Models\Mine;
use App\Models\Service;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('management/Index', [
            'mines' => Mine::with(['units', 'units.cafes.roles', 'services'])->get(),
            'units' => Unit::with(['mine', 'mine.services', 'services'])->get(),
            'cafes' => Cafe::with(['unit', 'unit.mine', 'unit.services', 'services', 'roles'])->get(),
            'services' => Service::all(),
            'businesses' => Business::all(),
            'roles' => \Spatie\Permission\Models\Role::all()
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
        //
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
