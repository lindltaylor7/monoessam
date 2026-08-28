<?php

use App\Models\Business;
use App\Models\Cafe;
use App\Models\Dinner;
use App\Models\Mine;
use App\Models\Sale;
use App\Models\Sale_type;
use App\Models\Service;
use App\Models\Subdealership;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
    ]);

    // El controlador guarda sale_type_id tal cual llega, y la FK exige que exista.
    $saleType = Sale_type::forceCreate(['name' => 'Contrato']);

    // Create a business attached to cafe (needed for sale creation)
    $business = Business::factory()->create();
    $cafe->businesses()->attach($business->id);

    // ticket_details.service_id tiene FK contra services.
    $service = Service::create([
        'code' => 'S01',
        'name' => 'Lunch',
        'description' => 'Almuerzo',
    ]);

    $services = [
        [
            'serviceID' => $service->id,
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
        'sale_type_id' => $saleType->id,
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

    // El endpoint paginado de ventas vive bajo el segmento `dinners`.
    $this->actingAs($user)
        ->get(route('dinners.pagination', ['offset' => 0, 'cafe_id' => $cafe->id]))
        ->assertOk()
        ->assertJsonCount(10);
});

test('can fetch sales report by date range', function () {
    $unit = Unit::factory()->create();
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);
    $user = User::factory()->create();
    $user->units()->attach($unit->id);

    $date = date('Y-m-d');
    Sale::factory()->create(['cafe_id' => $cafe->id, 'date' => $date]);

    // sales.report recibe startDate/endDate como parámetros de ruta, no query string.
    $this->actingAs($user)
        ->get(route('sales.report', ['startDate' => $date, 'endDate' => $date]))
        ->assertOk()
        ->assertJsonCount(1);
});
