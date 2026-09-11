<?php

use App\Models\Cafe;
use App\Models\Dish;
use App\Models\Dish_category;
use App\Models\DishRecipe;
use App\Models\Dosification;
use App\Models\Ingredient;
use App\Models\Level;
use App\Models\Mine;
use App\Models\Unit;
use App\Models\User;
use App\Models\WeeklyProgram;
use App\Models\WeeklyProgramItem;
use App\Models\DailyPortion;

function seedDosificacionProgram(): array
{
    $mine = Mine::factory()->create();
    $unit = Unit::factory()->create(['mine_id' => $mine->id]);
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);
    $user = User::factory()->create(['mine_id' => $mine->id]);

    $level    = Level::create(['name' => 'Obrero']);
    $category = Dish_category::create(['name' => 'A LA MESA']);
    $dish     = Dish::create(['name' => 'Mantequilla, Mermelada']);

    $ingredient = Ingredient::create(['name' => 'Mantequilla GLORIA']);
    Dosification::create([
        'ingredient_id' => $ingredient->id,
        'energy'        => 717,
        'water'         => 16,
        'protein'       => 0.85,
        'lipid'         => 81,
        'carbohydrate'  => 0.06,
        'calcium'       => 24,
        'sodium'        => 11,
    ]);

    $recipe = DishRecipe::create(['dish_id' => $dish->id, 'name' => 'Receta', 'level_id' => $level->id]);
    $recipe->ingredients()->attach($ingredient->id, [
        'gross_weight' => 2.00,
        'net_weight'   => 2.00,
        'solid_waste'  => 0,
        'liquid_waste' => 0,
        'calories'     => 14.34,
        'cost'         => 0,
        'unit_price'   => 0,
    ]);

    $program = WeeklyProgram::create([
        'cafe_id'    => $cafe->id,
        'start_date' => '2026-09-01',
        'end_date'   => '2026-09-07',
        'user_id'    => $user->id,
        'status'     => 'borrador',
    ]);

    WeeklyProgramItem::create([
        'weekly_program_id' => $program->id,
        'date'              => '2026-09-03',
        'meal_type'         => 'Desayuno',
        'dish_category_id'  => $category->id,
        'dish_id'           => $dish->id,
    ]);

    DailyPortion::create([
        'weekly_program_id' => $program->id,
        'date'              => '2026-09-03',
        'meal_type'         => 'Desayuno',
        'portions_count'    => 140,
    ]);

    return [$user, $program, $level];
}

test('dosificacion nutricional pdf renders for a program', function () {
    [$user, $program, $level] = seedDosificacionProgram();

    $response = $this->actingAs($user)
        ->get(route('planning.dosificacion-pdf', ['program_ids' => [$program->id], 'level_id' => $level->id]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('dosificacion nutricional pdf requires a valid level', function () {
    [$user, $program] = seedDosificacionProgram();

    $this->actingAs($user)
        ->get(route('planning.dosificacion-pdf', ['program_ids' => [$program->id], 'level_id' => 999999]))
        ->assertSessionHasErrors('level_id');
});
