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
use App\Models\MenuCycle;
use App\Models\Mine;
use App\Models\Provider;
use App\Models\PurchaseOrder;
use App\Models\Serviceable;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;
use App\Models\WeeklyProgram;
use App\Models\WeeklyProgramItem;

/**
 * Un mismo quebrado se usa en unidades que se abastecen desde ciudades distintas: el quebrado
 * guarda solo gramos y el costo se calcula con el proveedor activo de la ciudad de
 * abastecimiento del comedor (o, sin precio ahí, con el de otra ciudad marcado como prestado).
 */
function seedSupplyCityBase(): array
{
    $lima = City::create(['name' => 'LIMA']);
    $huancayo = City::create(['name' => 'HUANCAYO']);
    $provider = Provider::create(['name' => 'Proveedor Central']);

    $mine = Mine::factory()->create();
    $unitLima = Unit::factory()->create(['mine_id' => $mine->id, 'supply_city_id' => $lima->id]);
    $unitHyo = Unit::factory()->create(['mine_id' => $mine->id, 'supply_city_id' => $huancayo->id]);
    $cafeLima = Cafe::factory()->create(['unit_id' => $unitLima->id]);
    $cafeHyo = Cafe::factory()->create(['unit_id' => $unitHyo->id]);
    $user = User::factory()->create(['mine_id' => $mine->id]);

    $level = Level::create(['name' => 'Obrero']);
    $dish = Dish::create(['name' => 'Arroz con pollo']);

    // Arroz: precio en las dos ciudades. Pollo: solo en Lima.
    $arroz = Ingredient::create(['name' => 'Arroz']);
    $pollo = Ingredient::create(['name' => 'Pollo']);
    foreach ([[$arroz, $lima, 4.00], [$arroz, $huancayo, 5.00], [$pollo, $lima, 10.00]] as [$ingredient, $city, $price]) {
        Ingredient_city_provider::create([
            'ingredient_id' => $ingredient->id, 'provider_id' => $provider->id,
            'city_id' => $city->id, 'cost_price' => $price, 'is_active' => true,
        ]);
    }

    $recipe = DishRecipe::create(['dish_id' => $dish->id, 'name' => 'Receta', 'level_id' => $level->id]);
    // 100 g de cada uno; el precio grabado en la línea ya no debe usarse.
    $recipe->ingredients()->attach($arroz->id, ['gross_weight' => 100, 'net_weight' => 100, 'unit_price' => 99, 'cost' => 9.9]);
    $recipe->ingredients()->attach($pollo->id, ['gross_weight' => 100, 'net_weight' => 100, 'unit_price' => 0, 'cost' => 0]);

    return compact('lima', 'huancayo', 'cafeLima', 'cafeHyo', 'user', 'level', 'dish', 'recipe', 'arroz', 'pollo');
}

function loadedRecipe(DishRecipe $recipe): DishRecipe
{
    $recipe = DishRecipe::with(['ingredients.assignments', 'ingredients.dosification', 'ingredients.atwaterFactor', 'ingredients.nutritionalFactors'])
        ->find($recipe->id);
    foreach ($recipe->ingredients as $ingredient) {
        $ingredient->gross_weight = $ingredient->pivot->gross_weight;
        $ingredient->final_product = $ingredient->pivot->net_weight;
    }

    return $recipe;
}

test('el costo del quebrado depende de la ciudad y presta el precio que falta', function () {
    ['lima' => $lima, 'huancayo' => $huancayo, 'recipe' => $recipe] = seedSupplyCityBase();

    $inLima = loadedRecipe($recipe);
    $inLima->applyLiveTotals($lima->id);
    // 0.1 kg * 4 + 0.1 kg * 10; el unit_price 99 grabado en la receta se ignora.
    expect(round($inLima->total_cost, 2))->toBe(1.40)
        ->and($inLima->missing_prices)->toBe(0)
        ->and($inLima->borrowed_prices)->toBe(0);

    $inHyo = loadedRecipe($recipe);
    $inHyo->applyLiveTotals($huancayo->id);
    // Arroz de Huancayo (5) + Pollo prestado de Lima (10).
    expect(round($inHyo->total_cost, 2))->toBe(1.50)
        ->and($inHyo->borrowed_prices)->toBe(1)
        ->and($inHyo->ingredients->firstWhere('name', 'Pollo')->price_borrowed)->toBeTrue();
});

test('un proveedor inactivo no cuenta como precio de la ciudad', function () {
    ['huancayo' => $huancayo, 'recipe' => $recipe, 'pollo' => $pollo] = seedSupplyCityBase();
    Ingredient_city_provider::where('ingredient_id', $pollo->id)->update(['is_active' => false]);

    $inHyo = loadedRecipe($recipe);
    $inHyo->applyLiveTotals($huancayo->id);

    expect(round($inHyo->total_cost, 2))->toBe(0.50)
        ->and($inHyo->missing_prices)->toBe(1);
});

test('la búsqueda de platos costea con la ciudad pedida', function () {
    ['user' => $user, 'huancayo' => $huancayo, 'level' => $level] = seedSupplyCityBase();

    $recipe = $this->actingAs($user)
        ->getJson(route('dishes.search', ['level_id' => $level->id, 'city_id' => $huancayo->id]))
        ->assertOk()
        ->json('0.recipes.0');

    expect(round((float) $recipe['total_cost'], 2))->toBe(1.50)
        ->and($recipe['borrowed_prices'])->toBe(1);
});

