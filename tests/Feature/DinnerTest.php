<?php

use App\Models\Cafe;
use App\Models\Dinner;
use App\Models\Mine;
use App\Models\Unit;
use App\Models\User;
use App\Models\Subdealership;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dinners page is displayed for authenticated users', function () {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);
    $user->units()->attach($unit->id);

    $this->actingAs($user)
        ->get(route('dinners.index'))
        ->assertOk()
        ->assertInertia(
            fn($page) => $page
                ->component('dinners/Index')
                ->has('dinners')
                ->has('cafes')
        );
});

test('can create a dinner', function () {
    $mine = Mine::factory()->create();
    $user = User::factory()->create(['mine_id' => $mine->id]);
    $subdealership = Subdealership::factory()->create();

    // El alta de comensal vive en dinners.save; dinners.store es el registro de venta.
    $dinnerData = [
        'name' => 'John Doe',
        'dni' => '12345678',
        'phone' => '987654321',
        'subdealership_id' => $subdealership->id,
    ];

    $this->actingAs($user)
        ->post(route('dinners.save'), $dinnerData)
        ->assertRedirect(); // Back

    // mine_id no se envía: el controlador lo toma del usuario autenticado.
    $this->assertDatabaseHas('dinners', [
        'name' => 'John Doe',
        'dni' => '12345678',
        'mine_id' => $mine->id,
    ]);
});

test('can update a dinner', function () {
    $mine = Mine::factory()->create();
    $user = User::factory()->create(['mine_id' => $mine->id]);
    $dinner = Dinner::factory()->create();

    $updatedData = [
        'name' => 'Jane Doe',
        'dni' => $dinner->dni,
        'phone' => '111222333',
        'subdealership_id' => $dinner->subdealership_id,
    ];

    $this->actingAs($user)
        ->put(route('dinners.update', $dinner->id), $updatedData)
        ->assertRedirect();

    $this->assertDatabaseHas('dinners', [
        'id' => $dinner->id,
        'name' => 'Jane Doe',
        'phone' => '111222333',
    ]);
});

test('can delete a dinner', function () {
    $user = User::factory()->create();
    $dinner = Dinner::factory()->create();

    $this->actingAs($user)
        ->delete(route('dinners.destroy', $dinner->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('dinners', [
        'id' => $dinner->id,
    ]);
});

test('can search for a dinner', function () {
    $mine = Mine::factory()->create();
    $user = User::factory()->create(['mine_id' => $mine->id]);

    Dinner::factory()->create(['name' => 'UniqueName', 'mine_id' => $mine->id]);
    Dinner::factory()->create(['name' => 'OtherName', 'mine_id' => $mine->id]);

    // No existe una ruta dinners.search: el filtrado se hace con el parámetro
    // `search` del índice, que es lo que consume la pantalla.
    $this->actingAs($user)
        ->get(route('dinners.index', ['search' => 'Unique']))
        ->assertOk()
        ->assertInertia(
            fn($page) => $page
                ->component('dinners/Index')
                ->has('dinners.data', 1)
                ->where('dinners.data.0.name', 'UniqueName')
        );
});
