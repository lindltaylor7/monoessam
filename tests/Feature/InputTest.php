<?php

use App\Models\Input;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * El segmento `inputs` está protegido por CheckRoutePermission — la migración
 * add_inputs_permission crea la fila `permissions.route_name = inputs`, así que
 * cada usuario de prueba necesita el permiso `inputs.index`.
 */
function makeInputUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo('inputs.index');

    return $user;
}

test('inputs page is displayed for authenticated users', function () {
    $user = makeInputUser();

    $this->actingAs($user)
        ->get(route('inputs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('inputs/Index')->has('inputs'));
});

test('can create an input', function () {
    $user = makeInputUser();

    $this->actingAs($user)
        ->post(route('inputs.store'), [
            'code' => '010000002',
            'name' => 'ACELGA X 1 KG.',
            'unit_of_measure' => 'KGM',
            'unit' => 1,
            'cost' => 2.5,
            'total' => 2.5,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('inputs', ['code' => '010000002', 'name' => 'ACELGA X 1 KG.']);
});

test('can update an input', function () {
    $user = makeInputUser();
    $input = Input::create(['code' => '010000003', 'name' => 'AJI', 'unit_of_measure' => 'KGM']);

    $this->actingAs($user)
        ->put(route('inputs.update', $input->id), [
            'code' => '010000003',
            'name' => 'AJI CHUNCHO BLANCO/LIMO',
            'unit_of_measure' => 'KGM',
            'unit' => 0,
            'cost' => 0,
            'total' => 0,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('inputs', ['id' => $input->id, 'name' => 'AJI CHUNCHO BLANCO/LIMO']);
});

test('can delete an input', function () {
    $user = makeInputUser();
    $input = Input::create(['name' => 'TEMPORAL']);

    $this->actingAs($user)
        ->delete(route('inputs.destroy', $input->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('inputs', ['id' => $input->id]);
});

test('code must be unique', function () {
    $user = makeInputUser();
    Input::create(['code' => 'DUP', 'name' => 'A']);

    $this->actingAs($user)
        ->post(route('inputs.store'), ['code' => 'DUP', 'name' => 'B'])
        ->assertSessionHasErrors('code');
});
