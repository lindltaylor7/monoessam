<?php

namespace Database\Factories;

use App\Models\Cafe;
use App\Models\Dinner;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'dinner_id' => Dinner::factory(),
            'cafe_id' => Cafe::factory(),
            'date' => $this->faker->date(),
            'sale_type_id' => 1, // Assuming defaults
            'payment_method_id' => 1, // Assuming defaults
            'business_id' => 1, // Need to handle this relation if strict
            'total' => $this->faker->randomFloat(2, 10, 100),
            'total_igv' => 0,
            'status' => 1,
            // 'user_id' => User::factory(), // If nullable or handled
        ];
    }
}
