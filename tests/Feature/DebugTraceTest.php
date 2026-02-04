<?php

use App\Models\User;
use App\Models\Unit;
use App\Models\Cafe;

test('debug trace', function () {
    try {
        $user = User::factory()->create();
        var_dump("User created id=" . $user->id);
        $unit = Unit::factory()->create();
        var_dump("Unit created id=" . $unit->id);

        $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);

        var_dump("Attaching unit via DB...");
        \Illuminate\Support\Facades\DB::table('user_units')->insert([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        var_dump("Attached unit.");

        var_dump("Calling route");
        $response = $this->actingAs($user)->get(route('dinners.index'));
        var_dump("Route call finished. Status: " . $response->status());

        if ($response->exception) {
            var_dump("RESPONSE EXCEPTION: " . get_class($response->exception));
            var_dump("MSG: " . $response->exception->getMessage());
        }

        $response->assertOk();
        var_dump("Assert OK finished");
    } catch (\Illuminate\Database\QueryException $e) {
        var_dump("QUERY EXCEPTION: " . $e->getMessage());
        var_dump("SQL: " . $e->getSql());
    } catch (\Throwable $e) {
        var_dump("EXCEPTION TYPE: " . get_class($e));
        var_dump("MSG: " . $e->getMessage());
    }
});
