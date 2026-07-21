<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use App\Models\ComputerEquipment;
use App\Models\Epp;
use App\Models\EquipmentDispatch;
use App\Models\EquipmentStock;
use App\Models\Headquarter;
use App\Models\InventoryStock;
use App\Models\KitchenEquipment;
use App\Models\Mine;
use App\Models\Staff;
use App\Models\Unit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EquipmentDispatchController extends Controller
{
    public function index()
    {
        $dispatches = EquipmentDispatch::with(['equipable', 'origin', 'originCafe', 'staff', 'dispatcher', 'color'])
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
            'items.*.equipable_type'        => 'required|in:computer,kitchen,epp',
            'items.*.equipable_id'          => 'required|integer|min:1',
            'items.*.quantity'              => 'required|integer|min:1',
            'items.*.size'                  => 'nullable|string|max:100',
            'items.*.color_id'              => 'nullable|exists:colors,id',
            'origin_headquarter_id'         => 'required|exists:headquarters,id',
            'destination_type'              => 'required|in:headquarter,cafe,unit,mine',
            'destination_id'               => 'required|integer|min:1',
            'staff_id'                      => 'nullable|exists:staff,id',
            'description'                   => 'nullable|string|max:1000',
        ]);

        $modelMap = [
            'computer' => ComputerEquipment::class,
            'kitchen'  => KitchenEquipment::class,
            'epp'      => Epp::class,
        ];

        // El stock de EPP vive en InventoryStock, atado a la Sede Origen elegida (no en una
        // columna `quantity` propia del modelo — Epp no la tiene). Puede haber más de una fila
        // para la misma sede+talla+color si coexisten distintas condiciones (Nuevo / En
        // Almacén): sin filtrar por condición, un decrement() ejecutado directo sobre el query
        // builder genera un solo UPDATE que afecta a TODAS las filas que matchean (no "la
        // primera"), dejando alguna en negativo aunque la que se veía en pantalla tuviera stock
        // de sobra. Se suman todas las filas para saber el disponible real, y al descontar se
        // recorren una por una (ya como modelos, nunca por el builder) sin dejar ninguna bajo cero.
        $eppStockRows = fn (array $item) => InventoryStock::where([
            'stockable_type' => Epp::class,
            'stockable_id'   => $item['equipable_id'],
            'headquarter_id' => $validated['origin_headquarter_id'],
            'cafe_id'        => null,
            'unit_id'        => null,
            'size'           => $item['size'] ?? null,
            'color_id'       => $item['color_id'] ?? null,
        ])->where('quantity', '>', 0)->orderByDesc('quantity')->get();

        $decrementEppStock = function (array $item) use ($eppStockRows) {
            $remaining = $item['quantity'];
            foreach ($eppStockRows($item) as $row) {
                if ($remaining <= 0) break;
                $take = min($remaining, (float) $row->quantity);
                $row->decrement('quantity', $take);
                $remaining -= $take;
            }
        };

        // Validate stock for every item before creating any dispatch
        foreach ($validated['items'] as $i => $item) {
            if ($item['equipable_type'] === 'epp') {
                $available = (int) $eppStockRows($item)->sum('quantity');
                if ($available < $item['quantity']) {
                    return back()->withErrors(["items.{$i}.quantity" => "Solo hay {$available} unidades disponibles en esa sede."]);
                }
                continue;
            }

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

            $seq = EquipmentDispatch::whereYear('created_at', now()->year)->count() + 1;
            $dispatchNumber = 'DESP-' . now()->year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

            if ($item['equipable_type'] === 'epp') {
                $decrementEppStock($item);
            } else {
                $modelClass::find($item['equipable_id'])->decrement('quantity', $item['quantity']);
            }

            EquipmentDispatch::create([
                'equipable_type'        => $modelClass,
                'equipable_id'          => $item['equipable_id'],
                'quantity'              => $item['quantity'],
                'size'                  => $item['size'] ?? null,
                'color_id'              => $item['color_id'] ?? null,
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
        $dispatches = EquipmentDispatch::with(['equipable', 'origin', 'originCafe', 'staff', 'dispatcher', 'receiver', 'color'])
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

    public function markReceived(int $id, Request $request)
    {
        $dispatch = EquipmentDispatch::findOrFail($id);

        if ($dispatch->status !== 'active') {
            return back()->withErrors(['dispatch' => 'Este despacho no está activo.']);
        }

        if ($dispatch->received_at) {
            return back()->withErrors(['dispatch' => 'Este despacho ya fue confirmado como recibido.']);
        }

        $dispatch->update([
            'received_at'     => now(),
            'received_by'     => Auth::id(),
            'reception_notes' => $request->input('reception_notes'),
        ]);

        $isEpp = $dispatch->equipable_type === Epp::class;

        // Solo se repone el stock del almacén cuando el equipo regresa de un CAFÉ a una
        // Sede/Almacén: el descuento original ocurrió al despacharlo del almacén hacia el café,
        // así que al volver hay que deshacer ese descuento. Si el origen ya era una Sede/Almacén
        // (transferencia directa entre almacenes), el descuento hecho al generar la guía es
        // definitivo — el equipo se fue de ahí — y no debe reponerse al recepcionar, o el
        // traslado quedaría en cero neto (se descuenta y se vuelve a sumar, como si nunca se
        // hubiera movido). Solo aplica a computer/kitchen: Epp no tiene columna `quantity` propia
        // (su stock vive en InventoryStock) y en este flujo nunca parte de origin_cafe_id.
        if (!$isEpp && $dispatch->destination_type === 'headquarter' && $dispatch->origin_cafe_id) {
            $dispatch->equipable?->increment('quantity', $dispatch->quantity);
        }

        // Si el destino es un café o una unidad, se acredita al ledger de stock de ese lugar
        // (equipment_stocks) — es la única fuente de verdad de "cuánto hay ahí", independiente
        // de cualquier guía. Este ledger es específico de computer/kitchen.
        if (!$isEpp && in_array($dispatch->destination_type, ['cafe', 'unit'], true)) {
            $locationCol = $dispatch->destination_type === 'cafe' ? 'cafe_id' : 'unit_id';

            DB::transaction(function () use ($dispatch, $locationCol) {
                $stock = EquipmentStock::where('stockable_type', $dispatch->equipable_type)
                    ->where('stockable_id', $dispatch->equipable_id)
                    ->where($locationCol, $dispatch->destination_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $stock->increment('quantity', $dispatch->quantity);
                } else {
                    EquipmentStock::create([
                        'stockable_type' => $dispatch->equipable_type,
                        'stockable_id'   => $dispatch->equipable_id,
                        $locationCol     => $dispatch->destination_id,
                        'quantity'       => $dispatch->quantity,
                    ]);
                }
            });
        }

        // Para EPP el stock vive en InventoryStock, dimensionado por sede/café/unidad. Se
        // acredita en la columna de ubicación correspondiente al destino de la guía. "Mina" no
        // tiene columna en InventoryStock — no hay dónde acreditar, así que se omite sin error.
        if ($isEpp) {
            $locationCol = match ($dispatch->destination_type) {
                'headquarter' => 'headquarter_id',
                'cafe'        => 'cafe_id',
                'unit'        => 'unit_id',
                default       => null,
            };

            if ($locationCol) {
                $otherCols = array_diff(['headquarter_id', 'cafe_id', 'unit_id'], [$locationCol]);

                DB::transaction(function () use ($dispatch, $locationCol, $otherCols) {
                    $stock = InventoryStock::where([
                        'stockable_type' => $dispatch->equipable_type,
                        'stockable_id'   => $dispatch->equipable_id,
                        'size'           => $dispatch->size,
                        'color_id'       => $dispatch->color_id,
                        $locationCol     => $dispatch->destination_id,
                    ])->whereNull(array_values($otherCols))
                        ->lockForUpdate()
                        ->first();

                    if ($stock) {
                        $stock->increment('quantity', $dispatch->quantity);
                    } else {
                        InventoryStock::create([
                            'stockable_type' => $dispatch->equipable_type,
                            'stockable_id'   => $dispatch->equipable_id,
                            'size'           => $dispatch->size,
                            'color_id'       => $dispatch->color_id,
                            $locationCol     => $dispatch->destination_id,
                            'quantity'       => $dispatch->quantity,
                        ]);
                    }
                });
            }
        }

        return back()->with('success', "Despacho {$dispatch->dispatch_number} confirmado como recibido.");
    }

    public function markReturned(int $id)
    {
        $dispatch = EquipmentDispatch::with('equipable')->findOrFail($id);

        if ($dispatch->status === 'returned') {
            return back()->withErrors(['dispatch' => 'Este despacho ya fue retornado.']);
        }

        if ($dispatch->equipable_type === Epp::class) {
            // Epp no tiene columna `quantity` propia — repone el stock de InventoryStock en el
            // origen de la guía, deshaciendo el descuento hecho al generarla. El origen puede ser
            // una Sede (EquipmentDispatchController::store) o un Café (StoreController::sendDispatch,
            // reenvío entre cafés/a sede) — solo una de las dos columnas está seteada.
            $originCol = $dispatch->origin_cafe_id ? 'cafe_id' : 'headquarter_id';
            $originId  = $dispatch->origin_cafe_id ?? $dispatch->origin_headquarter_id;
            $otherCols = array_diff(['headquarter_id', 'cafe_id', 'unit_id'], [$originCol]);

            DB::transaction(function () use ($dispatch, $originCol, $originId, $otherCols) {
                $stock = InventoryStock::where([
                    'stockable_type' => $dispatch->equipable_type,
                    'stockable_id'   => $dispatch->equipable_id,
                    'size'           => $dispatch->size,
                    'color_id'       => $dispatch->color_id,
                    $originCol       => $originId,
                ])->whereNull(array_values($otherCols))->lockForUpdate()->first();

                if ($stock) {
                    $stock->increment('quantity', $dispatch->quantity);
                } else {
                    InventoryStock::create([
                        'stockable_type' => $dispatch->equipable_type,
                        'stockable_id'   => $dispatch->equipable_id,
                        'size'           => $dispatch->size,
                        'color_id'       => $dispatch->color_id,
                        $originCol       => $originId,
                        'quantity'       => $dispatch->quantity,
                    ]);
                }
            });
        } else {
            $dispatch->equipable?->increment('quantity', $dispatch->quantity);
        }

        $dispatch->update(['status' => 'returned', 'returned_at' => now()]);

        return back()->with('success', 'Equipo retornado al almacén correctamente.');
    }

    public function updateGuideNumber(Request $request)
    {
        $validated = $request->validate([
            'ids'          => 'required|array|min:1',
            'ids.*'        => 'integer|exists:equipment_dispatches,id',
            'guide_number' => 'required|string|max:100',
        ]);

        EquipmentDispatch::whereIn('id', $validated['ids'])->update(['guide_number' => $validated['guide_number']]);

        return back()->with('success', 'Número de guía actualizado correctamente.');
    }

    public function pdf(int $id)
    {
        $dispatch = EquipmentDispatch::with(['equipable', 'origin', 'originCafe', 'staff', 'dispatcher', 'color'])
            ->findOrFail($id);

        $data = $this->transform($dispatch);

        $pdf = Pdf::loadView('pdf.equipment_dispatch', ['dispatch' => $data]);

        return $pdf->setPaper('a4', 'portrait')
            ->stream("Despacho_{$data['dispatch_number']}.pdf");
    }

    public function guidePdf(string $guideNumber)
    {
        $dispatches = EquipmentDispatch::with(['equipable', 'origin', 'originCafe', 'staff', 'dispatcher', 'color'])
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

        $equipType = match (true) {
            str_contains($d->equipable_type, 'Computer') => 'computer',
            str_contains($d->equipable_type, 'Epp')      => 'epp',
            default                                       => 'kitchen',
        };

        return [
            'id'               => $d->id,
            'dispatch_number'  => $d->dispatch_number,
            'guide_number'     => $d->guide_number,
            'status'           => $d->status,
            'equipable_type'   => $equipType,
            'equipable_id'     => $d->equipable_id,
            'quantity'         => $d->quantity,
            'size'             => $d->size,
            'color_name'       => $d->color?->name,
            'equipment_name'   => $d->equipable?->name ?? '—',
            'equipment_brand'  => $d->equipable?->brand,
            'equipment_model'  => $d->equipable?->model,
            'equipment_code'   => $d->equipable?->code,
            'equipment_series' => $d->equipable?->series,
            'equipment_status' => $d->equipable?->status,
            'origin_id'        => $d->origin_headquarter_id ?? $d->origin_cafe_id,
            'origin_label'     => $d->origin_cafe_id ? 'Café / Comedor' : 'Sede / Almacén',
            'origin_name'      => $d->origin?->name ?? $d->originCafe?->name ?? '—',
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
