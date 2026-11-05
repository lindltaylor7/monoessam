<?php

use App\Models\Cafe;
use App\Models\DailyPortion;
use App\Models\Dish;
use App\Models\Dish_category;
use App\Models\DishRecipe;
use App\Models\Ingredient;
use App\Models\Level;
use App\Models\MenuCycle;
use App\Models\Mine;
use App\Models\PurchaseOrder;
use App\Models\Service;
use App\Models\Serviceable;
use App\Models\Structure;
use App\Models\StructureCost;
use App\Models\Unit;
use App\Models\User;
use App\Models\WeeklyProgram;
use App\Models\WeeklyProgramItem;

/**
 * Cada plato programado guarda el nivel de receta con que lo costeó el ciclo, y los reportes /
 * la orden de compra usan esa receta en vez de un único nivel elegido al generarlos.
 */
function seedTwoLevelDish(): array
{
    $mine = Mine::factory()->create();
    $unit = Unit::factory()->create(['mine_id' => $mine->id]);
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);
    $user = User::factory()->create(['mine_id' => $mine->id]);

    $obrero = Level::create(['name' => 'Obrero']);
    $staff = Level::create(['name' => 'Staff']);
    $category = Dish_category::create(['name' => 'PLATO DE FONDO']);
    $dish = Dish::create(['name' => 'Seco']);
    $ingredient = Ingredient::create(['name' => 'Arroz']);

    // Obrero: 100 g por ración. Staff: 200 g por ración.
    $recipes = [];
    foreach ([[$obrero, 100], [$staff, 200]] as [$level, $grams]) {
        $recipe = DishRecipe::create(['dish_id' => $dish->id, 'name' => 'Receta', 'level_id' => $level->id]);
        $recipe->ingredients()->attach($ingredient->id, [
            'gross_weight' => $grams, 'net_weight' => $grams, 'solid_waste' => 0,
            'liquid_waste' => 0, 'calories' => 0, 'cost' => 0, 'unit_price' => 0,
        ]);
        $recipes[$level->name] = $recipe;
    }

    $program = WeeklyProgram::create([
        'cafe_id' => $cafe->id, 'start_date' => now()->toDateString(), 'end_date' => now()->addDays(6)->toDateString(),
        'user_id' => $user->id, 'status' => 'borrador',
    ]);
    DailyPortion::create([
        'weekly_program_id' => $program->id, 'date' => now()->toDateString(),
        'meal_type' => 'Almuerzo', 'portions_count' => 10,
    ]);

    return compact('user', 'cafe', 'program', 'obrero', 'staff', 'category', 'dish', 'ingredient', 'recipes');
}

test('la orden de compra usa la receta del nivel de cada plato sin pedir nivel', function () {
    $d = seedTwoLevelDish();
    WeeklyProgramItem::create([
        'weekly_program_id' => $d['program']->id, 'date' => now()->toDateString(), 'meal_type' => 'Almuerzo',
        'dish_category_id' => $d['category']->id, 'dish_id' => $d['dish']->id, 'level_id' => $d['staff']->id, 'percentage' => 100,
    ]);

    $this->actingAs($d['user'])
        ->post(route('planning.generate-po', $d['program']->id), [])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    // Staff: 200 g x 10 raciones = 2 kg (con la receta Obrero serían 1 kg).
    expect((float) PurchaseOrder::latest('id')->first()->items->first()->total_amount)->toBe(2.0);
});

test('el nivel elegido en el reporte solo cubre a los platos sin nivel', function () {
    $d = seedTwoLevelDish();
    foreach ([$d['staff']->id, null] as $levelId) {
        WeeklyProgramItem::create([
            'weekly_program_id' => $d['program']->id, 'date' => now()->toDateString(), 'meal_type' => 'Almuerzo',
            'dish_category_id' => $d['category']->id, 'dish_id' => $d['dish']->id, 'level_id' => $levelId, 'percentage' => 100,
        ]);
    }

    // Sin nivel de respaldo no se puede: hay un plato sin nivel.
    $this->actingAs($d['user'])
        ->post(route('planning.generate-po', $d['program']->id), [])
        ->assertSessionHasErrors('level_id');

    $this->actingAs($d['user'])
        ->post(route('planning.generate-po', $d['program']->id), ['level_id' => $d['obrero']->id])
        ->assertRedirect();

    // Staff 2 kg + Obrero (respaldo) 1 kg.
    expect((float) PurchaseOrder::latest('id')->first()->items->first()->total_amount)->toBe(3.0);
});

