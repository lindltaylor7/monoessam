<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Cloth;
use App\Models\Staff;
use App\Models\Staff_clothes;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class ClothController extends Controller
{
    /**
     * Display the view for Staff Clothes (Staff vs Clothes Sizes).
     */
    public function index()
    {
        $staff = Staff::with(['role', 'staff_clothes.cloth', 'photo'])
            ->paginate(15);

        // Get all clothes with roles to map which role needs which clothes
        $clothes = Cloth::with('roles')->get();

        // Organize clothes by role for easier frontend consumption
        $roleClothes = [];
        foreach ($clothes as $cloth) {
            foreach ($cloth->roles as $role) {
                if (!isset($roleClothes[$role->id])) {
                    $roleClothes[$role->id] = [];
                }
                $roleClothes[$role->id][] = $cloth;
            }
        }

        return Inertia::render('clothes/Index', [
            'staff' => $staff,
            'roleClothes' => $roleClothes
        ]);
    }

    /**
     * Display the view for Managing Clothes & Profiles.
     */
    public function manage()
    {
        $roles = Role::all();
        $clothes = Cloth::with('roles')->get();

        return Inertia::render('clothes/Manage', [
            'roles' => $roles,
            'clothes' => $clothes
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
            'action' => 'required|in:attach,detach',
        ]);

        $cloth = Cloth::findOrFail($request->cloth_id);

        if ($request->action === 'attach') {
            $cloth->roles()->syncWithoutDetaching([$request->role_id]);
        } else {
            $cloth->roles()->detach($request->role_id);
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
}
