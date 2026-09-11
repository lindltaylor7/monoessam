<?php

use App\Models\Cafe;
use App\Models\DailyPortion;
use App\Models\Dish;
use App\Models\Dish_category;
use App\Models\Mine;
use App\Models\Unit;
use App\Models\User;
use App\Models\WeeklyProgram;
use App\Models\WeeklyProgramItem;

function seedMenuProgram(string $start = '2026-09-01', string $end = '2026-09-07'): array
{
    $mine = Mine::factory()->create();
    $unit = Unit::factory()->create(['mine_id' => $mine->id]);
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id, 'name' => 'KOLPA STAFF']);
    $user = User::factory()->create(['mine_id' => $mine->id]);

    $mesa  = Dish_category::create(['name' => 'A LA MESA']);
    $jugo  = Dish_category::create(['name' => 'JUGO DE FRUTA']);
    $fondo = Dish_category::create(['name' => '1 P. FONDO']);

    $d1 = Dish::create(['name' => 'Mantequilla, Mermelada']);
    $d2 = Dish::create(['name' => 'Jugo de Fresa']);
    $d3 = Dish::create(['name' => 'Pollo al Horno']);
    $d4 = Dish::create(['name' => 'Cau Cau Criollo']);

    $program = WeeklyProgram::create([
        'cafe_id'    => $cafe->id,
        'start_date' => $start,
        'end_date'   => $end,
        'user_id'    => $user->id,
        'status'     => 'borrador',
    ]);

    $mk = function ($date, $meal, $cat, $dish) use ($program) {
        WeeklyProgramItem::create([
            'weekly_program_id' => $program->id,
            'date'              => $date,
            'meal_type'         => $meal,
            'dish_category_id'  => $cat->id,
            'dish_id'           => $dish->id,
        ]);
    };

    $mk('2026-09-01', 'Desayuno', $mesa, $d1);
    $mk('2026-09-01', 'Desayuno', $jugo, $d2);
    $mk('2026-09-02', 'Desayuno', $mesa, $d1);
    // Dos platos de la misma categoría el mismo día -> dos slots [01]/[02]
    $mk('2026-09-01', 'Almuerzo', $fondo, $d3);
    $mk('2026-09-01', 'Almuerzo', $fondo, $d4);

    DailyPortion::create(['weekly_program_id' => $program->id, 'date' => '2026-09-01', 'meal_type' => 'Desayuno', 'portions_count' => 140]);
    DailyPortion::create(['weekly_program_id' => $program->id, 'date' => '2026-09-01', 'meal_type' => 'Almuerzo', 'portions_count' => 150]);

    return [$user, $program];
}

test('menu semanal excel downloads for selected programs', function () {
    [$user, $program] = seedMenuProgram();
    [, $program2] = seedMenuProgram('2026-09-08', '2026-09-14');

    $response = $this->actingAs($user)->get(route('planning.menu-excel', [
        'program_ids' => [$program->id, $program2->id],
    ]));

    $response->assertOk();
    $response->assertHeader(
        'content-type',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
    expect($response->getFile()->getSize())->toBeGreaterThan(1000);
});

test('menu semanal excel requires at least one valid program', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('planning.menu-excel', ['program_ids' => []]))
        ->assertSessionHasErrors('program_ids');

    $this->actingAs($user)
        ->get(route('planning.menu-excel', ['program_ids' => [999999]]))
        ->assertSessionHasErrors('program_ids.0');
});
