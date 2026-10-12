<?php

use App\Models\Cafe;
use App\Models\Ingredient;
use App\Models\Mine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\WeeklyProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * El quebrado solo consolida lo que estaba en la programación semanal. Compras necesita
 * poder sumar un insumo puntual a una orden ya generada, así que el detalle acepta líneas
 * agregadas a mano (is_manual) sobre el mismo módulo, con su propio permiso.
 */
function ordenDeCompra(): PurchaseOrder
{
    $mine = Mine::factory()->create();
    $unit = Unit::factory()->create(['mine_id' => $mine->id]);
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);

    $program = WeeklyProgram::create([
        'cafe_id'    => $cafe->id,
        'start_date' => '2026-02-02',
        'end_date'   => '2026-02-08',
        'user_id'    => User::factory()->create(['mine_id' => $mine->id])->id,
        'status'     => 'borrador',
    ]);

    return PurchaseOrder::create(['weekly_program_id' => $program->id, 'status' => 'pendiente']);
}

/** El permiso lo crea su migración; aquí solo se asigna. */
function usuarioDeCompras(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::where('route_name', 'purchase-orders')->firstOrFail());

    return $user;
}

test('el modulo es accesible con su propio permiso purchase-orders', function () {
    $this->actingAs(usuarioDeCompras())
        ->get(route('purchase_orders.index'))
        ->assertOk();
});

test('un usuario sin el permiso no entra al modulo', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('purchase_orders.index'))
        ->assertStatus(403);
});

test('se puede agregar un insumo que no venia del quebrado', function () {
    $order      = ordenDeCompra();
    $ingredient = Ingredient::create(['name' => 'Aceite vegetal', 'code' => 'ACE-01']);

    $this->actingAs(usuarioDeCompras())
        ->post(route('purchase_orders.items.store', $order->id), [
            'total_amount'  => 5.5,
            'ingredient_id' => $ingredient->id,
            'unit'          => 'Lt.',
        ])
        ->assertRedirect();

    $item = $order->items()->first();
    expect($item->ingredient_id)->toBe($ingredient->id);
    expect((float) $item->total_amount)->toBe(5.5);
    expect($item->is_manual)->toBeTrue();
});

test('agregar un insumo ya presente suma a la linea existente en vez de duplicarla', function () {
    $order      = ordenDeCompra();
    $ingredient = Ingredient::create(['name' => 'Arroz']);

    PurchaseOrderItem::create([
        'purchase_order_id' => $order->id,
        'ingredient_id'     => $ingredient->id,
        'total_amount'      => 12,
        'unit'              => 'Kg.',
    ]);

    $this->actingAs(usuarioDeCompras())
        ->post(route('purchase_orders.items.store', $order->id), [
            'total_amount'  => 3,
            'ingredient_id' => $ingredient->id,
            'unit'          => 'Kg.',
        ])
        ->assertRedirect();

    expect($order->items()->count())->toBe(1);
    expect((float) $order->items()->first()->total_amount)->toBe(15.0);
});

test('se puede quitar una linea del pedido', function () {
    $order = ordenDeCompra();
    $item  = PurchaseOrderItem::create([
        'purchase_order_id' => $order->id,
        'ingredient_id'     => Ingredient::create(['name' => 'Azúcar'])->id,
        'total_amount'      => 2,
        'unit'              => 'Kg.',
    ]);

    $this->actingAs(usuarioDeCompras())
        ->delete(route('purchase_orders.items.destroy', [$order->id, $item->id]))
        ->assertRedirect();

    expect($order->items()->count())->toBe(0);
});

test('una linea de otra orden no se puede borrar desde esta', function () {
    $order = ordenDeCompra();
    $otra  = ordenDeCompra();
    $item  = PurchaseOrderItem::create([
        'purchase_order_id' => $otra->id,
        'ingredient_id'     => Ingredient::create(['name' => 'Sal'])->id,
        'total_amount'      => 1,
        'unit'              => 'Kg.',
    ]);

    $this->actingAs(usuarioDeCompras())
        ->delete(route('purchase_orders.items.destroy', [$order->id, $item->id]))
        ->assertStatus(404);

    expect($otra->items()->count())->toBe(1);
});

test('el buscador de insumos del modulo responde sin el permiso de ingredientes', function () {
    Ingredient::create(['name' => 'Papa amarilla', 'code' => 'PAP-01']);
    Ingredient::create(['name' => 'Cebolla roja']);

    $response = $this->actingAs(usuarioDeCompras())
        ->getJson(route('purchase_orders.search-ingredients', ['q' => 'papa']))
        ->assertOk();

    expect($response->json())->toHaveCount(1);
    expect($response->json('0.name'))->toBe('Papa amarilla');
});