test('guardar y reabrir una programación conserva el nivel de cada plato', function () {
    $d = seedTwoLevelDish();
    $date = now()->toDateString();

    $this->actingAs($d['user'])->put(route('planning.update', $d['program']->id), [
        'cafe_id' => $d['cafe']->id,
        'start_date' => $date,
        'end_date' => now()->addDays(6)->toDateString(),
        'items' => [[
            'date' => $date, 'meal_type' => 'Almuerzo', 'dish_category_id' => $d['category']->id,
            'dish_id' => $d['dish']->id, 'level_id' => $d['staff']->id, 'percentage' => 100,
        ]],
        'portions' => [['date' => $date, 'meal_type' => 'Almuerzo', 'portions_count' => 10]],
    ])->assertSessionHasNoErrors();

    $this->actingAs($d['user'])
        ->getJson(route('planning.show', $d['program']->id))
        ->assertOk()
        ->assertJsonPath('items.0.level_id', $d['staff']->id);

    $this->actingAs($d['user'])
        ->getJson(route('planning.programs'))
        ->assertJsonPath('data.0.items_without_level', 0);
});

test('planning.prices devuelve el costo por plato y nivel y los límites de la estructura', function () {
    $d = seedTwoLevelDish();
    $serviceable = Serviceable::create([
        'serviceable_id' => $d['cafe']->id, 'serviceable_type' => Cafe::class,
        'service_id' => Service::create(['name' => 'ALMUERZO', 'code' => 'ALM', 'type' => 'comedor'])->id,
    ]);
    $structure = Structure::create(['name' => 'Estructura', 'serviceable_id' => $serviceable->id, 'selling_price' => 18]);
    StructureCost::create([
        'structure_id' => $structure->id, 'dish_category_id' => $d['category']->id,
        'name' => 'PLATO DE FONDO', 'order' => 1, 'total_cost' => 4.5, 'total_cost_superior' => 6.0,
    ]);

    $this->actingAs($d['user'])
        ->postJson(route('planning.prices'), [
            'cafe_id' => $d['cafe']->id,
            'serviceable_id' => $serviceable->id,
            'items' => [['dish_id' => $d['dish']->id, 'level_id' => $d['staff']->id]],
        ])
        ->assertOk()
        ->assertJsonStructure(['city_id', 'totals' => [$d['dish']->id . '-' . $d['staff']->id => ['price', 'calories', 'missing_prices']]])
        ->assertJsonPath('structure.selling_price', 18)
        ->assertJsonPath('structure.limits.' . $d['category']->id . '.min', 4.5)
        ->assertJsonPath('structure.limits.' . $d['category']->id . '.max', 6);
});

test('el uso de una receta cuenta los otros ciclos y programaciones vigentes con ese plato y nivel', function () {
    $d = seedTwoLevelDish();
    $day = fn ($levelId) => ['days' => ['1' => ['dish_id' => $d['dish']->id, 'level_id' => $levelId]]];

    $service = Service::create(['name' => 'ALMUERZO', 'code' => 'ALM', 'type' => 'comedor']);
    [$s1, $s2, $s3] = collect(range(1, 3))->map(fn () => Serviceable::create([
        'serviceable_id' => $d['cafe']->id, 'serviceable_type' => Cafe::class, 'service_id' => $service->id,
    ])->id)->all();

    $current = MenuCycle::create(['serviceable_id' => $s1, 'name' => 'Abierto', 'days' => 7, 'cycle_data' => [$day($d['staff']->id)]]);
    MenuCycle::create(['serviceable_id' => $s1, 'name' => 'Otro', 'days' => 7, 'cycle_data' => [$day($d['staff']->id)]]);
    MenuCycle::create(['serviceable_id' => $s2, 'name' => 'Otro servicio', 'days' => 7, 'cycle_data' => [$day($d['staff']->id)]]);
    MenuCycle::create(['serviceable_id' => $s3, 'name' => 'Otro nivel', 'days' => 7, 'cycle_data' => [$day($d['obrero']->id)]]);

    WeeklyProgramItem::create([
        'weekly_program_id' => $d['program']->id, 'date' => now()->toDateString(), 'meal_type' => 'Almuerzo',
        'dish_category_id' => $d['category']->id, 'dish_id' => $d['dish']->id, 'level_id' => $d['staff']->id, 'percentage' => 100,
    ]);

    $this->actingAs($d['user'])
        ->getJson(route('dish-recipes.usage', ['id' => $d['recipes']['Staff']->id, 'except_cycle_id' => $current->id]))
        ->assertOk()
        ->assertJson(['other_cycles' => 2, 'services' => 2, 'active_programs' => 1]);
});
