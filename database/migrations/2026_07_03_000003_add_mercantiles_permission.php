<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'name'          => 'Mercantiles',
            'guard_name'    => 'web',
            'sidebar_name'  => 'Mercantiles',
            'route_name'    => 'mercantiles',
            'icon_class'    => 'Store',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Grant it to whichever users already manage Productos/POS directly.
        $userIds = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->whereIn('permissions.route_name', ['products', 'pos'])
            ->where('model_has_permissions.model_type', \App\Models\User::class)
            ->pluck('model_has_permissions.model_id')
            ->unique();

        foreach ($userIds as $userId) {
            DB::table('model_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'model_type'    => \App\Models\User::class,
                'model_id'      => $userId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('route_name', 'mercantiles')->first();
        if ($permission) {
            DB::table('model_has_permissions')->where('permission_id', $permission->id)->delete();
            DB::table('permissions')->where('id', $permission->id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
