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

    public function store(Request $request)
    {
        $data = $request->validate([
            'mercantil_id' => 'required|exists:mercantiles,id',
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'products_sku_unique'          => 'nullable|string|max:100',
            'category'     => 'nullable|string|max:100',
            'price'        => 'required|numeric|min:0',
            'stock'        => 'nullable|integer|min:0',
            'is_active'    => 'boolean',
        ]);

        Product::create($data);

        return redirect()->back();
    }

    public function update(Request $request, int $id)
    {
        $product = Product::findOrFail($id);

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

        $product->update($data);

        return redirect()->back();
    }

    public function updateStock(Request $request, int $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'delta' => 'required|integer',
        ]);

        $product->stock = max(0, $product->stock + $data['delta']);
        $product->save();

        return redirect()->back();
    }

    public function destroy(int $id)
    {
        Product::findOrFail($id)->delete();

        return redirect()->back();
    }
}
