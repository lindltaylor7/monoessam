<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Órdenes de Compra pasa a ser un módulo con permiso propio. CheckRoutePermission filtra por
 * el primer segmento de la URL, así que necesita una fila en `permissions` con
 * route_name = 'purchase-orders': sin ella el segmento queda fail-open (cualquier usuario
 * autenticado entraría) y no aparece en el sidebar, que se arma a partir de los permisos del
 * usuario (AppSidebar.vue).
 *
 * Si en la BD ya existe el permiso legado con route_name = 'orders' (creado a mano desde la
 * pantalla de Permisos), se conserva: el alias del middleware sigue aceptándolo y los roles
 * que lo tenían reciben también el nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::updateOrCreate(
            ['name' => 'purchase-orders.index'],
            [
                'guard_name'   => 'web',
                'sidebar_name' => 'Órdenes de Compra',
                'route_name'   => 'purchase-orders',
                'icon_class'   => 'ShoppingCart',
            ]
        );

        $legacy = Permission::where('route_name', 'orders')->first();

        $roles = Role::whereIn('name', ['Administrador', 'DevMaster'])
            ->when($legacy, fn ($q) => $q->orWhereHas('permissions', fn ($p) => $p->where('permissions.id', $legacy->id)))
            ->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        Permission::where('name', 'purchase-orders.index')->delete();
    }
};
