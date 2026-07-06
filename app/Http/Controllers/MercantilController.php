<?php

namespace App\Http\Controllers;

use App\Models\Mercantil;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MercantilController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $units = Unit::where('mine_id', $user->mine_id)->orderBy('name')->get(['id', 'name']);

        $mercantiles = Mercantil::whereIn('unit_id', $units->pluck('id'))
            ->with('unit:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('mercantiles/Index', [
            'mercantiles' => $mercantiles,
            'units'       => $units,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'unit_id'   => 'required|exists:units,id',
            'name'      => 'required|string|max:255',
            'address'   => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        Mercantil::create($data);

        return redirect()->back();
    }

    public function update(Request $request, int $id)
    {
        $mercantil = Mercantil::findOrFail($id);

        $data = $request->validate([
            'unit_id'   => 'required|exists:units,id',
            'name'      => 'required|string|max:255',
            'address'   => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $mercantil->update($data);

        return redirect()->back();
    }

    public function destroy(int $id)
    {
        Mercantil::findOrFail($id)->delete();

        return redirect()->back();
    }
}
