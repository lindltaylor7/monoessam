<?php

namespace Database\Factories;

use App\Models\Mercantil;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'mercantil_id' => Mercantil::factory(),
            'name'         => $this->faker->unique()->words(3, true),
            'marca'        => $this->faker->company(),
            'description'  => $this->faker->optional()->sentence(),
            'sku'          => strtoupper($this->faker->unique()->bothify('SKU-####')),
            'category'     => $this->faker->randomElement(['Bebidas', 'Snacks', 'Lácteos', 'Abarrotes']),
            'price'        => $this->faker->randomFloat(2, 1, 100),
            'stock'        => $this->faker->numberBetween(0, 50),
            'is_active'    => true,
        ];
    }
}
