<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use App\Models\ComputerEquipment;
use App\Models\EquipmentDispatch;
use App\Models\Headquarter;
use App\Models\KitchenEquipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class StoreController extends Controller
{
    public function index()
    {
        $mineId = Auth::user()?->mine_id;

        $cafes = Cafe::with('unit:id,name,mine_id', 'unit.mine:id,name')
            ->when($mineId, fn($q) => $q->whereHas('unit', fn($q) => $q->where('mine_id', $mineId)))
            ->orderBy('name')
            ->get(['id', 'name', 'unit_id']);

        $cafeIds = $cafes->pluck('id');

        $dispatches = EquipmentDispatch::with(['equipable', 'origin', 'originCafe', 'dispatcher', 'receiver'])
            ->where('destination_type', 'cafe')
            ->whereIn('destination_id', $cafeIds)
            ->where('status', 'active')
            ->latest()
            ->get()
            ->map(fn($d) => $this->transform($d));

        // Destinations for the send modal
        $allCafes = Cafe::with('unit:id,name,mine_id', 'unit.mine:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'unit_id']);

        $headquarters = Headquarter::with('business:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'business_id']);

        return Inertia::render('store/Index', [
            'dispatches'   => $dispatches,
            'cafes'        => $cafes,
            'allCafes'     => $allCafes,
            'headquarters' => $headquarters,
        ]);
    }

    public function sendDispatch(Request $request)
    {
        $validated = $request->validate([
            'origin_cafe_id'   => 'required|exists:cafes,id',
            'destination_type' => 'required|in:headquarter,cafe',
            'destination_id'   => 'required|integer|min:1',
            'description'      => 'nullable|string|max:1000',
            'items'            => 'required|array|min:1',
            'items.*.equipable_type' => 'required|in:computer,kitchen',
            'items.*.equipable_id'   => 'required|integer|min:1',
            'items.*.quantity'       => 'required|integer|min:1',
        ]);

        $modelMap = [
            'computer' => ComputerEquipment::class,
            'kitchen'  => KitchenEquipment::class,
        ];

        $guideSeq    = EquipmentDispatch::whereYear('created_at', now()->year)->whereNotNull('guide_number')->distinct('guide_number')->count() + 1;
        $guideNumber = 'GR-' . now()->year . '-' . str_pad($guideSeq, 4, '0', STR_PAD_LEFT);

        $created = [];
        foreach ($validated['items'] as $item) {
            $modelClass = $modelMap[$item['equipable_type']];

            // Mark the original dispatch to this café as returned so it leaves the store view
            EquipmentDispatch::where('destination_type', 'cafe')
                ->where('destination_id', $validated['origin_cafe_id'])
                ->where('status', 'active')
                ->where('equipable_type', $modelClass)
                ->where('equipable_id', $item['equipable_id'])
                ->latest()
                ->first()
                ?->update(['status' => 'returned']);

            $seq            = EquipmentDispatch::whereYear('created_at', now()->year)->count() + 1;
            $dispatchNumber = 'DESP-' . now()->year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

            EquipmentDispatch::create([
                'equipable_type'        => $modelClass,
                'equipable_id'          => $item['equipable_id'],
                'quantity'              => $item['quantity'],
                'origin_headquarter_id' => null,
                'origin_cafe_id'        => $validated['origin_cafe_id'],
                'destination_type'      => $validated['destination_type'],
                'destination_id'        => $validated['destination_id'],
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
        return back()->with('success', "Guía {$guideNumber} generada — {$count} ítem(s) enviado(s).");
    }

    private function transform(EquipmentDispatch $d): array
    {
        $equipType = str_contains($d->equipable_type, 'Computer') ? 'computer' : 'kitchen';

        $originName = $d->origin?->name ?? $d->originCafe?->name ?? '—';

        return [
            'id'              => $d->id,
            'dispatch_number' => $d->dispatch_number,
            'guide_number'    => $d->guide_number,
            'status'          => $d->status,
            'equipable_type'  => $equipType,
            'equipable_id'    => $d->equipable_id,
            'quantity'        => $d->quantity,
            'equipment_name'  => $d->equipable?->name ?? '—',
            'equipment_brand' => $d->equipable?->brand,
            'equipment_model' => $d->equipable?->model,
            'equipment_code'  => $d->equipable?->code,
            'origin_name'     => $originName,
            'destination_id'  => $d->destination_id,
            'dispatched_by'   => $d->dispatcher?->name ?? '—',
            'dispatched_at'   => $d->dispatched_at?->format('d/m/Y H:i'),
            'received_at'     => $d->received_at?->format('d/m/Y H:i'),
            'received_by'     => $d->receiver?->name,
            'reception_notes' => $d->reception_notes,
        ];
    }
}
