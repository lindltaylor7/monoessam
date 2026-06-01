<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSubdealershipRequest;
use App\Models\Subdealership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SubdealershipController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $mineId = $user->mine_id;

        if ($mineId) {
            $subdealerships = Subdealership::whereHas('mines', fn($q) => $q->where('mines.id', $mineId))->get();
        } else {
            $subdealerships = Subdealership::all();
        }

        return Inertia::render('subdealerships/Index', [
            'subdealerships'    => $subdealerships,
            'allSubdealerships' => Subdealership::all(),
        ]);
    }

    public function create()
    {
        return Inertia::render('subdealerships/Create');
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $existing = Subdealership::whereRaw('LOWER(name) = LOWER(?)', [trim($request->name)])->first();

        if ($existing) {
            return back()->withErrors([
                'name' => 'DUPLICATE:' . $existing->id . ':' . $existing->name . ':' . ($existing->ruc ?? ''),
            ]);
        }

        $subdealership = Subdealership::create($request->only([
            'name', 'ruc', 'fiscal_address', 'legal_address', 'phone', 'email',
        ]));

        $mineId = Auth::user()->mine_id;
        if ($mineId) {
            $subdealership->mines()->attach($mineId);
        }

        return redirect()->back()->with('success', 'Subdealership created successfully.');
    }

    public function show(Subdealership $subdealership)
    {
        return Inertia::render('subdealerships/Show', [
            'subdealership' => $subdealership->load('dinners', 'units', 'mines'),
        ]);
    }

    public function edit(Subdealership $subdealership)
    {
        return Inertia::render('subdealerships/Edit', [
            'subdealership' => $subdealership,
        ]);
    }

    public function update(UpdateSubdealershipRequest $request, Subdealership $subdealership)
    {
        $subdealership->update($request->validated());

        return redirect()->route('subdealerships.index')->with('success', 'Subdealership updated successfully.');
    }

    public function search(Request $request)
    {
        $user = Auth::user();
        $mineId = $user->mine_id;
        $q = trim($request->get('q', ''));

        $query = Subdealership::query();

        if ($mineId) {
            $query->whereHas('mines', fn($q) => $q->where('mines.id', $mineId));
        }

        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }

        return response()->json($query->orderBy('name')->limit(20)->get(['id', 'name', 'ruc']));
    }

    public function attachToMine(Subdealership $subdealership)
    {
        $mineId = Auth::user()->mine_id;

        if ($mineId) {
            $subdealership->mines()->syncWithoutDetaching([$mineId]);
        }

        return redirect()->back()->with('success', 'Subconcesionaria asociada correctamente.');
    }

    public function destroy($id)
    {
        Subdealership::destroy($id);
    }
}
