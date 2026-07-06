<?php

namespace App\Http\Controllers;

use App\Models\Mercantil;
use App\Models\Sale_type;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PosController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $mercantiles = Mercantil::whereHas('unit', fn($q) => $q->where('mine_id', $user->mine_id))
            ->with([
                'unit:id,name',
                'products' => fn($q) => $q->where('is_active', true)
                    ->orderBy('category')
                    ->orderBy('name'),
            ])
            ->get(['id', 'name', 'unit_id']);

        return Inertia::render('pos/Index', [
            'mercantiles' => $mercantiles,
            'sale_types'  => Sale_type::all(),
        ]);
    }
}
