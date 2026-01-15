<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // 1. Obtener todas las tablas necesarias
        $permissions = Permission::all();
        $roles = Role::all();

        // 2. Obtener usuarios con sus relaciones
        $users = User::with('roles', 'roles.permissions', 'permissions')->get();

        // 3. Mapear la colección de usuarios para fusionar los permisos
        $users = $users->map(function ($user) {

            // Inicializar una colección para todos los permisos
            $allPermissions = collect();

            // 3a. Agregar permisos directos
            // Los permisos directos ya son una colección, simplemente los agregamos.
            $allPermissions = $allPermissions->merge($user->permissions);

            // 3b. Agregar permisos de roles
            // Iteramos sobre los roles del usuario para obtener sus permisos
            foreach ($user->roles as $role) {
                $allPermissions = $allPermissions->merge($role->permissions);
            }

            // 3c. Eliminar duplicados
            // Usamos unique() por el 'id' para evitar permisos duplicados 
            // (por si un permiso está asignado directamente y a través de un rol)
            $uniquePermissions = $allPermissions->unique('id')->values();

            // 3d. Agregar el nuevo atributo al objeto usuario
            $user->all_permissions = $uniquePermissions;

            // Opcional: Si no quieres enviar las relaciones anidadas originales (roles, permissions)
            // podrías ocultarlas o eliminarlas, pero las mantendremos por defecto.

            return $user;
        });

        // 4. Retornar la respuesta con la colección modificada
        return Inertia::render('permissions/Index', [
            'permissions' => $permissions,
            'roles' => $roles,
            'users' => $users
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
    public function store(Request $request)
    {
        $permission = Permission::create([
            'name' => $request->name,
            'guard_name' => 'web',
            'sidebar_name' => $request->sidebar_name,
            'route_name' => $request->route_name,
            'icon_class' => $request->icon_class
        ]);

        return to_route('permissions.index');
    }

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
        $permission = Permission::find($id);

        $permission->delete();

        return to_route('users');
    }

    public function rolePermissions(Request $request)
    {
        $role = Role::findOrFail($request->role_id);

        $permissions = $request->permissions ?? [];
        $selectedIds = array_map('intval', array_filter($permissions));

        $role->syncPermissions($selectedIds);

        return to_route('roles.index');
    }

    public function roleUser(Request $request)
    {
        $role = Role::findOrFail($request->role_id);

        $user = User::findOrFail($request->user_id);

        $user->syncRoles([$role->name]);

        return to_route('users');
    }

    public function userPermissions(Request $request)
    {
        $user = User::findOrFail($request->user_id);

        $permissions = $request->permissions ?? [];
        $selectedIds = array_map('intval', array_filter($permissions));

        $user->syncPermissions($selectedIds);

        return to_route('permissions.index');
    }
}
