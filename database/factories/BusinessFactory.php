<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'ruc' => $this->faker->numerify('###########'),
            'fiscal_address' => $this->faker->address,
            'legal_address' => $this->faker->address,
            'email' => $this->faker->unique()->safeEmail(),
        ];
    }
}
