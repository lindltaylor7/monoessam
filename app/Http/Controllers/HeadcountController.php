<?php

namespace App\Http\Controllers;

use App\Events\SessionEnded;
use App\Models\Cafe;
use App\Models\Mine;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class HeadcountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('headcount/Index', [
            'mines' => Mine::with(['units', 'services', 'units.cafes', 'units.cafes.users', 'units.cafes.guards', 'units.cafes.guards.roles', 'units.cafes.staffs', 'units.cafes.staffs.staff_files'])->get(),
            'roles' => Role::with(['permissions', 'users'])->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function blacklist(string $id)
    {
        $user = User::find($id);

        $user->update([
            'type' => 2
        ]);
    }

    public function banUser(string $id)
    {
        $user = User::find($id);

        if ($user) {
            $user->update([
                'type' => 3,
                'password' => Hash::make('lindltaylor7@gmail.com') // Reset password to a default value
            ]);
        }

        event(new SessionEnded($id));

        return to_route('users');
    }

    public function assignedUsers($cafeID)
    {
        $cafe = Cafe::with('guards', 'guards.roles', 'users')->find($cafeID);

        return $cafe;
    }
}
