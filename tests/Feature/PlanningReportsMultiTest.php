<?php

use App\Models\Cafe;
use App\Models\DailyPortion;
use App\Models\Dish;
use App\Models\Dish_category;
use App\Models\DishRecipe;
use App\Models\Ingredient;
use App\Models\Level;
use App\Models\Mine;
use App\Models\Unit;
use App\Models\User;
use App\Models\WeeklyProgram;
use App\Models\WeeklyProgramItem;

/**
 * Seeds a comedor + one dish with a recipe for the given level, and one weekly program that
 * assigns that dish on the given date with a portions count. Returns the program.
 */
function seedReportProgram(Level $level, Dish $dish, Dish_category $category, Cafe $cafe, User $user, string $start, string $date, int $portions, float $percentage = 100): WeeklyProgram
{
    $program = WeeklyProgram::create([
        'cafe_id'    => $cafe->id,
        'start_date' => $start,
        'end_date'   => \Carbon\Carbon::parse($start)->addDays(6)->toDateString(),
        'user_id'    => $user->id,
        'status'     => 'borrador',
    ]);

    WeeklyProgramItem::create([
        'weekly_program_id' => $program->id,
        'date'              => $date,
        'meal_type'         => 'Almuerzo',
        'dish_category_id'  => $category->id,
        'dish_id'           => $dish->id,
        'percentage'        => $percentage,
    ]);

    DailyPortion::create([
        'weekly_program_id' => $program->id,
        'date'              => $date,
        'meal_type'         => 'Almuerzo',
        'portions_count'    => $portions,
    ]);

    return $program;
}

function seedReportBase(): array
{
    $mine = Mine::factory()->create();
    $unit = Unit::factory()->create(['mine_id' => $mine->id]);
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);
    $user = User::factory()->create(['mine_id' => $mine->id]);

    $level    = Level::create(['name' => 'Obrero']);
    $category = Dish_category::create(['name' => 'PLATO DE FONDO']);
    $dish     = Dish::create(['name' => 'Seco de Julius']);

    $ingredient = Ingredient::create(['name' => 'Arroz']);
    $recipe = DishRecipe::create(['dish_id' => $dish->id, 'name' => 'Receta', 'level_id' => $level->id]);
    $recipe->ingredients()->attach($ingredient->id, [
        'gross_weight' => 120,
        'net_weight'   => 120,
        'solid_waste'  => 0,
        'liquid_waste' => 0,
        'calories'     => 0,
        'cost'         => 0,
        'unit_price'   => 0,
    ]);

    return [$mine, $unit, $cafe, $user, $level, $category, $dish, $ingredient];
}

test('planning index exposes service, unit, mine and saved date per program', function () {
    [$mine, $unit, $cafe, $user, $level, $category, $dish] = seedReportBase();
    $program = seedReportProgram($level, $dish, $category, $cafe, $user, '2026-02-02', '2026-02-03', 100);

    $this->actingAs($user)
        ->get(route('planning.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('planning/Index')
            ->where('programs.0.services', ['Almuerzo'])
            ->where('programs.0.cafe.unit.name', $unit->name)
            ->where('programs.0.cafe.unit.mine.name', $mine->name)
            ->has('programs.0.created_at'));
});

test('quebrado pdf combines several selected programs', function () {
    [, , $cafe, $user, $level, $category, $dish] = seedReportBase();

    $p1 = seedReportProgram($level, $dish, $category, $cafe, $user, '2026-02-02', '2026-02-03', 100);
    $p2 = seedReportProgram($level, $dish, $category, $cafe, $user, '2026-02-09', '2026-02-10', 80);

    $this->actingAs($user)
        ->get(route('planning.quebrado-pdf', ['program_ids' => [$p1->id, $p2->id], 'level_id' => $level->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('planning reports require at least one program', function () {
    [, , , $user, $level] = seedReportBase();

    $this->actingAs($user)
        ->get(route('planning.requerimiento-pdf', ['level_id' => $level->id]))
        ->assertSessionHasErrors('program_ids');
});

test('requerimiento pdf scales by the per-dish percentage', function () {
    [, , $cafe, $user, $level, $category, $dish] = seedReportBase();

    // 100 raciones al 50% => 50 raciones efectivas => 120g * 50 = 6000g = 6 kg
    $program = seedReportProgram($level, $dish, $category, $cafe, $user, '2026-02-02', '2026-02-03', 100, 50);

    $this->actingAs($user)
        ->get(route('planning.requerimiento-pdf', ['program_ids' => [$program->id], 'level_id' => $level->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
