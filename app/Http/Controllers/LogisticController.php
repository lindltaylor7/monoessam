<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use App\Models\ComputerEquipment;
use App\Models\Epp;
use App\Models\EquipmentDispatch;
use App\Models\Headquarter;
use App\Models\InventoryStock;
use App\Models\KitchenEquipment;
use App\Models\Mine;
use App\Models\Staff;
use App\Models\Unit;
class LogisticController extends Controller
{
    public function index()
    {
        $dispatches = EquipmentDispatch::with(['equipable', 'origin.business', 'originCafe.unit', 'staff', 'dispatcher', 'receiver', 'color'])
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
            // Filas de stock de EPP con Sede asignada — cada una es una combinación
            // (EPP, talla, color) dispachable, igual que una fila de computerEquipments/
            // kitchenEquipments. Solo se listan las que tienen sede (headquarter_id), ya que la
            // disponibilidad en la guía depende de la Sede Origen elegida, igual que los equipos.
            'eppStocks'          => InventoryStock::with(['stockable:id,name', 'color:id,name,hex_code'])
                ->where('stockable_type', Epp::class)
                ->whereNotNull('headquarter_id')
                ->whereNull('cafe_id')
                ->whereNull('unit_id')
                ->where('quantity', '>', 0)
                ->get()
                ->map(fn ($s) => [
                    'id'                     => $s->stockable_id,
                    'name'                   => $s->stockable?->name ?? '—',
                    'size'                   => $s->size,
                    'color_id'               => $s->color_id,
                    'color_name'             => $s->color?->name,
                    'color_hex'              => $s->color?->hex_code,
                    'quantity'               => $s->quantity,
                    'storage_headquarter_id' => $s->headquarter_id,
                ])
                ->values(),
            'cafes'              => Cafe::with('unit:id,name,mine_id', 'unit.mine:id,name')
                ->select('id', 'name', 'unit_id')
                ->get(),
            'units'              => Unit::with('mine:id,name')
                ->select('id', 'name', 'mine_id')
                ->get(),
            'mines'              => Mine::with(['units.cafes'])->orderBy('name')->get(),
            // Se agrega cafe_id/unit_id (derivados de la relación polimórfica `staffable`) para
            // poder filtrar el buscador de "Encargado de Recepción" según el destino elegido.
            'staff'              => Staff::where('status', '!=', 0)
                ->with('staffable')
                ->select('id', 'name', 'staffable_id', 'staffable_type')
                ->orderBy('name')
                ->get()
                ->map(function ($s) {
                    $isCafe = $s->staffable_type === Cafe::class;
                    return [
                        'id'      => $s->id,
                        'name'    => $s->name,
                        'cafe_id' => $isCafe ? $s->staffable_id : null,
                        'unit_id' => $isCafe ? $s->staffable?->unit_id : null,
                    ];
                })
                ->values(),
        ]);
    }

    private function transform(EquipmentDispatch $d): array
    {
        $dest = match ($d->destination_type) {
            'cafe'        => Cafe::with('unit:id,name')->find($d->destination_id),
            'unit'        => Unit::find($d->destination_id),
            'mine'        => Mine::find($d->destination_id),
            'headquarter' => Headquarter::with('business:id,name')->find($d->destination_id),
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

        $equipType = match (true) {
            str_contains($d->equipable_type, 'Computer') => 'computer',
            str_contains($d->equipable_type, 'Epp')      => 'epp',
            default                                       => 'kitchen',
        };

        return [
            'id'                => $d->id,
            'dispatch_number'   => $d->dispatch_number,
            'guide_number'      => $d->guide_number,
            'status'            => $d->status,
            'equipable_type'    => $equipType,
            'equipable_id'      => $d->equipable_id,
            'quantity'          => $d->quantity,
            'size'              => $d->size,
            'color_name'        => $d->color?->name,
            'equipment_name'    => $d->equipable?->name ?? '—',
            'equipment_brand'   => $d->equipable?->brand,
            'equipment_model'   => $d->equipable?->model,
            'equipment_code'    => $d->equipable?->code,
            'equipment_series'  => $d->equipable?->series,
            'equipment_status'  => $d->equipable?->status,
            'origin_id'           => $d->origin_headquarter_id ?? $d->origin_cafe_id,
            'origin_label'        => $d->origin_cafe_id ? 'Café / Comedor' : 'Sede / Almacén',
            'origin_name'         => $d->origin?->name ?? $d->originCafe?->name ?? '—',
            'origin_business'     => $d->origin_cafe_id ? null : $d->origin?->business?->name,
            'origin_unit'         => $d->origin_cafe_id ? $d->originCafe?->unit?->name : null,
            'destination_type'    => $d->destination_type,
            'destination_label'   => $destinationLabel,
            'destination_name'    => $destinationName ?? '—',
            'destination_business'=> $d->destination_type === 'headquarter' ? $dest?->business?->name : null,
            'destination_unit'    => $d->destination_type === 'cafe' ? $dest?->unit?->name : null,
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
