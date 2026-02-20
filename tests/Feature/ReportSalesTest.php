<?php

use App\Models\Cafe;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;

test('report sales page loads with correct statistics', function () {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);
    $user->units()->attach($unit->id);

    // Create sales for this cafe
    Sale::factory()->count(5)->create([
        'cafe_id' => $cafe->id,
        'date' => date('Y-m-d'),
        'total' => 100.00
    ]);

    $this->actingAs($user)
        ->get(route('reportsales.index'))
        ->assertOk()
        ->assertInertia(
            fn($page) => $page
                ->component('reportsales/Index')
                ->has('sales')
                ->has(
                    'statistics',
                    fn($stats) => $stats
                        ->where('total_sales', 5)
                        ->where('total_amount', 500.00)
                        ->etc()
                )
        );
});

test('can delete a sale if user has permission', function () {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);
    $user->units()->attach($unit->id);

    $sale = Sale::factory()->create([
        'cafe_id' => $cafe->id
    ]);

    $this->actingAs($user)
        ->delete(route('reportsales.destroy', $sale->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
});

test('cannot delete a sale if user does not own cafe', function () {
    $user = User::factory()->create();
    // User has no units/cafes assigned

    $sale = Sale::factory()->create(); // Random cafe

    $this->actingAs($user)
        ->delete(route('reportsales.destroy', $sale->id))
        ->assertRedirect(); // Should redirect back with error

    $this->assertDatabaseHas('sales', ['id' => $sale->id]);
});
