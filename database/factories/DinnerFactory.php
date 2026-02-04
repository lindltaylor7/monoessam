<?php

namespace Database\Factories;

use App\Models\Cafe;
use App\Models\Dinner;
use App\Models\Subdealership;
use Illuminate\Database\Eloquent\Factories\Factory;

class DinnerFactory extends Factory
{
    protected $model = Dinner::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'dni' => $this->faker->unique()->numerify('########'),
            'phone' => $this->faker->phoneNumber,
            'subdealership_id' => Subdealership::factory(),
            'cafe_id' => Cafe::factory(),
        ];
    }
}
