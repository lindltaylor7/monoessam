<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use Illuminate\Http\Request;

use App\Models\Cloth;
use App\Models\Staff;
use App\Models\Staff_clothes;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use App\Models\Unit;
use App\Models\Color;
use App\Models\Headquarter;
use App\Models\ClothInventory;

class ClothController extends Controller
{
    /**
     * Display the view for Staff Clothes (Staff vs Clothes Sizes).
     */
    public function index()
    {
        $staff = Staff::with(['role', 'staff_clothes.cloth', 'staff_clothes.epp.sizes', 'staff_clothes.color', 'photo', 'staffable.unit.mine'])
            ->where('status', 2)
            ->whereHasMorph('staffable', [Cafe::class])
            ->get();

        // Get all clothes with roles to map which role needs which clothes
        $clothes = Cloth::with('roles')->get();

        // Organize clothes by role and cafe for easier frontend consumption
        $roleClothes = [];
        foreach ($clothes as $cloth) {
            foreach ($cloth->roles as $role) {
                $roleId = $role->id;
                $cafeId = $role->pivot->cafe_id;

                if (!isset($roleClothes[$roleId])) {
                    $roleClothes[$roleId] = [];
                }

                $key = $cafeId ?: 'all';
                if (!isset($roleClothes[$roleId][$key])) {
                    $roleClothes[$roleId][$key] = [];
                }
                $roleClothes[$roleId][$key][] = $cloth;
            }
        }

        // GET EPP ASSIGNMENTS (roleEpps)
        $epps = \App\Models\Epp::with(['roles', 'sizes'])->get();
        $roleEpps = [];
        foreach ($epps as $epp) {
            foreach ($epp->roles as $role) {
                $roleId = $role->id;
                $cafeId = $role->pivot->cafe_id;

                if (!isset($roleEpps[$roleId])) {
                    $roleEpps[$roleId] = [];
                }

                $key = $cafeId ?: 'all';
                if (!isset($roleEpps[$roleId][$key])) {
                    $roleEpps[$roleId][$key] = [];
                }
                $roleEpps[$roleId][$key][] = $epp;
            }
        }

        return Inertia::render('clothes/Index', [
            'staff' => $staff,
            'roleClothes' => $roleClothes,
            'roleEpps' => $roleEpps,
            'units' => Unit::with(['cafes', 'mine'])->get(),
            'colors' => Color::all(),
            'headquarters' => Headquarter::with('business')->get(),
        ]);
    }

    /**
     * Display the view for Managing Clothes & Profiles.
     */
    public function manage()
    {
        $roles = Role::all();
        $epps = \App\Models\Epp::with('roles')->get();
        $cafes = Cafe::with(['roles', 'unit.mine'])->get();

        return Inertia::render('clothes/Manage', [
            'roles' => $roles,
            'epps' => $epps,
            'cafes' => $cafes
        ]);
    }

    /**
     * Store a new Cloth.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:cloths,name',
            'quantity' => 'nullable|integer|min:0',
        ]);

        Cloth::create($validated);

        return back()->with('success', 'Prenda creada exitosamente');
    }

    /**
     * Update Cloth quantity.
     */
    public function updateQuantity(Request $request, $id)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $cloth = Cloth::findOrFail($id);
        $cloth->update(['quantity' => $validated['quantity']]);

