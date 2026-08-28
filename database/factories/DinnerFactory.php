<?php

namespace Database\Factories;

use App\Models\Dinner;
use App\Models\Mine;
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
            'mine_id' => Mine::factory(),
        ];
    }
}
