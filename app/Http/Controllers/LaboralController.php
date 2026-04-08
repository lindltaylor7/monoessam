<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\Cafe;
use App\Models\Area;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LaboralController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('laboral/Index', [
            'staff' => Staff::with([
                'staff_files',
                'staff_financial',
                'role',
                'staffable' => function ($query) {
                    $query->morphWith([
                        Cafe::class => ['unit'], 
                        Area::class => ['headquarters'], 
                    ]);
                }
            ])->orderBy('id', 'desc')->get(),
        ]);
    }
}
