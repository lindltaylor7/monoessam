<?php

use App\Models\Cafe;
use App\Models\MenuCycle;
use App\Models\Mine;
use App\Models\Serviceable;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;

function seedCycleSaveBase(): array
{
    $mine = Mine::factory()->create();
    $unit = Unit::factory()->create(['mine_id' => $mine->id]);
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);
    $user = User::factory()->create(['mine_id' => $mine->id]);

    $almuerzo = Serviceable::create(['serviceable_id' => $cafe->id, 'serviceable_type' => Cafe::class, 'service_id' => Service::create(['name' => 'ALMUERZO', 'code' => 'ALM', 'type' => 'comedor'])->id]);
    $cena     = Serviceable::create(['serviceable_id' => $cafe->id, 'serviceable_type' => Cafe::class, 'service_id' => Service::create(['name' => 'CENA', 'code' => 'CEN', 'type' => 'comedor'])->id]);

    return [$user, $almuerzo, $cena];
}

function cyclePayload(Serviceable $serviceable, string $name, ?int $id = null): array
{
    return [
        'id'             => $id,
        'serviceable_id' => $serviceable->id,
        'name'           => $name,
        'days'           => 7,
        'cycle_data'     => [['id' => 1, 'category' => 'FONDO', 'dishCategoryId' => 1, 'days' => []]],
    ];
}

it('devuelve el id del ciclo creado para que el siguiente guardado lo actualice', function () {
    [$user, $almuerzo] = seedCycleSaveBase();
    $this->actingAs($user);

    $id = $this->postJson('/cycles', cyclePayload($almuerzo, 'Ciclo 1'))->assertOk()->json('id');

    $this->postJson('/cycles', cyclePayload($almuerzo, 'Ciclo 1 corregido', $id))->assertOk()->assertJson(['id' => $id]);

    expect(MenuCycle::count())->toBe(1)
        ->and(MenuCycle::find($id)->name)->toBe('Ciclo 1 corregido');
});

it('no crea un ciclo nuevo con el mismo nombre en el mismo servicio', function () {
    [$user, $almuerzo] = seedCycleSaveBase();
    $existing = MenuCycle::create(['serviceable_id' => $almuerzo->id, 'name' => 'Ciclo 1', 'days' => 7, 'cycle_data' => []]);

    $this->actingAs($user)
        ->postJson('/cycles', cyclePayload($almuerzo, '  ciclo 1 '))
        ->assertStatus(409)
        ->assertJson(['existing_id' => $existing->id]);

    expect(MenuCycle::count())->toBe(1);
});

it('permite el mismo nombre en otro servicio', function () {
    [$user, $almuerzo, $cena] = seedCycleSaveBase();
    MenuCycle::create(['serviceable_id' => $almuerzo->id, 'name' => 'Ciclo 1', 'days' => 7, 'cycle_data' => []]);

    $this->actingAs($user)->postJson('/cycles', cyclePayload($cena, 'Ciclo 1'))->assertOk();

    expect(MenuCycle::count())->toBe(2);
});
