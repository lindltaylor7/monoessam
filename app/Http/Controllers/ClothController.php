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
use App\Models\ClothInventory;

class ClothController extends Controller
{
    /**
     * Display the view for Staff Clothes (Staff vs Clothes Sizes).
     */
    public function index()
    {
        $staff = Staff::with(['role', 'staff_clothes.cloth', 'staff_clothes.color', 'photo', 'staffable.unit.mine'])
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

        return Inertia::render('clothes/Index', [
            'staff' => $staff,
            'roleClothes' => $roleClothes,
            'units' => Unit::with(['cafes', 'mine'])->get(),
            'colors' => Color::all()
        ]);
    }

    /**
     * Display the view for Managing Clothes & Profiles.
     */
    public function manage()
    {
        $roles = Role::all();
        $clothes = Cloth::with('roles')->get();
        $cafes = Cafe::all();

        return Inertia::render('clothes/Manage', [
            'roles' => $roles,
            'clothes' => $clothes,
            'cafes' => $cafes
        ]);
    }

    /**
     * Store a new Cloth.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:cloths,name',
        ]);

        Cloth::create($request->only('name'));

        return back()->with('success', 'Prenda creada exitosamente');
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
            'color_id' => 'nullable|exists:colors,id'
        ]);

        $entry = Staff_clothes::findOrFail($request->id);
        $oldStatus = $entry->status;
        $newStatus = $request->status;
        $newColorId = $request->color_id;
        $oldColorId = $entry->color_id;

        $staff = $entry->staff;
        $cafeId = $staff->cafe_id;

        // If color changed or status changed to Entregado
        if ($newStatus === 'Entregado' && ($oldStatus !== 'Entregado' || $newColorId != $oldColorId)) {
            // Subtract from new color inventory
            if ($newColorId) {
                $inventory = ClothInventory::firstOrCreate([
                    'cloth_id' => $entry->cloth_id,
                    'color_id' => $newColorId,
                    'cafe_id' => $cafeId
                ]);
                $inventory->quantity -= 1;
                $inventory->save();
            }

            // Add back to old color inventory if it was already Entregado
            if ($oldStatus === 'Entregado' && $oldColorId) {
                $oldInventory = ClothInventory::firstOrCreate([
                    'cloth_id' => $entry->cloth_id,
                    'color_id' => $oldColorId,
                    'cafe_id' => $cafeId
                ]);
                $oldInventory->quantity += 1;
                $oldInventory->save();
            }
        } elseif ($oldStatus === 'Entregado' && $newStatus !== 'Entregado') {
            // If it was delivered and now it's not, return to inventory
            if ($oldColorId) {
                $inventory = ClothInventory::firstOrCreate([
                    'cloth_id' => $entry->cloth_id,
                    'color_id' => $oldColorId,
                    'cafe_id' => $cafeId
                ]);
                $inventory->quantity += 1;
                $inventory->save();
            }
        }

        $entry->status = $newStatus;
        if ($newColorId) {
            $entry->color_id = $newColorId;
        }
        $entry->save();

        return back();
    }
}
