<?php

namespace App\Http\Controllers;

use App\Models\Staff;
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
                'staff_financial'
            ])->orderBy('id', 'desc')->get(),
        ]);
    }
}
