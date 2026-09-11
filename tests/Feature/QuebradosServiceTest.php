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
 * Antes QuebradosService recorría dish_ingredient_levels (0 filas en la BD) y SIEMPRE
 * generaba una orden sin líneas informando éxito. Ahora usa DishRecipe por nivel.
 */
function seedQuebradosBase(): array
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
        'gross_weight' => 120, 'net_weight' => 120, 'solid_waste' => 0,
        'liquid_waste' => 0, 'calories' => 0, 'cost' => 0, 'unit_price' => 0,
    ]);

    $program = WeeklyProgram::create([
        'cafe_id' => $cafe->id, 'start_date' => '2026-02-02', 'end_date' => '2026-02-08',
        'user_id' => $user->id, 'status' => 'borrador',
    ]);
    WeeklyProgramItem::create([
        'weekly_program_id' => $program->id, 'date' => '2026-02-03', 'meal_type' => 'Almuerzo',
        'dish_category_id' => $category->id, 'dish_id' => $dish->id, 'percentage' => 100,
    ]);
    DailyPortion::create([
        'weekly_program_id' => $program->id, 'date' => '2026-02-03',
        'meal_type' => 'Almuerzo', 'portions_count' => 100,
    ]);

    return compact('user', 'program', 'level', 'ingredient');
}

test('generate purchase order produces items from the dish recipe', function () {
    ['user' => $user, 'program' => $program, 'level' => $level, 'ingredient' => $ingredient] = seedQuebradosBase();

    $this->actingAs($user)
        ->post(route('planning.generate-po', $program->id), ['level_id' => $level->id])
        ->assertRedirect();

    $order = \App\Models\PurchaseOrder::latest('id')->first();
    expect($order)->not->toBeNull();
    expect($order->items)->toHaveCount(1);

    // 120 g/ración * 100 raciones = 12000 g = 12 kg
    expect((float) $order->items->first()->total_amount)->toBe(12.0);
    expect($order->items->first()->ingredient_id)->toBe($ingredient->id);
});

test('generate purchase order requires a level', function () {
    ['user' => $user, 'program' => $program] = seedQuebradosBase();

    $this->actingAs($user)
        ->post(route('planning.generate-po', $program->id), [])
        ->assertSessionHasErrors('level_id');
});
