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

test('can import inputs from a spreadsheet, skipping the header row', function () {
    $user = makeInputUser();

    $csv = "\"\",\"\",u de medida,unidad,costo,total\n"
        . "010000002,ACELGA X 1 KG.,KGM,0,0,0\n"
        . "010000003,AJI CHUNCHO BLANCO/LIMO,KGM,1.5,2,3\n";

    $path = tempnam(sys_get_temp_dir(), 'inputs') . '.csv';
    file_put_contents($path, $csv);
    $file = new \Illuminate\Http\UploadedFile($path, 'inputs.csv', 'text/csv', null, true);

    $this->actingAs($user)
        ->post(route('inputs.import'), ['excel_file' => $file])
        ->assertRedirect();

    expect(Input::count())->toBe(2);
    $this->assertDatabaseHas('inputs', ['code' => '010000003', 'name' => 'AJI CHUNCHO BLANCO/LIMO', 'cost' => 2]);

    @unlink($path);
});

test('code must be unique', function () {
    $user = makeInputUser();
    Input::create(['code' => 'DUP', 'name' => 'A']);

    $this->actingAs($user)
        ->post(route('inputs.store'), ['code' => 'DUP', 'name' => 'B'])
        ->assertSessionHasErrors('code');
});
