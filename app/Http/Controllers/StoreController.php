<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use App\Models\ComputerEquipment;
use App\Models\EquipmentDispatch;
use App\Models\EquipmentStock;
use App\Models\Headquarter;
use App\Models\KitchenEquipment;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        // Unidades a las que se le puede enviar de forma general (sin especificar un café),
        // p. ej. una guía dirigida a "Southern" en vez de a un comedor puntual de esa unidad.
        $units = Unit::with('mine:id,name')
            ->when($mineId, fn($q) => $q->where('mine_id', $mineId))
            ->orderBy('name')
            ->get(['id', 'name', 'mine_id']);

        $unitIds = $units->pluck('id');

        $dispatches = EquipmentDispatch::with(['equipable', 'origin', 'originCafe', 'dispatcher', 'receiver'])
            ->where(function ($q) use ($cafeIds, $unitIds) {
                $q->where(function ($q2) use ($cafeIds) {
                    $q2->where('destination_type', 'cafe')->whereIn('destination_id', $cafeIds);
                })->orWhere(function ($q2) use ($unitIds) {
                    $q2->where('destination_type', 'unit')->whereIn('destination_id', $unitIds);
                });
            })
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

        // Stock real por café/unidad (ledger), agrupado para que la tabla de Despachos y el
        // modal de envío lo lean directo — sin reconstruirlo a partir del historial de guías.
        $stocksByLocation = function (string $locationCol, $ids) {
            return EquipmentStock::whereIn($locationCol, $ids)
                ->get(['stockable_type', 'stockable_id', $locationCol, 'quantity'])
                ->groupBy($locationCol)
                ->map(fn($group) => $group->mapWithKeys(fn($r) => [
                    (str_contains($r->stockable_type, 'Computer') ? 'computer' : 'kitchen') . '-' . $r->stockable_id => $r->quantity,
                ]));
        };

        $cafeStocks = $stocksByLocation('cafe_id', $cafeIds);
        $unitStocks = $stocksByLocation('unit_id', $unitIds);

        return Inertia::render('store/Index', [
            'dispatches'   => $dispatches,
            'cafes'        => $cafes,
            'units'        => $units,
            'allCafes'     => $allCafes,
            'headquarters' => $headquarters,
            'cafeStocks'   => $cafeStocks,
            'unitStocks'   => $unitStocks,
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

        $created = DB::transaction(function () use ($validated, $modelMap, $guideNumber) {
            $created = [];
            foreach ($validated['items'] as $item) {
                $modelClass = $modelMap[$item['equipable_type']];

                // "Disponible" se lee directo del ledger de stock por café (equipment_stocks),
                // no reconstruyéndolo desde el historial de guías. El lock + chequeo dentro de
                // la transacción evita que dos envíos simultáneos sobrevendan el mismo stock.
                $stock = EquipmentStock::where('stockable_type', $modelClass)
                    ->where('stockable_id', $item['equipable_id'])
                    ->where('cafe_id', $validated['origin_cafe_id'])
                    ->lockForUpdate()
                    ->first();

                if (($stock->quantity ?? 0) < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => 'No hay suficiente stock disponible para uno de los equipos seleccionados.',
                    ]);
                }

                $stock->decrement('quantity', $item['quantity']);

                $seq            = EquipmentDispatch::whereYear('created_at', now()->year)->count() + 1;
                $dispatchNumber = 'DESP-' . now()->year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

                // La guía se crea con su propio `quantity` fijo — es un documento histórico
                // independiente, no se ve afectado por lo que pase después con el stock del café.
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

            return $created;
        });

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
            'destination_type' => $d->destination_type,
            'destination_id'  => $d->destination_id,
            'dispatched_by'   => $d->dispatcher?->name ?? '—',
            'dispatched_at'   => $d->dispatched_at?->format('d/m/Y H:i'),
            'received_at'     => $d->received_at?->format('d/m/Y H:i'),
            'received_by'     => $d->receiver?->name,
            'reception_notes' => $d->reception_notes,
        ];
    }
}
