<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
use App\Models\City;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

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

        // New polymorphic stocks - we might want to paginate this too if it grows large
        $stocks = InventoryStock::with(['stockable', 'cafe', 'headquarter'])->get();

        return Inertia::render('inventory/Index', [
            'colors' => $colors,
            'cafes' => $cafes,
            'headquarters' => $headquarters,
            'stocks' => $stocks,
            'businesses' => $businesses,
            'providers' => $providers,
            'clothes' => $clothes
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

        $items = $q->limit(10)->get();

        // Format label for dropdown
        $items->transform(function ($item) use ($type) {
            $label = $item->name;
            if (($type === 'computer' || $type === 'kitchen') && !$label) {
                $label = trim("{$item->brand} {$item->model}");
            } elseif ($type === 'computer' || $type === 'kitchen') {
                $label = "{$item->name} - {$item->brand} {$item->model}";
            }
            return [
                'id' => $item->id,
                'name' => $label ?: 'Sin Nombre'
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
        ];

        $modelClass = $modelMap[$validated['stockable_type']] ?? $validated['stockable_type'];

        $stock = InventoryStock::firstOrNew([
            'stockable_id' => $validated['stockable_id'],
            'stockable_type' => $modelClass,
            'headquarter_id' => $validated['headquarter_id'] ?? null,
            'cafe_id' => $validated['cafe_id'] ?? null,
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
            'cafe_id' => 'required|exists:cafes,id',
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
                    $inventory = ClothInventory::firstOrCreate([
                        'cloth_id' => $itemData['cloth_id'],
                        'color_id' => $itemData['color_id'],
                        'cafe_id' => $validated['cafe_id']
                    ]);
                    $inventory->quantity += $itemData['quantity'];
                    $inventory->save();

                    // Update polymorphic InventoryStock
                    $stock = InventoryStock::firstOrNew([
                        'stockable_id' => $itemData['cloth_id'],
                        'stockable_type' => Cloth::class,
                        'cafe_id' => $validated['cafe_id'],
                        'headquarter_id' => $validated['headquarter_id'],
                    ]);
                    $stock->quantity += $itemData['quantity'];
                    $stock->save();
                } elseif ($itemData['epp_id']) {
                    // Update polymorphic InventoryStock for EPP
                    $stock = InventoryStock::firstOrNew([
                        'stockable_id' => $itemData['epp_id'],
                        'stockable_type' => Epp::class,
                        'cafe_id' => $validated['cafe_id'],
                        'headquarter_id' => $validated['headquarter_id'],
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
        $invoices = ClothInvoice::with(['business', 'headquarter', 'provider', 'items.cloth', 'items.epp', 'items.color'])->latest()->get();
        $clothProviders = ClothProvider::with(['epps', 'clothes'])->get();

        return Inertia::render('inventory/Invoices/Index', [
            'invoices' => $invoices,
            'clothProviders' => $clothProviders,
            'businesses' => Business::all(),
            'headquarters' => Headquarter::with('business')->get(),
            'cafes' => Cafe::with('unit')->get(),
            'clothes' => Cloth::all(),
            'colors' => Color::all(),
            'epps' => Epp::with(['sizes.city', 'cityProviders.city', 'cityProviders.provider'])->get(),
            'cities' => City::all(),
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
        ]);

        Epp::create($validated);

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
            'city_id' => 'required|exists:cities,id',
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
}
