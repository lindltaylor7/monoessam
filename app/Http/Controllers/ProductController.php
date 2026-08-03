<?php

namespace App\Http\Controllers;

use App\Models\Mercantil;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $mercantiles = Mercantil::whereHas('unit', fn($q) => $q->where('mine_id', $user->mine_id))
            ->with('unit:id,name')
            ->get(['id', 'name', 'unit_id']);

        $mercantilIds = $mercantiles->pluck('id');

        $products = Product::whereIn('mercantil_id', $mercantilIds)
            ->with('mercantil:id,name')
            ->orderBy('mercantil_id')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

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
}
