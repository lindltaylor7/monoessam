<?php

namespace App\Http\Controllers;

use App\Models\Mercantil;
use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProductController extends Controller
{
    /** Orden de severidad para quedarnos con el peor estado entre los lotes de un producto. */
    private const STATUS_SEVERITY = ['expired' => 2, 'expiring_soon' => 1, 'ok' => 0];

    public function index()
    {
        $user = Auth::user();

        $mercantiles = Mercantil::whereHas('unit', fn($q) => $q->where('mine_id', $user->mine_id))
            ->with('unit:id,name')
            ->get(['id', 'name', 'unit_id']);

        $mercantilIds = $mercantiles->pluck('id');

        $products = Product::whereIn('mercantil_id', $mercantilIds)
            ->with(['mercantil:id,name', 'batches'])
            ->orderBy('mercantil_id')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // Resume, por producto, el peor estado de vencimiento entre todos sus lotes — así el
        // frontend no tiene que reimplementar la lógica de "expired/expiring_soon/ok" del modelo.
        $products->each(function (Product $product) {
            $worst = $product->batches->reduce(function (?string $carry, ProductBatch $batch) {
                $status = $batch->expiration_status;
                if (!$status || $status === 'ok') {
                    return $carry;
                }
                if (!$carry || self::STATUS_SEVERITY[$status] > self::STATUS_SEVERITY[$carry]) {
                    return $status;
                }
                return $carry;
            }, null);

            $product->worst_batch_status = $worst;
        });

        return Inertia::render('products/Index', [
            'products'    => $products,
            'mercantiles' => $mercantiles,
        ]);
    }

    /**
     * IDs de mercantiles que pertenecen a la mina del usuario autenticado — mismo criterio que
     * index() usa para listar. Se reutiliza para verificar que store/update/destroy/updateStock
     * nunca toquen un mercantil (o un producto de otro mercantil) fuera de esa mina.
     */
    private function scopedMercantilIds()
    {
        $user = Auth::user();

        return Mercantil::whereHas('unit', fn($q) => $q->where('mine_id', $user->mine_id))->pluck('id');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'mercantil_id' => 'required|exists:mercantiles,id',
            'name'         => 'required|string|max:255',
            'marca'        => 'nullable|string|max:100',
            'description'  => 'nullable|string',
            'sku'          => 'nullable|string|max:100',
            'category'     => 'nullable|string|max:100',
            'price'        => 'required|numeric|min:0',
            'stock'        => 'nullable|integer|min:0',
            'is_active'    => 'boolean',
        ]);

        abort_unless($this->scopedMercantilIds()->contains((int) $data['mercantil_id']), 403);

        Product::create($data);

        return redirect()->back();
    }

    public function update(Request $request, int $id)
    {
        $product = Product::findOrFail($id);

        $scopedIds = $this->scopedMercantilIds();
        abort_unless($scopedIds->contains($product->mercantil_id), 403);

        $data = $request->validate([
            'mercantil_id' => 'required|exists:mercantiles,id',
            'name'         => 'required|string|max:255',
            'marca'        => 'nullable|string|max:100',
            'description'  => 'nullable|string',
            'sku'          => 'nullable|string|max:100',
            'category'     => 'nullable|string|max:100',
            'price'        => 'required|numeric|min:0',
            'stock'        => 'nullable|integer|min:0',
            'is_active'    => 'boolean',
        ]);

        abort_unless($scopedIds->contains((int) $data['mercantil_id']), 403);

        $product->update($data);

        return redirect()->back();
    }

    public function updateStock(Request $request, int $id)
    {
        $product = Product::findOrFail($id);

        abort_unless($this->scopedMercantilIds()->contains($product->mercantil_id), 403);

        $data = $request->validate([
            'delta' => 'required|integer',
        ]);

        $product->stock = max(0, $product->stock + $data['delta']);
        $product->save();

        return redirect()->back();
    }

    public function destroy(int $id)
    {
        $product = Product::findOrFail($id);

        abort_unless($this->scopedMercantilIds()->contains($product->mercantil_id), 403);

        $product->delete();

        return redirect()->back();
    }

    /**
     * Registra un lote nuevo para el producto (cantidad + fecha de vencimiento) y suma esa
     * cantidad al stock total del producto — mismo criterio que updateStock ya usa para mover
     * el contador agregado.
     */
    public function storeBatch(Request $request, int $productId)
    {
        $product = Product::findOrFail($productId);

        abort_unless($this->scopedMercantilIds()->contains($product->mercantil_id), 403);

        $data = $request->validate([
            'batch_code'      => 'nullable|string|max:100',
            'quantity'        => 'required|integer|min:1',
            'expiration_date' => 'nullable|date',
            'received_at'     => 'nullable|date',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $data['product_id'] = $product->id;
        $data['received_at'] = $data['received_at'] ?? now()->toDateString();

        ProductBatch::create($data);

        $product->increment('stock', (int) $data['quantity']);

        return redirect()->back();
    }

    /**
     * Elimina un lote y descuenta su cantidad del stock total del producto (sin bajar de 0 —
     * por si el stock ya se movió por otro lado, p. ej. una venta, desde que se cargó el lote).
     */
    public function destroyBatch(int $batchId)
    {
        $batch = ProductBatch::with('product')->findOrFail($batchId);

        abort_unless($this->scopedMercantilIds()->contains($batch->product->mercantil_id), 403);

        $batch->product->stock = max(0, $batch->product->stock - $batch->quantity);
        $batch->product->save();

        $batch->delete();

        return redirect()->back();
    }
}
