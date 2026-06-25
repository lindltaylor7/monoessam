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
class LogisticController extends Controller
{
    public function index()
    {
        $dispatches = EquipmentDispatch::with(['equipable', 'origin', 'staff', 'dispatcher', 'receiver'])
            ->latest()
            ->get()
            ->map(fn ($d) => $this->transform($d));

        return inertia('logistics/Index', [
            'headquarters'       => Headquarter::with('business:id,name')->select('id', 'name', 'business_id')->get(),
            'dispatches'         => $dispatches,
            'computerEquipments' => ComputerEquipment::with('storageHeadquarter:id,name', 'responsible:id,name')
                ->select('id', 'name', 'brand', 'model', 'code', 'series', 'status', 'quantity', 'storage_headquarter_id', 'responsible_id')
                ->get(),
            'kitchenEquipments'  => KitchenEquipment::with('storageHeadquarter:id,name', 'responsible:id,name')
                ->select('id', 'name', 'brand', 'model', 'code', 'series', 'status', 'quantity', 'storage_headquarter_id', 'responsible_id')
                ->get(),
            'cafes'              => Cafe::with('unit:id,name,mine_id', 'unit.mine:id,name')
                ->select('id', 'name', 'unit_id')
                ->get(),
            'units'              => Unit::with('mine:id,name')
                ->select('id', 'name', 'mine_id')
                ->get(),
            'mines'              => Mine::with(['units.cafes'])->orderBy('name')->get(),
            'staff'              => Staff::where('status', '!=', 0)->select('id', 'name')->orderBy('name')->get(),
        ]);
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
            'id'                => $d->id,
            'dispatch_number'   => $d->dispatch_number,
            'guide_number'      => $d->guide_number,
            'status'            => $d->status,
            'equipable_type'    => $equipType,
            'equipable_id'      => $d->equipable_id,
            'quantity'          => $d->quantity,
            'equipment_name'    => $d->equipable?->name ?? '—',
            'equipment_brand'   => $d->equipable?->brand,
            'equipment_model'   => $d->equipable?->model,
            'equipment_code'    => $d->equipable?->code,
            'equipment_series'  => $d->equipable?->series,
            'equipment_status'  => $d->equipable?->status,
            'origin_id'         => $d->origin_headquarter_id,
            'origin_name'       => $d->origin?->name ?? '—',
            'destination_type'  => $d->destination_type,
            'destination_label' => $destinationLabel,
            'destination_name'  => $destinationName ?? '—',
            'destination_id'    => $d->destination_id,
            'staff_id'          => $d->staff_id,
            'staff_name'        => $d->staff?->name,
            'description'       => $d->description,
            'dispatched_by'     => $d->dispatcher?->name ?? '—',
            'dispatched_at'     => $d->dispatched_at?->format('d/m/Y H:i'),
            'dispatched_at_raw' => $d->dispatched_at?->toISOString(),
            'returned_at'       => $d->returned_at?->format('d/m/Y H:i'),
            'received_at'       => $d->received_at?->format('d/m/Y H:i'),
            'received_by'       => $d->receiver?->name,
            'origin_lat'        => $d->origin?->latitude  ? (float) $d->origin->latitude  : null,
            'origin_lng'        => $d->origin?->longitude ? (float) $d->origin->longitude : null,
            'destination_lat'   => $dest?->latitude  ? (float) $dest->latitude  : null,
            'destination_lng'   => $dest?->longitude ? (float) $dest->longitude : null,
        ];
    }
}
