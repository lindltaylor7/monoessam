<?php

namespace App\Http\Controllers;

use App\Models\Period;
use App\Http\Requests\StorePeriodRequest;
use App\Http\Requests\UpdatePeriodRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeriodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        $period = Period::create([
            'cafe_id' => $request->cafe_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date
        ]);

        foreach ($request->users as $user) {
            DB::table('period_staffs')->insert([
                'period_id' => $period->id,
                'staff_id' => $user['id'],
                'status' => $request->status
            ]);
        }

        return response()->json([
            'period_id' => $period->id
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Period $period)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Period $period)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePeriodRequest $request, Period $period)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $period = Period::find($id);
        $period->delete();
    }

    public function periodUser($id, Request $request)
    {
        // updateOrInsert( [Condiciones de búsqueda], [Valores a actualizar/insertar] )
        DB::table('period_staffs')->updateOrInsert(
            [
                'period_id' => $request->period_id, // (o usar $id si viene de la ruta)
                'staff_id'   => $request->staff_id
            ],
            [
                'status'    => $request->status
            ]
        );

        return response()->json(['message' => 'Status actualizado correctamente']);
    }
}
