<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class CafeController extends Controller
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
        $cafe = Cafe::create([
            'name'      => $request->name,
            'unit_id'   => $request->unit_id,
            'latitude'  => $request->latitude  ?? null,
            'longitude' => $request->longitude ?? null,
            'address'   => $request->address   ?? null,
        ]);

        $cafe->businesses()->sync([$request->business_id]);

        return response()->json($cafe);
    }

    public function update(Request $request, Cafe $cafe)
    {
        $cafe->update($request->only(['name', 'latitude', 'longitude', 'address']));
        return response()->json($cafe);
    }

    /** Return the services associated with a cafe (with pivot price). */
    public function services($id)
    {
        $cafe = Cafe::find($id);
        if (!$cafe) {
            return response()->json([], 404);
        }
        return response()->json($cafe->services()->get());
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $cafe = Cafe::with([
            'staffs.role',
            'guards.assignedRoles.role',
            'guards.assignedRoles.staff.staff_files',
            'guards.assignedRoles.replacement',
            'periods.staffs'
        ])->find($id);


        if (!$cafe) {
            return response()->json(['message' => 'Café no encontrado'], 404);
        }


        $assignedRoles = $cafe->guards->flatMap(function ($guard) {
            return $guard->assignedRoles;
        });

        // Obtener una colección de IDs de usuarios que tienen un rol asignado
        $assignedUserIds = $assignedRoles
            ->filter(fn($role) => $role->staff_id)
            ->pluck('staff_id')
            ->unique() // Asegura que cada ID aparezca una sola vez
            ->toArray(); // Convierte a array simple de IDs para la comparación

        // --- 3. Filtrar Usuarios ---

        // Colección de todos los usuarios pertenecientes al café
        $allUsers = $cafe->staffs;

        // Usuarios asignados: aquellos cuyos IDs están en la lista $assignedUserIds
        $assignedUsers = $allUsers->whereIn('id', $assignedUserIds)->values();

        // Usuarios no asignados: aquellos cuyos IDs NO están en la lista $assignedUserIds
        $unassignedUsers = $allUsers->whereNotIn('id', $assignedUserIds)->values();

        // --- 4. Devolver la Respuesta ---

        return response()->json([
            'users' => [
                'assigned' => $assignedUsers,
                'unassigned' => $unassignedUsers,
            ],
            'guards' => $cafe->guards,
            'periods' => $cafe->periods,
            'staff' => $cafe->staffs,

        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cafe $cafe)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cafe $cafe)
    {
        //
    }

    public function cafeServiceables(Request $request)
    {
        $cafe = Cafe::findOrFail($request->placeId);

        $selectedIds = array_map('intval', array_keys(array_filter($request->services ?? [])));

        // syncWithoutDetaching + detach manual conservaría el pivote price; el front que
        // consume esto gestiona el precio por separado (services.update-prices).
        $cafe->services()->sync($selectedIds);

        return to_route('management');
    }

    public function printTest()
    {
        try {
            $nombreImpresora = "EPSON TM-T20II Receipt";

            $connector = new WindowsPrintConnector($nombreImpresora);

            $printer = new Printer($connector);

            $printer->text("Hello World\n");
            $printer->cut();
            $printer->close();

            return response()->json(['success' => 'Ticket impreso correctamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al imprimir: ' . $e->getMessage()]);
        }
    }

    public function exportHeadcount($id)
    {
        // Antes usaba $cafe->staff (relación inexistente: es staffs()) y $role->user
        // (guard_roles tiene staff_id, no user), y escribía cabeceras con header()+exit
        // saltándose Laravel. Todo corregido: se cruza el personal del comedor con los
        // staff_id asignados a alguna guardia.
        $cafe = Cafe::with(['staffs', 'guards.assignedRoles'])->findOrFail($id);

        $assignedStaffIds = $cafe->guards
            ->flatMap(fn($guard) => $guard->assignedRoles)
            ->pluck('staff_id')
            ->filter()
            ->unique()
            ->all();

        $assignedStaff = $cafe->staffs->whereIn('id', $assignedStaffIds)->values();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'Nombre');
        $sheet->setCellValue('C1', 'DNI');

        $row = 2;
        foreach ($assignedStaff as $staff) {
            $sheet->setCellValue("A{$row}", $staff->id);
            $sheet->setCellValue("B{$row}", $staff->name);
            $sheet->setCellValue("C{$row}", $staff->dni);
            $row++;
        }

        $fileName = 'headcount_' . \Illuminate\Support\Str::slug($cafe->name) . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function search($unitId, $word = null)
    {
        $query = Cafe::where('unit_id', $unitId);
        if ($word) {
            $query->where('name', 'like', '%' . $word . '%');
        }
        $cafes = $query->get();

        return response()->json($cafes);
    }

    public function rolesIndex()
    {
        return Inertia::render('cafes/Roles', [
            'cafes' => Cafe::with(['roles', 'unit.mine'])->get(),
            'roles' => \Spatie\Permission\Models\Role::all()
        ]);
    }

    public function syncRoles(Request $request, $id)
    {
        $request->validate([
            'role_ids' => 'array',
            'role_ids.*' => 'exists:roles,id'
        ]);

        $cafe = Cafe::findOrFail($id);
        $cafe->roles()->sync($request->role_ids);

        return back()->with('success', 'Roles actualizados correctamente');
    }
}
