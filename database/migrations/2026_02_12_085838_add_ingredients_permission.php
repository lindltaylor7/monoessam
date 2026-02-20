<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permission = \Spatie\Permission\Models\Permission::updateOrCreate(
            ['name' => 'ingredients.index'],
            [
                'guard_name' => 'web',
                'sidebar_name' => 'Insumos',
                'route_name' => 'ingredients',
                'icon_class' => 'Utensils'
            ]
        );

        $roles = \Spatie\Permission\Models\Role::whereIn('name', ['Administrador', 'DevMaster'])->get();
        foreach ($roles as $role) {
            $role->givePermissionTo($permission);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Spatie\Permission\Models\Permission::where('name', 'ingredients.index')->delete();
    }
};
