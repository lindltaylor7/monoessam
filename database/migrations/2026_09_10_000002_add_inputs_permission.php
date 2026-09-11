<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = \Spatie\Permission\Models\Permission::updateOrCreate(
            ['name' => 'inputs.index'],
            [
                'guard_name'   => 'web',
                'sidebar_name' => 'Inputs',
                'route_name'   => 'inputs',
                'icon_class'   => 'Boxes',
            ]
        );

        $roles = \Spatie\Permission\Models\Role::whereIn('name', ['Administrador', 'DevMaster'])->get();
        foreach ($roles as $role) {
            $role->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        \Spatie\Permission\Models\Permission::where('name', 'inputs.index')->delete();
    }
};
