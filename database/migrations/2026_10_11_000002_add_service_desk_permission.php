<?php

use Illuminate\Database\Migrations\Migration;

/**
 * CheckRoutePermission filtra por el primer segmento de la URL, asi que el modulo necesita
 * una fila en `permissions` con route_name = 'service-desk'. Sin ella el segmento queda
 * fail-open (cualquier usuario autenticado entraria) y ademas no aparece en el sidebar,
 * que se arma a partir de los permisos del usuario (AppSidebar.vue).
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = \Spatie\Permission\Models\Permission::updateOrCreate(
            ['name' => 'service-desk.index'],
            [
                'guard_name'   => 'web',
                'sidebar_name' => 'Service Desk',
                'route_name'   => 'service-desk',
                'icon_class'   => 'Headset',
            ]
        );

        $roles = \Spatie\Permission\Models\Role::whereIn('name', ['Administrador', 'DevMaster'])->get();
        foreach ($roles as $role) {
            $role->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        \Spatie\Permission\Models\Permission::where('name', 'service-desk.index')->delete();
    }
};
