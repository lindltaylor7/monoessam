<?php

use App\Models\Business;
use App\Models\Cafe;
use App\Models\Dinner;
use App\Models\Sale;
use App\Models\Subdealership;
use App\Models\Unit;
use App\Models\User;

test('sales page is displayed for authenticated users', function () {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);
    $user->units()->attach($unit->id);

    $this->actingAs($user)
        ->get(route('sales.index'))
        ->assertOk()
        ->assertInertia(
            fn($page) => $page
                ->component('sales/Index')
                ->has('dinners')
                ->has('cafes')
        );
});

test('can create a sale with ticket', function () {
    $user = User::factory()->create();
    $cafe = Cafe::factory()->create();
    $subdealership = Subdealership::factory()->create();
    $dinner = Dinner::factory()->create([
        'subdealership_id' => $subdealership->id,
        'cafe_id' => $cafe->id
    ]);

    // Create a business attached to cafe (needed for sale creation)
    $business = \App\Models\Business::factory()->create();
    $cafe->businesses()->attach($business->id);

    $services = [
        [
            'serviceID' => 1,
            'code' => 'S01',
            'name' => 'Lunch',
            'quantity' => 1,
            'price' => 10.00,
            'unit_price' => 10.00
        ]
    ];

    $saleData = [
        'dni' => $dinner->dni,
        'cafe_id' => $cafe->id,
        'date' => date('Y-m-d'),
        'sale_type_id' => 1,
        'double_price' => 'false',
        'receipt_type' => 1, // Generate Ticket
        'services' => json_encode($services),
    ];

    $response = $this->actingAs($user)
        ->post(route('sales.store'), $saleData);

    $response->assertStatus(200);

    $this->assertDatabaseHas('sales', [
        'dinner_id' => $dinner->id,
        'cafe_id' => $cafe->id,
        'total' => 10.00,
    ]);

    $this->assertDatabaseHas('tickets', [
        'dinner_id' => $dinner->id,
        'price_value' => 10.00,
    ]);
});

test('can fetch sales pagination', function () {
    $user = User::factory()->create();
    $cafe = Cafe::factory()->create();

    Sale::factory()->count(15)->create([
        'cafe_id' => $cafe->id,
        'date' => date('Y-m-d')
    ]);

    $this->actingAs($user)
        ->get(route('sales.pagination', ['offset' => 0, 'cafe_id' => $cafe->id]))
        ->assertOk()
        ->assertJsonCount(10);
});

test('can fetch sales report by date range', function () {
    $user = User::factory()->create();
    $date = date('Y-m-d');
    Sale::factory()->create(['date' => $date]);

    $this->actingAs($user)
        ->get(route('sales.report', ['dateInitial' => $date, 'datFinal' => $date]))
        ->assertOk()
        ->assertJsonCount(1);
});
