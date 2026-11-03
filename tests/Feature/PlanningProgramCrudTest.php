<?php

use App\Models\Cafe;
use App\Models\DailyPortion;
use App\Models\Dish;
use App\Models\Dish_category;
use App\Models\Mine;
use App\Models\PurchaseOrder;
use App\Models\Unit;
use App\Models\User;
use App\Models\WeeklyProgram;
use App\Models\WeeklyProgramItem;

function seedCrudProgram(): array
{
    $mine = Mine::factory()->create();
    $unit = Unit::factory()->create(['mine_id' => $mine->id]);
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);
    $user = User::factory()->create(['mine_id' => $mine->id]);

    $category = Dish_category::create(['name' => 'PLATO DE FONDO']);
    $dish     = Dish::create(['name' => 'Seco de res']);
    $other    = Dish::create(['name' => 'Ají de gallina']);

    $program = WeeklyProgram::create([
        'cafe_id'    => $cafe->id,
        'meal_type'  => 'Almuerzo',
        'start_date' => '2026-02-02',
        'end_date'   => '2026-02-08',
        'user_id'    => $user->id,
        'status'     => 'borrador',
    ]);
    WeeklyProgramItem::create([
        'weekly_program_id' => $program->id,
        'date'              => '2026-02-02',
        'meal_type'         => 'Almuerzo',
        'dish_category_id'  => $category->id,
        'dish_id'           => $dish->id,
        'percentage'        => 80,
    ]);
    DailyPortion::create(['weekly_program_id' => $program->id, 'date' => '2026-02-02', 'meal_type' => 'Almuerzo', 'portions_count' => 120]);

    return [$user, $cafe, $category, $dish, $other, $program];
}

it('devuelve el detalle de una programación para reabrirla', function () {
    [$user, $cafe, $category, $dish, , $program] = seedCrudProgram();

    $this->actingAs($user)
        ->getJson(route('planning.show', $program->id))
        ->assertOk()
        ->assertJson([
            'id'         => $program->id,
            'cafe_id'    => $cafe->id,
            'service'    => 'Almuerzo',
            'start_date' => '2026-02-02',
            'end_date'   => '2026-02-08',
            'items'      => [['date' => '2026-02-02', 'dish_category_id' => $category->id, 'dish_id' => $dish->id, 'dish' => 'Seco de res', 'percentage' => 80]],
            'portions'   => [['date' => '2026-02-02', 'meal_type' => 'Almuerzo', 'portions_count' => 120]],
        ]);
});

it('actualiza la misma programación sin crear otra', function () {
    [$user, $cafe, $category, , $other, $program] = seedCrudProgram();

    $this->actingAs($user)->put(route('planning.update', $program->id), [
        'cafe_id'    => $cafe->id,
        'meal_type'  => 'Almuerzo',
        'start_date' => '2026-02-02',
        'end_date'   => '2026-02-04',
        'items'      => [
            ['date' => '2026-02-03', 'meal_type' => 'Almuerzo', 'dish_category_id' => $category->id, 'dish_id' => $other->id, 'percentage' => 50],
            ['date' => '2026-02-04', 'meal_type' => 'Almuerzo', 'dish_category_id' => $category->id, 'dish_id' => null, 'percentage' => 100],
        ],
        'portions'   => [['date' => '2026-02-03', 'meal_type' => 'Almuerzo', 'portions_count' => 90]],
    ])->assertRedirect();

    $program->refresh();
    expect(WeeklyProgram::count())->toBe(1)
        ->and($program->end_date)->toStartWith('2026-02-04')
        ->and($program->items()->pluck('dish_id')->all())->toBe([$other->id])
        ->and((float) $program->items()->first()->percentage)->toBe(50.0)
        ->and($program->portions()->pluck('portions_count')->all())->toBe([90]);
});

it('elimina una programación sin órdenes de compra', function () {
    [$user, , , , , $program] = seedCrudProgram();

    $this->actingAs($user)->deleteJson(route('planning.destroy', $program->id))->assertOk();

    expect(WeeklyProgram::count())->toBe(0)
        ->and(WeeklyProgramItem::count())->toBe(0)
        ->and(DailyPortion::count())->toBe(0);
});

it('no elimina una programación que ya generó órdenes de compra', function () {
    [$user, , , , , $program] = seedCrudProgram();
    PurchaseOrder::create(['weekly_program_id' => $program->id, 'status' => 'pendiente']);

    $this->actingAs($user)->deleteJson(route('planning.destroy', $program->id))->assertStatus(422);

    expect(WeeklyProgram::count())->toBe(1)
        ->and(PurchaseOrder::count())->toBe(1);
});

it('filtra el listado por periodo planificado', function () {
    [$user, $cafe, , , , $program] = seedCrudProgram();
    $later = WeeklyProgram::create([
        'cafe_id'    => $cafe->id,
        'start_date' => '2026-03-02',
        'end_date'   => '2026-03-08',
        'user_id'    => $user->id,
        'status'     => 'borrador',
    ]);

    $this->actingAs($user);

    expect($this->getJson(route('planning.programs', ['period_from' => '2026-02-05', 'period_to' => '2026-02-06']))->json('data.*.id'))->toBe([$program->id])
        ->and($this->getJson(route('planning.programs', ['period_from' => '2026-03-01']))->json('data.*.id'))->toBe([$later->id]);
});

it('guarda los platos al crear una programación', function () {
    [$user, $cafe, $category, $dish] = seedCrudProgram();

    $this->actingAs($user)->post(route('planning.store'), [
        'cafe_id'    => $cafe->id,
        'meal_type'  => 'Cena',
        'start_date' => '2026-03-02',
        'end_date'   => '2026-03-08',
        'items'      => [['date' => '2026-03-02', 'meal_type' => 'Cena', 'dish_category_id' => $category->id, 'dish_id' => $dish->id, 'percentage' => 75]],
        'portions'   => [['date' => '2026-03-02', 'meal_type' => 'Cena', 'portions_count' => 50]],
    ])->assertRedirect();

    $program = WeeklyProgram::latest('id')->first();
    expect($program->items()->count())->toBe(1)
        ->and($program->items()->first()->dish_id)->toBe($dish->id)
        ->and((float) $program->items()->first()->percentage)->toBe(75.0);
});
