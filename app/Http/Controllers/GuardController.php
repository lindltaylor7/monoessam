<?php

namespace App\Http\Controllers;

use App\Models\Guard;
use App\Http\Requests\StoreGuardRequest;
use App\Http\Requests\UpdateGuardRequest;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuardController extends Controller
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
        $createdGuards = [];

        $guardsData = $request->guards;

        foreach ($guardsData as $guardData) {
            $guard = Guard::create([
                'cafe_id' => $request->cafe_id,
                'name' => $guardData['name'],
            ]);

            $createdGuards[] = $guard;
        }

        return redirect()->back()->with('newGuards', $createdGuards);
    }

    /**
     * Display the specified resource.
     */
    public function show(Guard $guard)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Guard $guard)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGuardRequest $request, Guard $guard)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $guard = Guard::find($id);

        if ($guard) {
            $guard->roles()->detach();
            $guard->delete();
        }
    }

    public function insertGuardRoles(Request $request)
    {
        $guard = Guard::find($request->guard_id);

        // syncWithoutDetaching en vez de attach: reenviar el mismo role_id ya no crea
        // puestos duplicados en el turno.
        $guard->roles()->syncWithoutDetaching($request->roles_ids);

        return redirect()->back();
    }

    public function deleteGuardRoles($roleId)
    {
        DB::table('guard_roles')->where('id', $roleId)->delete();

        //return redirect()->back();
    }

    public function insertGuardRolesUser(Request $request)
    {
        DB::table('guard_roles')
            ->where('id', $request->guard_role_id)
            ->update(['staff_id' => $request->user_id]);

        // Siempre se responde JSON: DB::update() devuelve 0 cuando el valor ya era ese
        // staff_id, y antes el método caía en un return null -> 200 con cuerpo vacío que
        // el front no sabía interpretar.
        return response()->json(['message' => 'Rol de guardia actualizado correctamente.'], 200);
    }

    /**
     * $id es el id de la fila guard_roles (el puesto concreto), NO el staff_id: antes se
     * filtraba por staff_id y quitar a alguien de un turno lo quitaba de TODOS sus turnos.
     */
    public function deleteGuardRolesUser($id)
    {
        DB::table('guard_roles')->where('id', $id)->update(['staff_id' => null]);

        return response()->json(['message' => 'Personal retirado del puesto.'], 200);
    }

    public function updateObservation(Request $request)
    {
        DB::table('guard_roles')
            ->where('id', $request->guard_role_id)
            ->update(['observation' => $request->observation]);

        return response()->json(['message' => 'Observación actualizada.']);
    }

    public function assignReplacement(Request $request)
    {
        DB::table('guard_roles')
            ->where('id', $request->guard_role_id)
            ->update(['replacement_id' => $request->user_id]);

        return response()->json(['message' => 'Reemplazo asignado.']);
    }

    public function deleteReplacement($id)
    {
        DB::table('guard_roles')->where('id', $id)->update(['replacement_id' => null]);
    }
}
