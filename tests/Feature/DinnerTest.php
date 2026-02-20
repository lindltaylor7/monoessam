<?php

use App\Models\Cafe;
use App\Models\Dinner;
use App\Models\Unit;
use App\Models\User;
use App\Models\Subdealership;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dinners page is displayed for authenticated users', function () {
    $user = User::factory()->create();
    $unit = Unit::factory()->create();
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);
    $user->units()->attach($unit->id);

    $this->actingAs($user)
        ->get(route('dinners.index'))
        ->assertOk()
        ->assertInertia(
            fn($page) => $page
                ->component('dinners/Index')
                ->has('dinners')
                ->has('cafes')
        );
});

test('can create a dinner', function () {
    $user = User::factory()->create();
    $cafe = Cafe::factory()->create();
    $subdealership = Subdealership::factory()->create();

    $dinnerData = [
        'name' => 'John Doe',
        'dni' => '12345678',
        'phone' => '987654321',
        'subdealership_id' => $subdealership->id,
        'cafe_id' => $cafe->id,
    ];

    $this->actingAs($user)
        ->post(route('dinners.store'), $dinnerData)
        ->assertRedirect(); // Back

    $this->assertDatabaseHas('dinners', [
        'name' => 'John Doe',
        'dni' => '12345678',
    ]);
});

test('can update a dinner', function () {
    $user = User::factory()->create();
    $dinner = Dinner::factory()->create();

    $updatedData = [
        'name' => 'Jane Doe',
        'dni' => $dinner->dni,
        'phone' => '111222333',
        'subdealership_id' => $dinner->subdealership_id,
        'cafe_id' => $dinner->cafe_id,
    ];

    $this->actingAs($user)
        ->put(route('dinners.update', $dinner->id), $updatedData)
        ->assertRedirect();

    $this->assertDatabaseHas('dinners', [
        'id' => $dinner->id,
        'name' => 'Jane Doe',
        'phone' => '111222333',
    ]);
});

test('can delete a dinner', function () {
    $user = User::factory()->create();
    $dinner = Dinner::factory()->create();

    $this->actingAs($user)
        ->delete(route('dinners.destroy', $dinner->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('dinners', [
        'id' => $dinner->id,
    ]);
});

test('can search for a dinner', function () {
    $user = User::factory()->create();
    $cafe = Cafe::factory()->create();
    $dinner = Dinner::factory()->create([
        'name' => 'UniqueName',
        'cafe_id' => $cafe->id
    ]);

    // Create another dinner to ensure filter works
    Dinner::factory()->create(['name' => 'OtherName', 'cafe_id' => $cafe->id]);

    $response = $this->actingAs($user)
        ->get(route('dinners.search', ['word' => 'Unique', 'id' => $cafe->id]));

    $response->assertOk();
    // Search returns JSON array
    $this->assertEquals(1, count($response->json()));
    $this->assertEquals('UniqueName', $response->json()[0]['name']);
});
