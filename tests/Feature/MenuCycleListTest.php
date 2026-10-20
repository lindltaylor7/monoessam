<?php

use App\Models\Cafe;
use App\Models\MenuCycle;
use App\Models\Mine;
use App\Models\Serviceable;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;

function seedCycleListBase(): array
{
    $mine = Mine::factory()->create();
    $unit = Unit::factory()->create(['mine_id' => $mine->id]);
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);
    $user = User::factory()->create(['mine_id' => $mine->id]);

    $desayuno = Serviceable::create(['serviceable_id' => $cafe->id, 'serviceable_type' => Cafe::class, 'service_id' => Service::create(['name' => 'DESAYUNO', 'code' => 'DES', 'type' => 'comedor'])->id]);
    $cena     = Serviceable::create(['serviceable_id' => $cafe->id, 'serviceable_type' => Cafe::class, 'service_id' => Service::create(['name' => 'CENA', 'code' => 'CEN', 'type' => 'comedor'])->id]);

    return [$user, $desayuno, $cena];
}

it('pagina el listado de ciclos sin traer cycle_data', function () {
    [$user, $desayuno] = seedCycleListBase();
    foreach (range(1, 12) as $n) {
        MenuCycle::create(['serviceable_id' => $desayuno->id, 'name' => "ciclo {$n}", 'days' => 7, 'cycle_data' => []]);
    }

    $response = $this->actingAs($user)->getJson(route('cycles.list', ['per_page' => 10]))->assertOk();

    expect($response->json('total'))->toBe(12)
        ->and($response->json('last_page'))->toBe(2)
        ->and($response->json('data'))->toHaveCount(10)
        ->and($response->json('data.0'))->not->toHaveKey('cycle_data')
        ->and($response->json('data.0.name'))->toBe('ciclo 12');
});

it('filtra por servicio, nombre e id', function () {
    [$user, $desayuno, $cena] = seedCycleListBase();
    MenuCycle::create(['serviceable_id' => $desayuno->id, 'name' => 'yaruicocha desayuno', 'days' => 7, 'cycle_data' => []]);
    $cenaCycle = MenuCycle::create(['serviceable_id' => $cena->id, 'name' => 'volcan cena', 'days' => 10, 'cycle_data' => []]);

    $this->actingAs($user);

    expect($this->getJson(route('cycles.list', ['serviceable_id' => $cena->id]))->json('data.*.id'))->toBe([$cenaCycle->id])
        ->and($this->getJson(route('cycles.list', ['search' => 'yarui']))->json('total'))->toBe(1)
        ->and($this->getJson(route('cycles.list', ['search' => (string) $cenaCycle->id]))->json('data.*.id'))->toContain($cenaCycle->id);
});

it('devuelve todos los ciclos de un servicio con all=1', function () {
    [$user, $desayuno] = seedCycleListBase();
    foreach (range(1, 15) as $n) {
        MenuCycle::create(['serviceable_id' => $desayuno->id, 'name' => "ciclo {$n}", 'days' => 7, 'cycle_data' => []]);
    }

    $response = $this->actingAs($user)->getJson(route('cycles.list', ['serviceable_id' => $desayuno->id, 'all' => 1]))->assertOk();

    expect($response->json('data'))->toHaveCount(15);
});
