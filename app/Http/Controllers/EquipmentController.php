<?php

namespace App\Http\Controllers;

use App\Models\ComputerEquipment;
use App\Models\EquipmentHistory;
use App\Models\Headquarter;
use App\Models\KitchenEquipment;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EquipmentController extends Controller
{
    private function resolveModel(string $type): string
    {
        return match ($type) {
            'computer' => ComputerEquipment::class,
            'kitchen'  => KitchenEquipment::class,
            default    => abort(404),
        };
    }

    public function index()
    {
        return Inertia::render('equipments/Index', [
            'computerEquipments' => ComputerEquipment::with('responsible:id,name', 'storageHeadquarter:id,name')
                ->withCount('histories')
                ->latest()
                ->get(),
            'kitchenEquipments' => KitchenEquipment::with('responsible:id,name', 'storageHeadquarter:id,name')
                ->withCount('histories')
                ->latest()
                ->get(),
            'staff'        => Staff::where('status', '!=', 0)->select('id', 'name')->orderBy('name')->get(),
            'headquarters' => Headquarter::select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $type = $request->input('type');

        if ($type === 'computer') {
            $data = $request->validate([
                'name'                   => 'required|string|max:255',
                'brand'                  => 'nullable|string|max:255',
                'model'                  => 'nullable|string|max:255',
                'description'            => 'nullable|string',
                'presentation'           => 'nullable|string|max:255',
                'color'                  => 'nullable|string|max:100',
                'series'                 => 'nullable|string|max:255',
                'code'                   => 'nullable|string|max:100',
                'status'                 => 'nullable|integer|between:0,4',
                'responsible_id'         => 'nullable|exists:staff,id',
                'storage_headquarter_id' => 'nullable|exists:headquarters,id',
            ]);

            $equipment = ComputerEquipment::create($data);
        } else {
            $data = $request->validate([
                'name'                   => 'required|string|max:255',
                'brand'                  => 'nullable|string|max:255',
                'model'                  => 'nullable|string|max:255',
                'size'                   => 'nullable|string|max:255',
                'description'            => 'nullable|string',
                'color'                  => 'nullable|string|max:100',
                'current_type'           => 'nullable|string|max:100',
                'series'                 => 'nullable|string|max:255',
                'manual'                 => 'nullable|string|max:100',
                'code'                   => 'nullable|string|max:100',
                'status'                 => 'nullable|integer|between:0,4',
                'responsible_id'         => 'nullable|exists:staff,id',
                'storage_headquarter_id' => 'nullable|exists:headquarters,id',
            ]);

            $equipment = KitchenEquipment::create($data);
        }

        EquipmentHistory::create([
            'equipable_id'   => $equipment->id,
            'equipable_type' => get_class($equipment),
            'action'         => 'Registro',
            'notes'          => 'Equipo registrado en el sistema.',
            'staff_id'       => $data['responsible_id'] ?? null,
            'user_id'        => $request->user()->id,
        ]);

        return back();
    }

    public function update(Request $request, string $type, int $id)
    {
        $model = $this->resolveModel($type);
        $equipment = $model::findOrFail($id);

        if ($type === 'computer') {
            $data = $request->validate([
                'name'                   => 'required|string|max:255',
                'brand'                  => 'nullable|string|max:255',
                'model'                  => 'nullable|string|max:255',
                'description'            => 'nullable|string',
                'presentation'           => 'nullable|string|max:255',
                'color'                  => 'nullable|string|max:100',
                'series'                 => 'nullable|string|max:255',
                'code'                   => 'nullable|string|max:100',
                'status'                 => 'nullable|integer|between:0,4',
                'responsible_id'         => 'nullable|exists:staff,id',
                'storage_headquarter_id' => 'nullable|exists:headquarters,id',
            ]);
        } else {
            $data = $request->validate([
                'name'                   => 'required|string|max:255',
                'brand'                  => 'nullable|string|max:255',
                'model'                  => 'nullable|string|max:255',
                'size'                   => 'nullable|string|max:255',
                'description'            => 'nullable|string',
                'color'                  => 'nullable|string|max:100',
                'current_type'           => 'nullable|string|max:100',
                'series'                 => 'nullable|string|max:255',
                'manual'                 => 'nullable|string|max:100',
                'code'                   => 'nullable|string|max:100',
                'status'                 => 'nullable|integer|between:0,4',
                'responsible_id'         => 'nullable|exists:staff,id',
                'storage_headquarter_id' => 'nullable|exists:headquarters,id',
            ]);
        }

        // Log responsible change if it changed
        if (array_key_exists('responsible_id', $data) && $data['responsible_id'] != $equipment->responsible_id) {
            EquipmentHistory::create([
                'equipable_id'   => $equipment->id,
                'equipable_type' => get_class($equipment),
                'action'         => 'Asignación',
                'notes'          => 'Responsable actualizado.',
                'staff_id'       => $data['responsible_id'],
                'user_id'        => $request->user()->id,
            ]);
        }

        $equipment->update($data);

        return back();
    }

    public function destroy(string $type, int $id)
    {
        $model = $this->resolveModel($type);
        $model::findOrFail($id)->delete();

        return back();
    }

    public function history(string $type, int $id)
    {
        $model = $this->resolveModel($type);
        $equipment = $model::findOrFail($id);

        return response()->json(
            $equipment->histories()
                ->with('staff:id,name', 'user:id,name')
                ->latest()
                ->get()
        );
    }

    public function storeHistory(Request $request, string $type, int $id)
    {
        $model = $this->resolveModel($type);
        $equipment = $model::findOrFail($id);

        $data = $request->validate([
            'action'   => ['required', 'string', Rule::in(['Registro', 'Asignación', 'Mantenimiento', 'Reparación', 'Transferencia', 'Daño', 'Baja', 'Observación'])],
            'notes'    => 'nullable|string|max:1000',
            'staff_id' => 'nullable|exists:staff,id',
            'status'   => 'nullable|integer|between:0,4',
        ]);

        EquipmentHistory::create([
            'equipable_id'   => $equipment->id,
            'equipable_type' => get_class($equipment),
            'action'         => $data['action'],
            'notes'          => $data['notes'] ?? null,
            'staff_id'       => $data['staff_id'] ?? null,
            'user_id'        => $request->user()->id,
        ]);

        if (!empty($data['status'])) {
            $equipment->update(['status' => $data['status']]);
        }

        if (!empty($data['staff_id'])) {
            $equipment->update(['responsible_id' => $data['staff_id']]);
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }
}
