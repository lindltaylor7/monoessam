<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Ingredient;
use App\Models\Ingredient_city_provider;
use App\Models\Level;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        return Inertia::render('purchase-orders/Index', $this->listPayload());
    }

    /**
     * Listado legado bajo /orders. Renderiza la misma pantalla que /purchase-orders para no
     * mantener dos vistas del mismo historial: el módulo vive en 'purchase-orders' desde que
     * tiene permiso propio, y esta ruta queda solo por compatibilidad.
     */
    public function ordersIndex()
    {
        return Inertia::render('purchase-orders/Index', $this->listPayload());
    }

    private function listPayload(): array
    {
        return [
            'orders' => PurchaseOrder::with(['program.cafe.unit.mine', 'programs.cafe.unit.mine'])->latest()->get(),
            'levels' => Level::orderBy('name')->get(),
            'cities' => City::orderBy('name')->get(),
        ];
    }

    /**
     * El costo estimado se calcula con el proveedor marcado como activo en Proveedores. Ese
     * activo es por (insumo, ciudad) y no hay relación Mina/Unidad/Comedor -> Ciudad, así que la
     * ciudad se elige en la pantalla; por defecto, la que tiene más precios activos cargados.
     */
    public function show(Request $request, $id)
    {
        $order = PurchaseOrder::with([
            'program.cafe.unit.mine',
            'programs.cafe.unit.mine',
            'items' => fn ($q) => $q->with('ingredient')->orderBy('id'),
        ])->findOrFail($id);

        $cityId = $request->integer('city_id') ?: Ingredient_city_provider::where('is_active', true)
            ->selectRaw('city_id, count(*) as total')
            ->groupBy('city_id')
            ->orderByDesc('total')
            ->value('city_id');

        $activePrices = $cityId
            ? Ingredient_city_provider::with('provider:id,name')
                ->where('city_id', $cityId)
                ->where('is_active', true)
                ->whereIn('ingredient_id', $order->items->pluck('ingredient_id'))
                ->get()
                ->keyBy('ingredient_id')
            : collect();

        foreach ($order->items as $item) {
            $active = $activePrices->get($item->ingredient_id);
            $unitPrice = $active?->cost_price !== null ? (float) $active->cost_price : null;

            $item->setAttribute('active_unit_price', $unitPrice);
            $item->setAttribute('active_provider', $active?->provider?->name);
            $item->setAttribute('active_cost', $unitPrice !== null ? round($unitPrice * (float) $item->total_amount, 2) : null);
        }

        return Inertia::render('purchase-orders/Show', [
            'order' => $order,
            'cities' => City::orderBy('name')->get(['id', 'name']),
            'cityId' => $cityId,
        ]);
    }

    /**
     * Buscador de insumos para el panel "Agregar insumo" del detalle. Vive en este segmento
     * a propósito (ver routes/web.php): /ingredients/search exige el permiso 'ingredients'.
     */
    public function searchIngredients(Request $request)
    {
        $query = trim((string) $request->query('q', ''));

        $ingredients = Ingredient::query()
            ->when($query !== '', fn ($q) => $q->where('name', 'like', "%{$query}%")->orWhere('code', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'code', 'unit_medida']);

        return response()->json($ingredients);
    }

    /**
     * Agrega un insumo que no venía del quebrado. Si el insumo ya está en la orden se suma a
     * la línea existente en vez de duplicarla: el pedido se envía consolidado por producto.
     */
    public function storeItem(Request $request, $id)
    {
        $order = PurchaseOrder::findOrFail($id);

        $validated = $request->validate([
            'ingredient_id'  => 'required|exists:ingredients,id',
            'total_amount'   => 'required|numeric|min:0.0001',
            'unit'           => 'required|string|max:20',
            'estimated_cost' => 'nullable|numeric|min:0',
        ]);

        $existing = $order->items()->where('ingredient_id', $validated['ingredient_id'])->first();

        if ($existing) {
            $existing->update([
                'total_amount'   => round($existing->total_amount + $validated['total_amount'], 4),
                'estimated_cost' => $validated['estimated_cost'] ?? $existing->estimated_cost,
            ]);

            return back()->with('success', 'El insumo ya estaba en el pedido: se sumó la cantidad a la línea existente.');
        }

        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'ingredient_id'     => $validated['ingredient_id'],
            'total_amount'      => round($validated['total_amount'], 4),
            'unit'              => $validated['unit'],
            'estimated_cost'    => $validated['estimated_cost'] ?? null,
            'is_manual'         => true,
        ]);

        return back()->with('success', 'Insumo agregado al pedido.');
    }

    public function destroyItem($id, $itemId)
    {
        $order = PurchaseOrder::findOrFail($id);
        $order->items()->where('id', $itemId)->firstOrFail()->delete();

        return back()->with('success', 'Insumo quitado del pedido.');
    }

    public function destroy($id)
    {
        $order = PurchaseOrder::findOrFail($id);
        $order->delete();

        return back();
    }
}
