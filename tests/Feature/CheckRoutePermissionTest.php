<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * `purchase-orders` no tiene fila propia en `permissions`: se rige por el permiso
 * "Ordenes" (route_name `orders`) vía CheckRoutePermission::SEGMENT_PERMISSION_ALIASES.
 * Sin ese alias el segmento quedaba abierto a cualquier usuario autenticado.
 */
beforeEach(function () {
    Permission::create([
        'name' => 'Ordenes',
        'guard_name' => 'web',
        'route_name' => 'orders',
    ]);
});

test('purchase-orders is blocked for a user without the orders permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/purchase-orders')
        ->assertStatus(403);
});

test('purchase-orders is allowed for a user with the orders permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('Ordenes');

    $this->actingAs($user)
        ->get('/purchase-orders')
        ->assertOk();
});

test('orders itself is still gated by the same permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/orders')
        ->assertStatus(403);
});
