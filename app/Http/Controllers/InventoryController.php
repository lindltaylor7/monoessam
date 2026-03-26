<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

use App\Models\Cloth;
use App\Models\Color;
use App\Models\Cafe;
use App\Models\ClothInventory;
use App\Models\ComputerEquipment;
use App\Models\KitchenEquipment;
use App\Models\Ingredient;
use App\Models\InventoryStock;
use App\Models\Headquarter;
use App\Models\Business;
use App\Models\Provider;
use App\Models\ClothInvoice;
use App\Models\ClothInvoiceItem;
use App\Models\ClothProvider;
use App\Models\Epp;
use App\Models\EppSize;
use App\Models\Size;
use App\Models\City;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\Unit;
use App\Models\Staff;
use App\Models\Staff_clothes;
use App\Models\CategoryEpp;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    public function index()
    {
        $colors = Color::all();
        $cafes = Cafe::with(['unit', 'unit.mine'])->get();
        $headquarters = Headquarter::with('business')->get();
        $businesses = Business::all();
        $providers = Provider::all();
        $clothes = Cloth::all();
        $epps = Epp::all();
        $units = Unit::with('mine')->get();

        // New polymorphic stocks
        $stocks = InventoryStock::with(['stockable', 'cafe', 'headquarter', 'unit.mine', 'color'])->get();

        $transfers = InventoryTransfer::with(['staff', 'unit.mine', 'items.stockable'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('inventory/Index', [
            'colors' => $colors,
            'cafes' => $cafes,
            'headquarters' => $headquarters,
            'stocks' => $stocks,
            'businesses' => $businesses,
            'providers' => $providers,
            'clothes' => $clothes,
            'epps' => $epps,
            'units' => $units,
            'transfers' => $transfers
        ]);
    }

    public function searchItems(Request $request)
    {
        $type = $request->input('type', 'cloth');
        $query = $request->input('query', '');

        $modelMap = [
            'cloth' => Cloth::class,
            'computer' => ComputerEquipment::class,
            'kitchen' => KitchenEquipment::class,
            'ingredient' => Ingredient::class,
            'epp' => Epp::class,
        ];

        $modelClass = $modelMap[$type] ?? Cloth::class;

        $q = $modelClass::query();

        if ($type === 'computer' || $type === 'kitchen') {
            $q->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('brand', 'like', "%{$query}%")
                    ->orWhere('model', 'like', "%{$query}%")
                    ->orWhere('series', 'like', "%{$query}%")
                    ->orWhere('code', 'like', "%{$query}%");
            });
        } else {
            $q->where('name', 'like', "%{$query}%");
        }

        if ($type === 'epp') {
            $q->with('sizes');
        }

        $items = $q->get();

        // Format label for dropdown
        $items->transform(function ($item) use ($type) {
            $label = $item->name;
            if (($type === 'computer' || $type === 'kitchen') && !$label) {
                $label = trim("{$item->brand} {$item->model}");
            } elseif ($type === 'computer' || $type === 'kitchen') {
                $label = "{$item->name} - {$item->brand} {$item->model}";
            }

            $stockSum = 0;
            $stockDetails = [];
            $stockOptions = [];
            $modelClassMap = [
                'cloth' => Cloth::class,
                'epp' => Epp::class,
                'computer' => ComputerEquipment::class,
                'kitchen' => KitchenEquipment::class,
                'ingredient' => Ingredient::class,
            ];
            $modelType = $modelClassMap[$type] ?? null;

            if ($modelType) {
                $stocks = InventoryStock::with('color')
                    ->where('stockable_id', $item->id)
                    ->where('stockable_type', $modelType)
                    ->whereNull('unit_id')
                    ->whereNull('cafe_id')
                    ->get();
                $stockSum = $stocks->sum('quantity');
                
                foreach($stocks as $s) {
                    if ($s->quantity > 0) {
                        $sizeVal = ($s->size && $s->size !== 'null') ? $s->size : 'Estándar';
                        $colorName = $s->color ? $s->color->name : 'N/A';
                        $colorHex = $s->color ? $s->color->hex_code : '#ccc';
                        
                        $stockDetails[] = "{$sizeVal} ({$colorName}): {$s->quantity}";
                        
                        $stockOptions[] = [
                            'label' => "{$sizeVal} - {$colorName}",
                            'value' => $sizeVal,
                            'color_id' => $s->color_id,
                            'color_name' => $colorName,
                            'color_hex' => $colorHex,
                            'quantity' => $s->quantity
                        ];
                    }
                }
            }

            return [
                'id' => $item->id,
                'name' => $label ?: 'Sin Nombre',
                'stock' => $stockSum,
                'stock_details' => $stockDetails,
                'stock_options' => $stockOptions
            ];
        });

        return response()->json($items);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'stockable_id' => 'required|integer',
            'stockable_type' => 'required|string',
            'headquarter_id' => 'nullable|exists:headquarters,id',
            'cafe_id' => 'nullable|exists:cafes,id',
            'quantity' => 'required|numeric|min:0',
            'action' => 'required|in:set,add'
        ]);

        $modelMap = [
            'cloth' => Cloth::class,
            'computer' => ComputerEquipment::class,
            'kitchen' => KitchenEquipment::class,
            'ingredient' => Ingredient::class,
            'epp' => Epp::class,
        ];

        $modelClass = $modelMap[$validated['stockable_type']] ?? $validated['stockable_type'];

        $stock = InventoryStock::firstOrNew([
            'stockable_id' => $validated['stockable_id'],
            'stockable_type' => $modelClass,
            'headquarter_id' => $validated['headquarter_id'] ?? null,
            'cafe_id' => $validated['cafe_id'] ?? null,
            'size' => $request->input('size'),
            'color_id' => $request->input('color_id'),
        ]);

        if ($validated['action'] === 'add') {
            $stock->quantity += $validated['quantity'];
        } else {
            $stock->quantity = $validated['quantity'];
        }

        $stock->save();

        return back()->with('success', 'Inventario actualizado');
    }

    public function storeColor(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:colors,name',
            'hex_code' => 'nullable|string'
        ]);

        Color::create($request->only('name', 'hex_code'));

        return back()->with('success', 'Color creado');
    }

    public function storeItem(Request $request)
    {
        $type = $request->input('type');

        if ($type === 'computer') {
            $validated = $request->validate([
                'name' => 'nullable|string',
                'description' => 'nullable|string',
                'brand' => 'nullable|string',
                'model' => 'nullable|string',
                'presentation' => 'nullable|string',
                'color' => 'nullable|string',
            ]);
            ComputerEquipment::create($validated);
        } elseif ($type === 'kitchen') {
            $validated = $request->validate([
                'name' => 'required|string',
                'brand' => 'required|string',
                'model' => 'required|string',
                'size' => 'required|string',
                'description' => 'nullable|string',
                'color' => 'nullable|string',
                'current_type' => 'nullable|string',
                'series' => 'nullable|string',
                'manual' => 'nullable|string',
                'code' => 'nullable|string',
                'status' => 'nullable|string',
            ]);
            KitchenEquipment::create($validated);
        }

        return back()->with('success', 'Equipo registrado correctamente');
    }
    public function storeClothInvoice(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'headquarter_id' => 'nullable|exists:headquarters,id',
            'cloth_provider_id' => 'required|exists:cloth_providers,id',
            'invoice_number' => 'nullable|string',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'cafe_id' => 'nullable|exists:cafes,id',
            'items' => 'required|array|min:1',
            'items.*.cloth_id' => 'nullable|exists:cloths,id',
            'items.*.epp_id' => 'nullable|exists:epps,id',
            'items.*.color_id' => 'nullable|exists:colors,id',
            'items.*.size' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'invoice_image' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        $invoiceImagePath = null;
        if ($request->hasFile('invoice_image')) {
            $path = $request->file('invoice_image')->store('invoice_images', 'public');
            $invoiceImagePath = '/storage/' . $path;
        }

        DB::transaction(function () use ($validated, $invoiceImagePath) {
            $totalAmount = collect($validated['items'])->sum(function ($item) {
                return $item['quantity'] * $item['unit_price'];
            });

            $invoice = ClothInvoice::create([
                'business_id' => $validated['business_id'],
                'headquarter_id' => $validated['headquarter_id'] ?? null,
                'cloth_provider_id' => $validated['cloth_provider_id'],
                'invoice_number' => $validated['invoice_number'],
                'date' => $validated['date'],
                'notes' => $validated['notes'],
                'total_amount' => $totalAmount,
                'invoice_image' => $invoiceImagePath,
                'user_id' => Auth::id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                $total_price = $itemData['quantity'] * $itemData['unit_price'];

                $invoice->items()->create([
                    'cloth_id' => $itemData['cloth_id'] ?? null,
                    'epp_id' => $itemData['epp_id'] ?? null,
                    'color_id' => $itemData['color_id'],
                    'size' => $itemData['size'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $total_price,
                ]);

                if ($itemData['cloth_id']) {
                    // Update ClothInventory
                    if ($validated['cafe_id']) {
                        $inventory = ClothInventory::firstOrCreate([
                            'cloth_id' => $itemData['cloth_id'],
                            'color_id' => $itemData['color_id'],
                            'cafe_id' => $validated['cafe_id']
                        ]);
                        $inventory->quantity += $itemData['quantity'];
                        $inventory->save();
                    }

                    // Update polymorphic InventoryStock
                    $stock = InventoryStock::firstOrNew([
                        'stockable_id' => $itemData['cloth_id'],
                        'stockable_type' => Cloth::class,
                        'cafe_id' => $validated['cafe_id'] ?? null,
                        'headquarter_id' => $validated['headquarter_id'] ?? null,
                        'size' => $itemData['size'] ?? null,
                        'color_id' => $itemData['color_id'] ?? null,
                    ]);
                    $stock->quantity += $itemData['quantity'];
                    $stock->save();
                } elseif ($itemData['epp_id']) {
                    // Update polymorphic InventoryStock for EPP
                    $stock = InventoryStock::firstOrNew([
                        'stockable_id' => $itemData['epp_id'],
                        'stockable_type' => Epp::class,
                        'cafe_id' => $validated['cafe_id'] ?? null,
                        'headquarter_id' => $validated['headquarter_id'] ?? null,
                        'size' => $itemData['size'] ?? null,
                        'color_id' => $itemData['color_id'] ?? null,
                    ]);
                    $stock->quantity += $itemData['quantity'];
                    $stock->save();
                }
            }
        });

        return back()->with('success', 'Factura de stock ingresada correctamente');
    }

    public function updateInvoiceImage(Request $request, $id)
    {
        $validated = $request->validate([
            'invoice_image' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        $invoice = ClothInvoice::findOrFail($id);

        if ($request->hasFile('invoice_image')) {
            $path = $request->file('invoice_image')->store('invoice_images', 'public');
            $invoice->update(['invoice_image' => '/storage/' . $path]);
        }

        return back()->with('success', 'Imagen de factura actualizada correctamente');
    }

    public function invoicesIndex()
    {
        $invoices = ClothInvoice::with(['business', 'headquarter', 'provider', 'items.cloth', 'items.epp', 'items.color', 'user'])->latest()->get();
        $clothProviders = ClothProvider::with(['epps', 'clothes'])->get();

        return Inertia::render('inventory/Invoices/Index', [
            'invoices' => $invoices,
            'clothProviders' => $clothProviders,
            'businesses' => Business::all(),
            'headquarters' => Headquarter::with('business')->get(),
            'cafes' => Cafe::with('unit')->get(),
            'clothes' => Cloth::all(),
            'colors' => Color::all(),
            'epps' => Epp::with(['sizes', 'cityProviders.city', 'cityProviders.provider', 'availableSizes', 'category'])->get(),
            'cities' => City::all(),
            'all_sizes' => Size::all(),
            'epp_categories' => CategoryEpp::all(),
        ]);
    }

    public function storeProvider(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
        ]);

        ClothProvider::create($validated);

        return back()->with('success', 'Proveedor registrado correctamente');
    }

    public function updateProvider(Request $request, $id)
    {
        $provider = ClothProvider::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
        ]);

        $provider->update($validated);

        return back()->with('success', 'Proveedor actualizado correctamente');
    }

    public function destroyProvider($id)
    {
        $provider = ClothProvider::findOrFail($id);
        $provider->delete();

        return back()->with('success', 'Proveedor eliminado correctamente');
    }

    public function storeEpp(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_epp_id' => 'nullable|exists:category_epps,id',
            'size_ids' => 'nullable|array',
            'size_ids.*' => 'exists:sizes,id',
        ]);

        $epp = Epp::create([
            'name' => $validated['name'],
            'category_epp_id' => $validated['category_epp_id'] ?? null,
        ]);

        if (!empty($validated['size_ids'])) {
            $epp->availableSizes()->sync($validated['size_ids']);
        }

        return back()->with('success', 'EPP registrado correctamente');
    }

    public function assignEppPrice(Request $request)
    {
        $validated = $request->validate([
            'epp_id' => 'required|exists:epps,id',
            'cloth_provider_id' => 'required|exists:cloth_providers,id',
            'city_id' => 'required|exists:cities,id',
            'cost_price' => 'required|numeric|min:0',
        ]);

        \App\Models\Epp_city_provider::updateOrCreate(
            [
                'epp_id' => $validated['epp_id'],
                'cloth_provider_id' => $validated['cloth_provider_id'],
                'city_id' => $validated['city_id'],
            ],
            [
                'cost_price' => $validated['cost_price'],
            ]
        );

        return back()->with('success', 'Precio de EPP asignado correctamente');
    }

    public function syncProviderEpps(Request $request, $id)
    {
        $provider = ClothProvider::findOrFail($id);

        $validated = $request->validate([
            'epp_ids' => 'nullable|array',
            'epp_ids.*' => 'exists:epps,id',
            'cloth_ids' => 'nullable|array',
            'cloth_ids.*' => 'exists:cloths,id'
        ]);

        $provider->epps()->sync($validated['epp_ids'] ?? []);
        $provider->clothes()->sync($validated['cloth_ids'] ?? []);

        return back()->with('success', 'Elementos asignados correctamente');
    }

    public function storeEppSize(Request $request)
    {
        $validated = $request->validate([
            'epp_id' => 'required|exists:epps,id',
            'size' => 'required|string|max:255',
        ]);

        EppSize::create($validated);

        return back()->with('success', 'Talla registrada correctamente');
    }

    public function destroyEppSize($id)
    {
        $size = EppSize::findOrFail($id);
        $size->delete();

        return back()->with('success', 'Talla eliminada correctamente');
    }
    public function storeCategoryEpp(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:category_epps,name',
        ]);

        CategoryEpp::create($validated);

        return back()->with('success', 'Categoría de EPP registrada correctamente');
    }

    public function getStockSizes($id)
    {
        $stockItem = InventoryStock::findOrFail($id);

        $stocks = InventoryStock::with(['color', 'headquarter', 'cafe'])
            ->where('stockable_id', $stockItem->stockable_id)
            ->where('stockable_type', $stockItem->stockable_type)
            ->get();

        return response()->json($stocks);
    }

    public function unitsStockIndex()
    {
        $units = Unit::with('mine')->get();
        $epps = Epp::with('category')->get();
        $colors = Color::all();
        $stocks = InventoryStock::with(['stockable', 'unit.mine', 'color'])
            ->whereNotNull('unit_id')
            ->where('stockable_type', Epp::class) // specifically for EPPs
            ->get();

        $transfers = InventoryTransfer::with(['staff', 'unit.mine', 'items.stockable'])
            ->whereNotNull('unit_id')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $staffHistories = \App\Models\StaffClothesHistory::with([
            'staff.staffable' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\Cafe::class => ['unit.mine']
                ]);
            },
            'user'
        ])
            ->whereHas('staff', function ($q) {
                $q->whereHasMorph('staffable', [\App\Models\Cafe::class], function ($sq) {
                    $sq->whereNotNull('unit_id');
                });
            })
            ->latest()
            ->get();

        return Inertia::render('inventory-unit/Index', [
            'units' => $units,
            'epps' => $epps,
            'colors' => $colors,
            'stocks' => $stocks,
            'transfers' => $transfers,
            'staffHistories' => $staffHistories,
        ]);
    }

    public function storeTransfer(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'staff_id' => 'nullable|exists:staff,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.stockable_id' => 'required|integer',
            'items.*.stockable_type' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.size' => 'nullable|string',
            'items.*.color_id' => 'nullable|exists:colors,id',
        ]);

        DB::transaction(function () use ($validated) {
            $transfer = InventoryTransfer::create([
                'unit_id' => $validated['unit_id'],
                'staff_id' => $validated['staff_id'],
                'notes' => $validated['notes'],
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            foreach ($validated['items'] as $itemData) {
                // Determine stockable type class
                $type = $itemData['stockable_type'] === 'epp' ? Epp::class : Cloth::class;

                // Determine size logic (Estándar -> null)
                $querySize = ($itemData['size'] === 'Estándar' || $itemData['size'] === '') ? null : ($itemData['size'] ?? null);

                // 1. Subtract from Principal (assuming principal is null unit/cafe)
                // We'll grab the first available stock record that has enough quantity
                $principalStockQuery = InventoryStock::where([
                    'stockable_id' => $itemData['stockable_id'],
                    'stockable_type' => $type,
                    'unit_id' => null,
                    'cafe_id' => null,
                    'size' => $querySize,
                    'color_id' => $itemData['color_id'] ?? null,
                ]);

                // First try to find one with exact quantity or more
                $principalStock = (clone $principalStockQuery)->where('quantity', '>=', $itemData['quantity'])->first();

                // If not found, try getting any that has some quantity (to throw a more accurate error later, though might be insufficient)
                if (!$principalStock) {
                    $principalStock = $principalStockQuery->first();
                }

                if (!$principalStock || $principalStock->quantity < $itemData['quantity']) {
                    $itemName = $type === Epp::class ? Epp::find($itemData['stockable_id'])->name : Cloth::find($itemData['stockable_id'])->name;
                    $colorName = $itemData['color_id'] ? Color::find($itemData['color_id'])->name : 'N/A';
                    throw ValidationException::withMessages([
                        'items' => "Stock insuficiente en Principal para: {$itemName} (Talla: " . ($querySize ?? 'N/A') . ", Color: {$colorName}). Disponible: " . ($principalStock ? $principalStock->quantity : 0)
                    ]);
                }

                $principalStock->decrement('quantity', $itemData['quantity']);

                // 2. Add to Unit Stock
                $unitStock = InventoryStock::firstOrCreate([
                    'stockable_id' => $itemData['stockable_id'],
                    'stockable_type' => $type,
                    'unit_id' => $validated['unit_id'],
                    'size' => $querySize,
                    'color_id' => $itemData['color_id'] ?? null,
                ], ['quantity' => 0]);

                $unitStock->increment('quantity', $itemData['quantity']);

                // 3. Create transfer item
                $transfer->items()->create([
                    'stockable_id' => $itemData['stockable_id'],
                    'stockable_type' => $type,
                    'quantity' => $itemData['quantity'],
                    'size' => $querySize,
                    'color_id' => $itemData['color_id'] ?? null,
                ]);

                // 4. If staff_id is provided, record in staff_clothes if it's cloth
                // (optional logic based on user's business needs)
            }
        });

        return back()->with('success', 'Envío registrado correctamente');
    }

    public function returnToPrincipal(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'items' => 'required|array|min:1',
            'items.*.stockable_id' => 'required|integer',
            'items.*.stockable_type' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.size' => 'nullable|string',
            'items.*.color_id' => 'nullable|exists:colors,id',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $itemData) {
                $type = $itemData['stockable_type']; 
                if ($type === 'App\Models\Epp' || $type === 'Epp') $type = Epp::class;
                if ($type === 'App\Models\Cloth' || $type === 'Cloth') $type = Cloth::class;

                // 1. Subtract from Unit
                $unitStock = InventoryStock::where([
                    'stockable_id' => $itemData['stockable_id'],
                    'stockable_type' => $type,
                    'unit_id' => $validated['unit_id'],
                    'size' => $itemData['size'] ?? null,
                    'color_id' => $itemData['color_id'] ?? null,
                ])->first();

                if (!$unitStock || $unitStock->quantity < $itemData['quantity']) {
                    throw new \Exception("Stock insuficiente en Unidad");
                }

                $unitStock->decrement('quantity', $itemData['quantity']);

                // 2. Add to Principal
                $principalStock = InventoryStock::firstOrCreate([
                    'stockable_id' => $itemData['stockable_id'],
                    'stockable_type' => $type,
                    'unit_id' => null,
                    'cafe_id' => null,
                    'headquarter_id' => $unitStock->headquarter_id ?? null,
                    'size' => $itemData['size'] ?? null,
                    'color_id' => $itemData['color_id'] ?? null,
                ], ['quantity' => 0]);

                $principalStock->increment('quantity', $itemData['quantity']);
            }
        });
    }

    public function assignStaffClothes(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'items' => 'required|array|min:1',
            'items.*.epp_id' => 'required|exists:epps,id',
            'items.*.epp_name' => 'nullable|string',
            'items.*.color_id' => 'required|exists:colors,id',
            'items.*.size' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.headquarter_id' => 'nullable|exists:headquarters,id',
            'items.*.id' => 'nullable|exists:staff_clothes,id',
            'reason' => 'nullable|string',
            'create_history' => 'nullable|boolean',
        ]);

        $staff = Staff::findOrFail($validated['staff_id']);

        try {
            DB::transaction(function () use ($validated, $staff) {
                foreach ($validated['items'] as $itemData) {
                    // Logic for EPP stock decrement/increment
                    $stockQuery = InventoryStock::where([
                        'stockable_id' => $itemData['epp_id'],
                        'stockable_type' => Epp::class,
                        'size' => $itemData['size'],
                        'color_id' => $itemData['color_id'],
                    ]);

                    if (!empty($itemData['headquarter_id'])) {
                        $stockQuery->where('headquarter_id', $itemData['headquarter_id']);
                    } else {
                        $stockQuery->where('cafe_id', $staff->cafe_id);
                    }

                    $stock = $stockQuery->first();

                    $isReplacement = ($validated['reason'] ?? '') === 'Reposición';

                    if (!$isReplacement) {
                        if (!$stock || $stock->quantity < $itemData['quantity']) {
                            $epp = Epp::find($itemData['epp_id']);
                            $colorName = Color::find($itemData['color_id'])?->name ?: 'N/A';
                            $locationName = !empty($itemData['headquarter_id'])
                                ? (Headquarter::find($itemData['headquarter_id'])?->name ?: 'la sede seleccionada')
                                : ($staff->cafe?->name ?: 'el punto de venta');
                            throw new \Exception("Stock insuficiente de '{$epp->name}' (Talla: {$itemData['size']}, Color: {$colorName}) en {$locationName}. Disponible: " . ($stock?->quantity ?: 0));
                        }

                        $stock->decrement('quantity', $itemData['quantity']);
                    } else {
                        if ($stock) {
                            $stock->increment('quantity', $itemData['quantity']);
                        } else {
                            InventoryStock::create([
                                'stockable_id' => $itemData['epp_id'],
                                'stockable_type' => Epp::class,
                                'size' => $itemData['size'],
                                'color_id' => $itemData['color_id'],
                                'quantity' => $itemData['quantity'],
                                'headquarter_id' => $itemData['headquarter_id'] ?? null,
                                'cafe_id' => empty($itemData['headquarter_id']) ? $staff->cafe_id : null,
                            ]);
                        }
                    }

                    if (!empty($itemData['id'])) {
                        Staff_clothes::find($itemData['id'])->update([
                            'status' => 'Entregado',
                            'color_id' => $itemData['color_id'],
                            'clothing_size' => $itemData['size'],
                            'quantity' => $itemData['quantity'],
                        ]);
                    } else {
                        $staffCloth = Staff_clothes::where([
                            'staff_id' => $staff->id,
                            'epp_id' => $itemData['epp_id'],
                            'status' => $itemData['status'] ?? 'Entregado',
                            'color_id' => $itemData['color_id'],
                            'clothing_size' => $itemData['size']
                        ])->first();

                        if ($staffCloth) {
                            $staffCloth->increment('quantity', $itemData['quantity']);
                        } else {
                            Staff_clothes::create([
                                'staff_id' => $staff->id,
                                'epp_id' => $itemData['epp_id'],
                                'color_id' => $itemData['color_id'],
                                'clothing_size' => $itemData['size'],
                                'status' => $itemData['status'] ?? 'Entregado',
                                'quantity' => $itemData['quantity'],
                            ]);
                        }
                    }

                    // Subtract from ClothInvoiceItems
                    for ($i = 0; $i < $itemData['quantity']; $i++) {
                        if (!$isReplacement) {
                            $invoiceItem = \App\Models\ClothInvoiceItem::where('epp_id', $itemData['epp_id'])
                                ->where('color_id', $itemData['color_id'])
                                ->where('size', $itemData['size'])
                                ->where('quantity', '>', 0)
                                ->orderBy('created_at', 'asc')
                                ->first();
                            if ($invoiceItem) $invoiceItem->decrement('quantity');
                        } else {
                            $invoiceItem = \App\Models\ClothInvoiceItem::where('epp_id', $itemData['epp_id'])
                                ->where('color_id', $itemData['color_id'])
                                ->where('size', $itemData['size'])
                                ->orderBy('created_at', 'desc')
                                ->first();
                            if ($invoiceItem) $invoiceItem->increment('quantity');
                        }
                    }
                }

                if (!empty($validated['create_history'])) {
                    \App\Models\StaffClothesHistory::create([
                        'staff_id' => $staff->id,
                        'user_id' => Auth::id(),
                        'reason' => $validated['reason'] ?? 'Nuevo',
                        'assigned_at' => now(),
                        'items' => $validated['items']
                    ]);
                }
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'EPPs asignados correctamente');
    }

    public function getItemStock(Request $request, $id)
    {
        $type = $request->input('type', 'epp');
        $modelType = $type === 'cloth' ? \App\Models\Cloth::class : \App\Models\Epp::class;

        $stocks = InventoryStock::where([
            'stockable_id' => $id,
            'stockable_type' => $modelType
        ])->get(['headquarter_id', 'cafe_id', 'quantity', 'size', 'color_id']);

        return response()->json($stocks);
    }

    public function uploadHistoryEvidence(Request $request, $id)
    {
        try {
            $request->validate([
                'evidence_image' => 'required|file|image|max:10240',
            ]);

            $history = \App\Models\StaffClothesHistory::findOrFail($id);

            if ($request->hasFile('evidence_image')) {
                $path = $request->file('evidence_image')->store('evidence_images', 'public');
                $history->update(['evidence_image' => '/storage/' . $path]);
            }

            return back()->with('success', 'Evidencia subida correctamente');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al subir evidencia: ' . $e->getMessage());
        }
    }
}
