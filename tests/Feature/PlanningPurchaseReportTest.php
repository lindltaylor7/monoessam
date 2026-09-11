<?php

use App\Models\Cafe;
use App\Models\City;
use App\Models\DailyPortion;
use App\Models\Dish;
use App\Models\Dish_category;
use App\Models\DishRecipe;
use App\Models\Ingredient;
use App\Models\Ingredient_city_provider;
use App\Models\Level;
use App\Models\Mine;
use App\Models\Provider;
use App\Models\Unit;
use App\Models\User;
use App\Models\WeeklyProgram;
use App\Models\WeeklyProgramItem;

/**
 * Two comedores under the same mine/unit, each with its own weekly program using the same dish,
 * so the report has to pivot the requirement by destino instead of just summing everything.
 */
test('purchase report pivots requirement by cafe and lists every registered provider cheapest-first', function () {
    $mine = Mine::factory()->create();
    $unit = Unit::factory()->create(['mine_id' => $mine->id]);
    $cafeA = Cafe::factory()->create(['unit_id' => $unit->id, 'name' => 'KOLPA']);
    $cafeB = Cafe::factory()->create(['unit_id' => $unit->id, 'name' => 'PANADERIA']);
    $user = User::factory()->create(['mine_id' => $mine->id]);

    $level = Level::create(['name' => 'Obrero']);
    $category = Dish_category::create(['name' => 'PLATO DE FONDO']);
    $dish = Dish::create(['name' => 'Seco de Julius']);
    $ingredientCategory = \App\Models\Ingredient_category::create(['name' => 'Verduras']);
    $ingredient = Ingredient::create(['name' => 'Arroz', 'ingredient_category_id' => $ingredientCategory->id]);

    $recipe = DishRecipe::create(['dish_id' => $dish->id, 'name' => 'Receta', 'level_id' => $level->id]);
    $recipe->ingredients()->attach($ingredient->id, [
        'gross_weight' => 120,
        'net_weight' => 120,
        'solid_waste' => 0,
        'liquid_waste' => 0,
        'calories' => 0,
        'cost' => 0,
        'unit_price' => 0,
    ]);

    $city = City::create(['name' => 'Huancayo']);
    $cheapProvider = Provider::create(['name' => 'Carlos Peña Otarola', 'type' => 'ingredient']);
    $expensiveProvider = Provider::create(['name' => 'Otro Proveedor', 'type' => 'ingredient']);
    Ingredient_city_provider::create(['ingredient_id' => $ingredient->id, 'provider_id' => $cheapProvider->id, 'city_id' => $city->id, 'cost_price' => 1.84]);
    Ingredient_city_provider::create(['ingredient_id' => $ingredient->id, 'provider_id' => $expensiveProvider->id, 'city_id' => $city->id, 'cost_price' => 2.50]);

    $programA = WeeklyProgram::create(['cafe_id' => $cafeA->id, 'start_date' => '2026-02-02', 'end_date' => '2026-02-08', 'user_id' => $user->id, 'status' => 'borrador']);
    WeeklyProgramItem::create(['weekly_program_id' => $programA->id, 'date' => '2026-02-03', 'meal_type' => 'Almuerzo', 'dish_category_id' => $category->id, 'dish_id' => $dish->id, 'percentage' => 100]);
    DailyPortion::create(['weekly_program_id' => $programA->id, 'date' => '2026-02-03', 'meal_type' => 'Almuerzo', 'portions_count' => 100]);

    $programB = WeeklyProgram::create(['cafe_id' => $cafeB->id, 'start_date' => '2026-02-02', 'end_date' => '2026-02-08', 'user_id' => $user->id, 'status' => 'borrador']);
    WeeklyProgramItem::create(['weekly_program_id' => $programB->id, 'date' => '2026-02-03', 'meal_type' => 'Almuerzo', 'dish_category_id' => $category->id, 'dish_id' => $dish->id, 'percentage' => 100]);
    DailyPortion::create(['weekly_program_id' => $programB->id, 'date' => '2026-02-03', 'meal_type' => 'Almuerzo', 'portions_count' => 50]);

    $this->actingAs($user)
        ->get(route('planning.reporte-compras', [
            'program_ids' => [$programA->id, $programB->id],
            'level_id' => $level->id,
            'city_id' => $city->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('planning/PurchaseReport')
            ->has('destinations', 2)
            ->where('categories.0.rows.0.name', 'Arroz')
            // 100 raciones * 120g = 12000g = 12kg en KOLPA; 50 raciones * 120g = 6000g = 6kg en PANADERIA
            ->where("categories.0.rows.0.by_cafe.{$cafeA->id}", 12)
            ->where("categories.0.rows.0.by_cafe.{$cafeB->id}", 6)
            ->where('categories.0.rows.0.total_kg', 18)
            ->where('categories.0.rows.0.providers.0.provider_name', 'Carlos Peña Otarola')
            ->where('categories.0.rows.0.providers.0.price', 1.84)
            ->where('categories.0.rows.0.providers.1.provider_name', 'Otro Proveedor')
            ->where('categories.0.rows.0.best_price', 1.84)
            ->where('categories.0.rows.0.subtotal', 33.12)
            ->where('max_providers', 2)
            ->where('missing_price_count', 0));
});

test('purchase report flags ingredients with no registered price in the chosen city', function () {
    $mine = Mine::factory()->create();
    $unit = Unit::factory()->create(['mine_id' => $mine->id]);
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);
    $user = User::factory()->create(['mine_id' => $mine->id]);

    $level = Level::create(['name' => 'Obrero']);
    $category = Dish_category::create(['name' => 'PLATO DE FONDO']);
    $dish = Dish::create(['name' => 'Seco de Julius']);
    $ingredient = Ingredient::create(['name' => 'Arroz']);

    $recipe = DishRecipe::create(['dish_id' => $dish->id, 'name' => 'Receta', 'level_id' => $level->id]);
    $recipe->ingredients()->attach($ingredient->id, [
        'gross_weight' => 120, 'net_weight' => 120, 'solid_waste' => 0, 'liquid_waste' => 0, 'calories' => 0, 'cost' => 0, 'unit_price' => 0,
    ]);

    $city = City::create(['name' => 'Huancayo']);

    $program = WeeklyProgram::create(['cafe_id' => $cafe->id, 'start_date' => '2026-02-02', 'end_date' => '2026-02-08', 'user_id' => $user->id, 'status' => 'borrador']);
    WeeklyProgramItem::create(['weekly_program_id' => $program->id, 'date' => '2026-02-03', 'meal_type' => 'Almuerzo', 'dish_category_id' => $category->id, 'dish_id' => $dish->id, 'percentage' => 100]);
    DailyPortion::create(['weekly_program_id' => $program->id, 'date' => '2026-02-03', 'meal_type' => 'Almuerzo', 'portions_count' => 10]);

    $this->actingAs($user)
        ->get(route('planning.reporte-compras', ['program_ids' => [$program->id], 'level_id' => $level->id, 'city_id' => $city->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('planning/PurchaseReport')
            ->where('categories.0.rows.0.providers', [])
            ->where('categories.0.rows.0.best_price', null)
            ->where('categories.0.rows.0.subtotal', null)
            ->where('missing_price_count', 1)
            ->where('grand_total', 0));
});
