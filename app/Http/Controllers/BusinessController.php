<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Dealership;
use App\Models\Headquarter;
use App\Models\Service;
use App\Models\Subdealership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BusinessController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('businesses/Index', [
            'businesses' => Business::with('services')->get(),
            'headquarters' => Headquarter::with(['business', 'areas'])->get(),
            'services' => Service::all(),
            'dealerships' => Dealership::all(),
            'subdealerships' => Subdealership::with('mines')->get(),
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

    public function businessServices(Request $request)
    {

        $business = Business::find($request->businessId);

        $selectedIds = array_map('intval', array_keys(array_filter($request->services)));

        $business->services()->sync($selectedIds);

        return to_route('businesses');
    }

    public function uploadLogo(Request $request, string $id)
    {
        $request->validate(['logo' => 'required|image|max:2048']);

        $business = Business::findOrFail($id);

        if ($business->logo) {
            Storage::disk('public')->delete($business->logo);
        }

        $path = $request->file('logo')->store('business-logos', 'public');
        $business->update(['logo' => $path]);

        return response()->json(['logo' => $path]);
    }
}