test('guardar un quebrado no graba precios en la receta', function () {
    ['user' => $user, 'recipe' => $recipe, 'arroz' => $arroz, 'lima' => $lima] = seedSupplyCityBase();

    $this->actingAs($user)->putJson(route('dish-recipes.update', $recipe->id), [
        'city_id' => $lima->id,
        'total_cost' => 123,
        'ingredients' => [['id' => $arroz->id, 'gross_weight' => 200, 'final_product' => 200, 'unit_price' => 50, 'cost' => 10]],
    ])->assertOk()->assertJsonPath('total_cost', 0.8);

    expect((float) $recipe->fresh()->total_cost)->toBe(0.0);
    expect((float) $recipe->ingredients()->first()->pivot->unit_price)->toBe(0.0);
});

test('el ciclo se costea con la ciudad de abastecimiento de su comedor', function () {
    ['user' => $user, 'cafeHyo' => $cafeHyo, 'huancayo' => $huancayo, 'lima' => $lima, 'dish' => $dish, 'level' => $level] = seedSupplyCityBase();

    $serviceable = Serviceable::create([
        'serviceable_id' => $cafeHyo->id, 'serviceable_type' => Cafe::class,
        'service_id' => Service::create(['name' => 'ALMUERZO', 'code' => 'ALM', 'type' => 'comedor'])->id,
    ]);
    // Foto guardada con el precio de Lima: debe recostearse en Huancayo.
    $cycle = MenuCycle::create([
        'serviceable_id' => $serviceable->id, 'name' => 'Ciclo', 'days' => 1,
        'cycle_data' => [['id' => 1, 'days' => [1 => [
            'dish_id' => $dish->id, 'level_id' => $level->id, 'dish_name' => 'Arroz con pollo',
            'price' => 1.40, 'calories' => 10, 'city_id' => $lima->id,
        ]]]],
    ]);

    $day = $this->actingAs($user)->getJson(route('cycles.show', $cycle->id))
        ->assertOk()
        ->assertJsonPath('city_id', $huancayo->id)
        ->json('cycle_data.0.days.1');

    expect((float) $day['price'])->toBe(1.5)
        ->and($day['city_id'])->toBe($huancayo->id)
        ->and($day['borrowed_prices'])->toBe(1)
        // Las calorías guardadas (distintas de 0) se respetan.
        ->and((float) $day['calories'])->toBe(10.0);
});

test('recalcular costos de la grilla usa la ciudad indicada', function () {
    ['user' => $user, 'lima' => $lima, 'dish' => $dish, 'level' => $level] = seedSupplyCityBase();

    $this->actingAs($user)->postJson(route('cycles.prices'), [
        'city_id' => $lima->id,
        'items' => [['dish_id' => $dish->id, 'level_id' => $level->id]],
    ])->assertOk()
        ->assertJsonPath("totals.{$dish->id}-{$level->id}.price", 1.4)
        ->assertJsonPath("totals.{$dish->id}-{$level->id}.missing_prices", 0);
});

test('programaciones de comedores de distintas ciudades generan una orden por ciudad', function () {
    ['user' => $user, 'cafeLima' => $cafeLima, 'cafeHyo' => $cafeHyo, 'lima' => $lima, 'huancayo' => $huancayo, 'dish' => $dish, 'level' => $level] = seedSupplyCityBase();
    $category = Dish_category::create(['name' => 'PLATO DE FONDO']);

    $programs = collect([$cafeLima, $cafeHyo])->map(function ($cafe) use ($user, $category, $dish) {
        $program = WeeklyProgram::create([
            'cafe_id' => $cafe->id, 'start_date' => '2026-02-02', 'end_date' => '2026-02-08',
            'user_id' => $user->id, 'status' => 'borrador',
        ]);
        WeeklyProgramItem::create([
            'weekly_program_id' => $program->id, 'date' => '2026-02-03', 'meal_type' => 'Almuerzo',
            'dish_category_id' => $category->id, 'dish_id' => $dish->id, 'percentage' => 100,
        ]);
        DailyPortion::create([
            'weekly_program_id' => $program->id, 'date' => '2026-02-03', 'meal_type' => 'Almuerzo', 'portions_count' => 10,
        ]);

        return $program;
    });

    $this->actingAs($user)
        ->post(route('planning.generate-po-multiple'), ['program_ids' => $programs->pluck('id')->all(), 'level_id' => $level->id])
        ->assertRedirect(route('purchase_orders.index'));

    expect(PurchaseOrder::orderBy('city_id')->pluck('city_id')->all())->toBe([$lima->id, $huancayo->id]);
});

test('un comedor sin ciudad propia hereda la de su unidad', function () {
    ['cafeHyo' => $cafeHyo, 'huancayo' => $huancayo, 'lima' => $lima] = seedSupplyCityBase();

    expect($cafeHyo->fresh()->supplyCityId())->toBe($huancayo->id);

    $cafeHyo->update(['supply_city_id' => $lima->id]);
    expect($cafeHyo->fresh()->supplyCityId())->toBe($lima->id);
});
