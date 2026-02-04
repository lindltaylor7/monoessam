<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Dealership;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subdealership>
 */
class SubdealershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'ruc' => $this->faker->numerify('###########'),
            'fiscal_address' => $this->faker->address(),
            'legal_address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'dealership_id' => Dealership::factory(),
        ];
    }
}
