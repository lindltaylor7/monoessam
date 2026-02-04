<?php

use App\Models\User;
use App\Models\Cafe;
use App\Models\Subdealership;
use App\Models\Dinner;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('debug sale creation', function () {
    try {
        \Illuminate\Support\Facades\DB::listen(function ($query) {
            var_dump("SQL: " . $query->sql);
            // var_dump($query->bindings);
        });

        $user = User::factory()->create();
        $cafe = Cafe::factory()->create();
        var_dump("Subdealership creating via Factory...");
        // $subdealership = Subdealership::factory()->create();

        var_dump("Subdealership creating via Model...");
        // Use Factory class directly to verify content
        $factory = \Database\Factories\DealershipFactory::new();
        var_dump("Factory Definition Sample: " . json_encode($factory->definition()));

        $dealership = $factory->create();
        $subdealership = \App\Models\Subdealership::create([
            'dealership_id' => $dealership->id,
            'fiscal_address' => 'Test Address',
            'legal_address' => 'Test Address 2'
        ]);
        var_dump("Subdealership created.");
        $dinner = Dinner::factory()->create([
            'subdealership_id' => $subdealership->id,
            'cafe_id' => $cafe->id
        ]);

        $business = Business::factory()->create();
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

        var_dump("Posting sale...");
        $response = $this->actingAs($user)
            ->post(route('sales.store'), $saleData);

        var_dump("Status: " . $response->status());

        if ($response->exception) {
            var_dump("EXCEPTION: " . get_class($response->exception));
            var_dump("MSG: " . substr($response->exception->getMessage(), 0, 200));
        }

        $response->assertStatus(200);
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Fixed Dealership')) {
            var_dump("USING FIXED VALUES");
        } else {
            var_dump("NOT USING FIXED VALUES");
        }
        var_dump("ERR MSG PART: " . substr($msg, 0, 200));
    }
});