        return back();
    }

    /**
     * Delete a Cloth.
     */
    public function destroy($id)
    {
        $cloth = Cloth::findOrFail($id);
        $cloth->delete();

        return back()->with('success', 'Prenda eliminada exitosamente');
    }

    /**
     * Assign Roles to a Cloth (Pivot).
     */
    public function assignRole(Request $request)
    {
        $request->validate([
            'cloth_id' => 'required|exists:cloths,id',
            'role_id' => 'required|exists:roles,id',
            'cafe_id' => 'required|exists:cafes,id',
            'action' => 'required|in:attach,detach',
        ]);

        $cloth = Cloth::findOrFail($request->cloth_id);

        if ($request->action === 'attach') {
            $cloth->roles()->attach($request->role_id, ['cafe_id' => $request->cafe_id]);
        } else {
            $cloth->roles()
                ->wherePivot('cafe_id', $request->cafe_id)
                ->detach($request->role_id);
        }

        return back(); // Inertia handles reload
    }

    /**
     * Update Staff Size.
     */
    public function updateStaffSize(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'cloth_id' => 'required|exists:cloths,id',
            'clothing_size' => 'nullable|string',
        ]);

        $cloth = Cloth::findOrFail($validated['cloth_id']);

        Staff_clothes::updateOrCreate(
            [
                'staff_id' => $validated['staff_id'],
                'cloth_id' => $validated['cloth_id'],
            ],
            [
                'clothing_size' => $validated['clothing_size'],
                'clothe_name' => $cloth->name,
            ]
        );

        return back();
    }

    /**
     * Update Staff Cloth Status.
     */
    public function updateStatus(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:staff_clothes,id',
            'status' => 'required|string',
            'color_id' => 'nullable|exists:colors,id',
            'epp_id' => 'nullable|exists:epps,id',
            'clothing_size' => 'nullable|string',
            'quantity' => 'nullable|integer|min:1',
            'headquarter_id' => 'nullable|exists:headquarters,id'
        ]);

        $entry = Staff_clothes::findOrFail($request->id);
        
        if ($request->has('epp_id')) $entry->epp_id = $request->epp_id;
        if ($request->has('clothing_size')) $entry->clothing_size = $request->clothing_size;
        $oldStatus = $entry->status;
        $newStatus = $request->status;
        $newColorId = $request->color_id;
        $oldColorId = $entry->color_id;

        $staff = $entry->staff;
        $cafeId = $staff->cafe_id;

        if ($entry->cloth_id || $entry->epp_id) {
            $itemId = $entry->cloth_id ?: $entry->epp_id;
            $itemType = $entry->cloth_id ? \App\Models\Cloth::class : \App\Models\Epp::class;
            $itemColumn = $entry->cloth_id ? 'cloth_id' : 'epp_id';

            // Determine headquarter_id from staffable if it's an Area
            $headquarterId = null;
            if ($staff->staffable && get_class($staff->staffable) === \App\Models\Area::class) {
                $headquarterId = $staff->staffable->headquarter_id;
            }

            // If color changed or status changed to Entregado
            if ($newStatus === 'Entregado' && ($oldStatus !== 'Entregado' || $newColorId != $oldColorId)) {
                $qtyToDecrement = $request->input('quantity', 1);

                // Subtract from new color inventory
                if ($entry->cloth_id) {
                    $inventory = \App\Models\ClothInventory::firstOrCreate([
                        'cloth_id' => $entry->cloth_id,
                        'color_id' => $newColorId,
                        'cafe_id' => $cafeId
                    ]);
                    $inventory->decrement('quantity', $qtyToDecrement);
                }

                // Subtract from global polymorphic stock
                if ($oldStatus !== 'Entregado') {
                    // Use headquarter_id from request if provided, otherwise fallback to auto-detection
                    $finalHqId = $request->headquarter_id ?: $headquarterId;

                    $stockQuery = \App\Models\InventoryStock::where([
                        'stockable_id' => $itemId,
                        'stockable_type' => $itemType,
                        'size' => $entry->clothing_size,
                    ]);

                    if ($finalHqId) {
                        $stockQuery->where('headquarter_id', $finalHqId);
                    } else {
                        $stockQuery->where('cafe_id', $cafeId);
                    }

                    $stock = $stockQuery->first();
                    if (!$stock || $stock->quantity < $qtyToDecrement) {
                        $itemName = $entry->epp?->name ?: ($entry->cloth?->name ?: $entry->clothe_name);
                        $locationName = $finalHqId ? Headquarter::find($finalHqId)?->name : ($staff->cafe?->name ?: 'el punto de venta');
                        return back()->with('error', "Stock insuficiente de '{$itemName}' (Talla: {$entry->clothing_size}) en {$locationName}. Disponible: " . ($stock?->quantity ?: 0));
                    }
                    $stock->decrement('quantity', $qtyToDecrement);

                    // SUBTRACT FROM CLOTH_INVOICE_ITEMS (FIFO)
                    for ($i = 0; $i < $qtyToDecrement; $i++) {
                        $invoiceItem = \App\Models\ClothInvoiceItem::where($itemColumn, $itemId)
                            ->where('color_id', $newColorId)
                            ->where('size', $entry->clothing_size)
                            ->where('quantity', '>', 0)
                            ->orderBy('created_at', 'asc')
                            ->first();
                        if ($invoiceItem) $invoiceItem->decrement('quantity');
                    }
                }

                // Add back to old color inventory if it was already Entregado and color changed
                if ($oldStatus === 'Entregado' && $oldColorId != $newColorId) {
                    if ($entry->cloth_id) {
                        $oldInventory = \App\Models\ClothInventory::firstOrCreate([
                            'cloth_id' => $entry->cloth_id,
                            'color_id' => $oldColorId,
                            'cafe_id' => $cafeId
                        ]);
                        $oldInventory->increment('quantity');
                    }
                    
                    // Also add back to invoice item if color changed? (complex to find which one it came from)
                }
            } elseif ($oldStatus === 'Entregado' && $newStatus !== 'Entregado') {
                // If it was delivered and now it's not (e.g. "Devuelto"), return to inventory
                if ($entry->cloth_id) {
                    $inventory = \App\Models\ClothInventory::firstOrCreate([
                        'cloth_id' => $entry->cloth_id,
                        'color_id' => $oldColorId,
                        'cafe_id' => $cafeId
                    ]);
                    $inventory->increment('quantity');
                }

                // Add back to global polymorphic stock
                $stock = \App\Models\InventoryStock::where([
                    'stockable_id' => $itemId,
                    'stockable_type' => $itemType,
                    'cafe_id' => $cafeId,
                    'headquarter_id' => $headquarterId,
                ])->first();
                if ($stock) $stock->increment('quantity');

                // ADD BACK TO CLOTH_INVOICE_ITEMS
                $invoiceItem = \App\Models\ClothInvoiceItem::where($itemColumn, $itemId)
                    ->where('color_id', $oldColorId)
                    ->orderBy('created_at', 'desc')
                    ->first();
                if ($invoiceItem) $invoiceItem->increment('quantity');
            }
        }

        $entry->status = $newStatus;
        $entry->color_id = $newColorId;
        $entry->save();

        return back();
    }

    /**
     * Assign Roles to an EPP (Pivot).
     */
    public function assignEppRole(Request $request)
    {
        $request->validate([
            'epp_id' => 'required|exists:epps,id',
            'role_id' => 'required|exists:roles,id',
            'cafe_id' => 'required|exists:cafes,id',
            'action' => 'required|in:attach,detach',
        ]);

        $epp = \App\Models\Epp::findOrFail($request->epp_id);

        if ($request->action === 'attach') {
            $epp->roles()->attach($request->role_id, ['cafe_id' => $request->cafe_id]);
        } else {
            $epp->roles()
                ->wherePivot('cafe_id', $request->cafe_id)
                ->detach($request->role_id);
        }

        return back();
    }

    /**
     * Delete an EPP.
     */
    public function destroyEpp($id)
    {
        $epp = \App\Models\Epp::findOrFail($id);
        $epp->delete();

        return back()->with('success', 'EPP eliminado exitosamente');
    }
}
