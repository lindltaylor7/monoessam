<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * CheckRoutePermission autoriza mirando el primer segmento de la URL. Estos tests fijan
 * las tres propiedades que sostienen el diseño:
 *
 *  1. Un segmento sin fila propia en `permissions` ya no queda abierto, si está en
 *     SEGMENT_PERMISSION_ALIASES (antes era fail-open).
 *  2. La escritura se comprueba (antes solo se filtraban los GET).
 *  3. Los alias de escritura NO amplían la lectura.
 */
function crearPermiso(string $name, string $routeName): Permission
{
    return Permission::create([
        'name' => $name,
        'guard_name' => 'web',
        'route_name' => $routeName,
    ]);
}

beforeEach(function () {
    crearPermiso('Ordenes', 'orders');
    crearPermiso('Comensales', 'dinners');
    crearPermiso('POS', 'pos');
    crearPermiso('Ventas', 'sales');
    crearPermiso('Lugares', 'management');
});

test('purchase-orders is blocked for a user without the orders permission', function () {
    $this->actingAs(User::factory()->create())
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
    $this->actingAs(User::factory()->create())
        ->get('/orders')
        ->assertStatus(403);
});

test('an aliased segment with no permission row of its own is no longer open', function () {
    // `mines` no tiene fila en permissions; el alias lo ata a `management`.
    $this->actingAs(User::factory()->create())
        ->get('/mines/search/foo')
        ->assertStatus(403);
});

test('an aliased segment is reachable with any of its mapped permissions', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('Lugares');

    $this->actingAs($user)
        ->get('/mines/search/foo')
        ->assertOk();
});

test('writes are now checked, not just GET', function () {
    // Antes, cualquier autenticado podia escribir en cualquier segmento.
    $this->actingAs(User::factory()->create())
        ->post('/dinners/save', [
            'name' => 'Test',
            'dni' => '12345678',
        ])
        ->assertStatus(403);
});

test('a write alias lets a neighbouring module write', function () {
    // El POS registra comensales via dinners.quick-register sin tener el permiso Comensales.
    $user = User::factory()->create();
    $user->givePermissionTo('POS');

    $this->actingAs($user)
        ->post('/dinners/quick-register', [
            'name' => 'Comensal POS',
            'dni' => '87654321',
        ])
        ->assertStatus(201);
});

test('a write alias does NOT widen read access', function () {
    // Con permiso POS se puede escribir en dinners, pero no leer el padron completo.
    $user = User::factory()->create();
    $user->givePermissionTo('POS');

    $this->actingAs($user)
        ->get('/dinners')
        ->assertStatus(403);
});

test('the owning permission still grants both read and write', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('Comensales');

    $this->actingAs($user)->get('/dinners')->assertOk();

    $this->actingAs($user)
        ->post('/dinners/save', [
            'name' => 'Test',
            'dni' => '11223344',
        ])
        ->assertRedirect();
});

test('a segment with no permission row and no alias stays open', function () {
    // Fail-open heredado: se mantiene deliberadamente para no romper endpoints sueltos.
    $this->actingAs(User::factory()->create())
        ->get('/laboral')
        ->assertOk();
});
