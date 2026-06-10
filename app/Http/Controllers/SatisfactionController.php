<?php

namespace App\Http\Controllers;

use App\Models\CafeSatisfaction;
use App\Models\Mine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SatisfactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // El usuario solo ve la mina que tiene asignada; sin asignación ve todas (perfil administrador)
        $mines = Mine::with(['units.cafes.services'])
            ->when($user->mine_id, fn($q) => $q->where('id', $user->mine_id))
            ->get();

        return Inertia::render('satisfaction/Index', [
            'mines' => $mines,
            'locked_mine_id' => $user->mine_id,
            'today_votes' => CafeSatisfaction::whereDate('date', Carbon::today())
                ->selectRaw('cafe_id, COUNT(*) as total')
                ->groupBy('cafe_id')
                ->pluck('total', 'cafe_id'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cafe_id' => 'required|exists:cafes,id',
            'score' => 'required|integer|min:1|max:5',
            'service' => 'required|string|max:100',
        ]);

        // El comedor debe pertenecer a la mina asignada al usuario
        $user = $request->user();
        if ($user->mine_id) {
            $cafe = \App\Models\Cafe::with('unit')->findOrFail($validated['cafe_id']);
            if ((int) ($cafe->unit?->mine_id) !== (int) $user->mine_id) {
                abort(403, 'El comedor seleccionado no pertenece a su mina asignada.');
            }
        }

        $satisfaction = CafeSatisfaction::create([
            'cafe_id' => $validated['cafe_id'],
            'score' => $validated['score'],
            'service' => $validated['service'],
            'date' => Carbon::today()->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'id' => $satisfaction->id,
            'today_total' => CafeSatisfaction::where('cafe_id', $validated['cafe_id'])
                ->whereDate('date', Carbon::today())
                ->count(),
        ]);
    }
}
