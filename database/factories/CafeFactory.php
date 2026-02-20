<?php

namespace Database\Factories;

use App\Models\Cafe;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class CafeFactory extends Factory
{
    protected $model = Cafe::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company . ' Cafe',
            'unit_id' => Unit::factory(),
        ];
    }
}
