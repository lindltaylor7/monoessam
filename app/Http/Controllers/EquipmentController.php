<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Color;
use App\Models\ComputerEquipment;
use App\Models\EquipmentHistory;
use App\Models\EquipmentInvoice;
use App\Models\Headquarter;
use App\Models\KitchenEquipment;
use App\Models\Provider;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'staff'          => Staff::where('status', '!=', 0)->select('id', 'name')->orderBy('name')->get(),
            'headquarters'   => Headquarter::with('business:id,name')->select('id', 'name', 'business_id')->get(),
            'businesses'         => Business::select('id', 'name')->orderBy('name')->get(),
            'equipmentProviders' => Provider::where('type', 'equipment')->select('id', 'name', 'ruc', 'email', 'phone')->orderBy('name')->get(),
            'colors'             => Color::select('id', 'name', 'hex_code')->orderBy('name')->get(),
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
                'quantity'               => 'nullable|integer|min:1',
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
                'quantity'               => 'nullable|integer|min:1',
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

    public function storeInvoice(Request $request)
    {
        $validated = $request->validate([
            'business_id'  => 'nullable|exists:businesses,id',
            'provider_id'  => 'nullable|exists:providers,id',
            'document_type'      => 'nullable|string',
            'invoice_number'     => 'nullable|string|max:100',
            'date'               => 'required|date',
            'notes'              => 'nullable|string',
            'invoice_image'      => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'headquarter_id'     => 'nullable|exists:headquarters,id',
            'items'              => 'required|array|min:1',
            'items.*.type'       => 'required|in:computer,kitchen',
            'items.*.name'       => 'required|string|max:255',
            'items.*.brand'      => 'nullable|string|max:255',
            'items.*.model'      => 'nullable|string|max:255',
            'items.*.code'       => 'nullable|string|max:100',
            'items.*.series'     => 'nullable|string|max:255',
            'items.*.color'      => 'nullable|string|max:100',
            'items.*.status'     => 'nullable|integer|between:0,4',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.quantity'   => 'nullable|integer|min:1',
        ]);

        $imagePath = null;
        if ($request->hasFile('invoice_image')) {
            $path = $request->file('invoice_image')->store('equipment_invoices', 'public');
            $imagePath = '/storage/' . $path;
        }

        $totalAmount = collect($validated['items'])->sum(fn($i) => $i['unit_price'] * ($i['quantity'] ?? 1));

        DB::transaction(function () use ($validated, $imagePath, $totalAmount, $request) {
            $invoice = EquipmentInvoice::create([
                'business_id' => $validated['business_id'] ?? null,
                'provider_id' => $validated['provider_id'] ?? null,
                'document_type'     => $validated['document_type'] ?? null,
                'invoice_number'    => $validated['invoice_number'] ?? null,
                'date'              => $validated['date'],
                'notes'             => $validated['notes'] ?? null,
                'invoice_image'     => $imagePath,
                'total_amount'      => $totalAmount,
                'user_id'           => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                $equipmentData = [
                    'name'                   => $item['name'],
                    'brand'                  => $item['brand'] ?? null,
                    'model'                  => $item['model'] ?? null,
                    'code'                   => $item['code'] ?? null,
                    'series'                 => $item['series'] ?? null,
                    'color'                  => $item['color'] ?? null,
                    'status'                 => $item['status'] ?? 0,
                    'unit_price'             => $item['unit_price'],
                    'quantity'               => $item['quantity'] ?? 1,
                    'invoiced_quantity'      => $item['quantity'] ?? 1,
                    'equipment_invoice_id'   => $invoice->id,
                    'storage_headquarter_id' => $validated['headquarter_id'] ?? null,
                    // kitchen-specific (ignored by ComputerEquipment's $fillable)
                    'size'                   => $item['size'] ?? null,
                    'description'            => $item['description'] ?? null,
                ];

                $modelClass = $item['type'] === 'computer' ? ComputerEquipment::class : KitchenEquipment::class;
                $equipment  = $modelClass::create($equipmentData);

                EquipmentHistory::create([
                    'equipable_id'   => $equipment->id,
                    'equipable_type' => get_class($equipment),
                    'action'         => 'Registro',
                    'notes'          => 'Registrado vía factura ' . ($validated['invoice_number'] ?? 'S/N'),
                    'user_id'        => Auth::id(),
                ]);
            }
        });

        return back()->with('success', 'Equipos ingresados con factura correctamente.');
    }

    public function updateInvoiceImage(Request $request, $id)
    {
        $validated = $request->validate([
            'invoice_image' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        $invoice = EquipmentInvoice::findOrFail($id);

        if ($request->hasFile('invoice_image')) {
            $path = $request->file('invoice_image')->store('equipment_invoices', 'public');
            $invoice->update(['invoice_image' => '/storage/' . $path]);
        }

        return back()->with('success', 'Imagen de factura actualizada correctamente');
    }

    public function storeEquipmentProvider(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'ruc'   => 'nullable|string|size:11',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $provider = Provider::create([...$data, 'type' => 'equipment']);

        return back()->with('newEquipmentProvider', $provider->only('id', 'name'));
    }

    public function updateEquipmentProvider(Request $request, int $id)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'ruc'   => 'nullable|string|size:11',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        Provider::where('type', 'equipment')->findOrFail($id)->update($data);

        return back();
    }

    public function destroyEquipmentProvider(int $id)
    {
        Provider::where('type', 'equipment')->findOrFail($id)->delete();

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
                'quantity'               => 'nullable|integer|min:1',
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
                'quantity'               => 'nullable|integer|min:1',
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
            'action'     => ['required', 'string', Rule::in(['Registro', 'Asignación', 'Mantenimiento', 'Reparación', 'Transferencia', 'Daño', 'Baja', 'Observación'])],
            'notes'      => 'nullable|string|max:1000',
            'staff_id'   => 'nullable|exists:staff,id',
            'status'     => 'nullable|integer|between:0,4',
            'cargo_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip|max:10240',
        ]);

        $filePath = null;
        if ($request->hasFile('cargo_file')) {
            $filePath = $request->file('cargo_file')->store('equipment_cargos', 'public');
        }

        EquipmentHistory::create([
            'equipable_id'   => $equipment->id,
            'equipable_type' => get_class($equipment),
            'action'         => $data['action'],
            'notes'          => $data['notes'] ?? null,
            'staff_id'       => $data['staff_id'] ?? null,
            'user_id'        => $request->user()->id,
            'file_path'      => $filePath,
        ]);

        if (!empty($data['status'])) {
            $equipment->update(['status' => $data['status']]);
        }

        if (array_key_exists('staff_id', $data)) {
            $updateData = ['responsible_id' => $data['staff_id'] ?: null];
            if ($data['action'] === 'Asignación') {
                if ($filePath !== null) {
                    $updateData['cargo_path'] = $filePath;
                } elseif (empty($data['staff_id'])) {
                    $updateData['cargo_path'] = null;
                } else {
                    $updateData['cargo_path'] = null;
                }
            }
            $equipment->update($updateData);
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }
}
