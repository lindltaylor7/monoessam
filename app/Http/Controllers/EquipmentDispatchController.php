<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use App\Models\ComputerEquipment;
use App\Models\EquipmentDispatch;
use App\Models\Headquarter;
use App\Models\KitchenEquipment;
use App\Models\Mine;
use App\Models\Staff;
use App\Models\Unit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EquipmentDispatchController extends Controller
{
    public function index()
    {
        $dispatches = EquipmentDispatch::with(['equipable', 'origin', 'staff', 'dispatcher'])
            ->latest()
            ->get()
            ->map(fn ($d) => $this->transform($d));

        return Inertia::render('equipments/Dispatches', [
            'dispatches'         => $dispatches,
            'computerEquipments' => ComputerEquipment::with('storageHeadquarter:id,name', 'responsible:id,name')
                ->select('id', 'name', 'brand', 'model', 'code', 'series', 'status', 'quantity', 'storage_headquarter_id', 'responsible_id')
                ->get(),
            'kitchenEquipments'  => KitchenEquipment::with('storageHeadquarter:id,name', 'responsible:id,name')
                ->select('id', 'name', 'brand', 'model', 'code', 'series', 'status', 'quantity', 'storage_headquarter_id', 'responsible_id')
                ->get(),
            'headquarters'       => Headquarter::with('business:id,name')->select('id', 'name', 'business_id')->get(),
            'cafes'              => Cafe::with('unit:id,name,mine_id', 'unit.mine:id,name')
                ->select('id', 'name', 'unit_id')
                ->get(),
            'units'              => Unit::with('mine:id,name')
                ->select('id', 'name', 'mine_id')
                ->get(),
            'mines'              => Mine::select('id', 'name')->get(),
            'staff'              => Staff::where('status', '!=', 0)->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items'                         => 'required|array|min:1',
            'items.*.equipable_type'        => 'required|in:computer,kitchen',
            'items.*.equipable_id'          => 'required|integer|min:1',
            'items.*.quantity'              => 'required|integer|min:1',
            'origin_headquarter_id'         => 'required|exists:headquarters,id',
            'destination_type'              => 'required|in:headquarter,cafe,unit,mine',
            'destination_id'               => 'required|integer|min:1',
            'staff_id'                      => 'nullable|exists:staff,id',
            'description'                   => 'nullable|string|max:1000',
        ]);

        $modelMap = [
            'computer' => ComputerEquipment::class,
            'kitchen'  => KitchenEquipment::class,
        ];

        // Validate stock for every item before creating any dispatch
        foreach ($validated['items'] as $i => $item) {
            $equipment = $modelMap[$item['equipable_type']]::find($item['equipable_id']);
            $available = $equipment?->quantity ?? 0;
            if (!$equipment || $available < $item['quantity']) {
                return back()->withErrors(["items.{$i}.quantity" => "Solo hay {$available} unidades disponibles."]);
            }
        }

        // One guide number for the whole batch
        $guideSeq    = EquipmentDispatch::whereYear('created_at', now()->year)->whereNotNull('guide_number')->distinct('guide_number')->count() + 1;
        $guideNumber = 'GR-' . now()->year . '-' . str_pad($guideSeq, 4, '0', STR_PAD_LEFT);

        $created = [];
        foreach ($validated['items'] as $item) {
            $modelClass = $modelMap[$item['equipable_type']];
            $equipment  = $modelClass::find($item['equipable_id']);

            $seq = EquipmentDispatch::whereYear('created_at', now()->year)->count() + 1;
            $dispatchNumber = 'DESP-' . now()->year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

            $equipment->decrement('quantity', $item['quantity']);

            EquipmentDispatch::create([
                'equipable_type'        => $modelClass,
                'equipable_id'          => $item['equipable_id'],
                'quantity'              => $item['quantity'],
                'origin_headquarter_id' => $validated['origin_headquarter_id'],
                'destination_type'      => $validated['destination_type'],
                'destination_id'        => $validated['destination_id'],
                'staff_id'              => $validated['staff_id'] ?? null,
                'description'           => $validated['description'] ?? null,
                'dispatch_number'       => $dispatchNumber,
                'guide_number'          => $guideNumber,
                'status'                => 'active',
                'dispatched_at'         => now(),
                'dispatched_by'         => Auth::id(),
            ]);

            $created[] = $dispatchNumber;
        }

        $count = count($created);
        return back()->with('success', "Guía {$guideNumber} — {$count} ítem(s) registrado(s).");
    }

    public function receptions()
    {
        $dispatches = EquipmentDispatch::with(['equipable', 'origin', 'staff', 'dispatcher', 'receiver'])
            ->where('status', 'active')
            ->latest()
            ->get()
            ->map(fn ($d) => $this->transform($d));

        return Inertia::render('equipments/Receptions', [
            'dispatches'  => $dispatches,
            'mines'       => Mine::with(['units.cafes'])->orderBy('name')->get(),
            'headquarters'=> Headquarter::with('business:id,name')->select('id', 'name', 'business_id')->orderBy('name')->get(),
        ]);
    }

    public function markReceived(int $id)
    {
        $dispatch = EquipmentDispatch::findOrFail($id);

        if ($dispatch->status !== 'active') {
            return back()->withErrors(['dispatch' => 'Este despacho no está activo.']);
        }

        if ($dispatch->received_at) {
            return back()->withErrors(['dispatch' => 'Este despacho ya fue confirmado como recibido.']);
        }

        $dispatch->update([
            'received_at' => now(),
            'received_by' => Auth::id(),
        ]);

        return back()->with('success', "Despacho {$dispatch->dispatch_number} confirmado como recibido.");
    }

    public function markReturned(int $id)
    {
        $dispatch = EquipmentDispatch::with('equipable')->findOrFail($id);

        if ($dispatch->status === 'returned') {
            return back()->withErrors(['dispatch' => 'Este despacho ya fue retornado.']);
        }

        $dispatch->equipable?->increment('quantity', $dispatch->quantity);
        $dispatch->update(['status' => 'returned', 'returned_at' => now()]);

        return back()->with('success', 'Equipo retornado al almacén correctamente.');
    }

    public function pdf(int $id)
    {
        $dispatch = EquipmentDispatch::with(['equipable', 'origin', 'staff', 'dispatcher'])
            ->findOrFail($id);

        $data = $this->transform($dispatch);

        $pdf = Pdf::loadView('pdf.equipment_dispatch', ['dispatch' => $data]);

        return $pdf->setPaper('a4', 'portrait')
            ->stream("Despacho_{$data['dispatch_number']}.pdf");
    }

    public function guidePdf(string $guideNumber)
    {
        $dispatches = EquipmentDispatch::with(['equipable', 'origin', 'staff', 'dispatcher'])
            ->where('guide_number', $guideNumber)
            ->orderBy('id')
            ->get();

        abort_if($dispatches->isEmpty(), 404);

        $items = $dispatches->map(fn ($d) => $this->transform($d));
        $first = $items->first();

        $pdf = Pdf::loadView('pdf.equipment_guide', [
            'guide_number'      => $guideNumber,
            'items'             => $items,
            'origin_name'       => $first['origin_name'],
            'destination_name'  => $first['destination_name'],
            'destination_label' => $first['destination_label'],
            'destination_type'  => $first['destination_type'],
            'dispatched_by'     => $first['dispatched_by'],
            'dispatched_at'     => $first['dispatched_at'],
            'staff_name'        => $first['staff_name'],
            'description'       => $first['description'],
        ]);

        return $pdf->setPaper('a4', 'portrait')
            ->stream("Guia_{$guideNumber}.pdf");
    }

    private function transform(EquipmentDispatch $d): array
    {
        $dest = match ($d->destination_type) {
            'cafe'        => Cafe::find($d->destination_id),
            'unit'        => Unit::find($d->destination_id),
            'mine'        => Mine::find($d->destination_id),
            'headquarter' => Headquarter::find($d->destination_id),
            default       => null,
        };

        $destinationName = $dest?->name ?? '—';

        $destinationLabel = match ($d->destination_type) {
            'cafe'        => 'Café / Comedor',
            'unit'        => 'Unidad',
            'mine'        => 'Mina',
            'headquarter' => 'Sede / Almacén',
            default       => '—',
        };

        $equipType = str_contains($d->equipable_type, 'Computer') ? 'computer' : 'kitchen';

        return [
            'id'               => $d->id,
            'dispatch_number'  => $d->dispatch_number,
            'guide_number'     => $d->guide_number,
            'status'           => $d->status,
            'equipable_type'   => $equipType,
            'equipable_id'     => $d->equipable_id,
            'quantity'         => $d->quantity,
            'equipment_name'   => $d->equipable?->name ?? '—',
            'equipment_brand'  => $d->equipable?->brand,
            'equipment_model'  => $d->equipable?->model,
            'equipment_code'   => $d->equipable?->code,
            'equipment_series' => $d->equipable?->series,
            'equipment_status' => $d->equipable?->status,
            'origin_id'        => $d->origin_headquarter_id,
            'origin_name'      => $d->origin?->name ?? '—',
            'destination_type' => $d->destination_type,
            'destination_label'=> $destinationLabel,
            'destination_name' => $destinationName ?? '—',
            'destination_id'   => $d->destination_id,
            'staff_id'         => $d->staff_id,
            'staff_name'       => $d->staff?->name,
            'description'      => $d->description,
            'dispatched_by'    => $d->dispatcher?->name ?? '—',
            'dispatched_at'    => $d->dispatched_at?->format('d/m/Y H:i'),
            'dispatched_at_raw'=> $d->dispatched_at?->toISOString(),
            'returned_at'      => $d->returned_at?->format('d/m/Y H:i'),
            'received_at'      => $d->received_at?->format('d/m/Y H:i'),
            'received_by'      => $d->receiver?->name,
            'origin_lat'       => $d->origin?->latitude  ? (float) $d->origin->latitude  : null,
            'origin_lng'       => $d->origin?->longitude ? (float) $d->origin->longitude : null,
            'destination_lat'  => $dest?->latitude  ? (float) $dest->latitude  : null,
            'destination_lng'  => $dest?->longitude ? (float) $dest->longitude : null,
        ];
    }
}
