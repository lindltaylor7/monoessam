<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductBatchFactory extends Factory
{
    protected $model = ProductBatch::class;

    public function definition(): array
    {
        return [
            'product_id'      => Product::factory(),
            'batch_code'      => strtoupper($this->faker->unique()->bothify('LOTE-####')),
            'quantity'        => $this->faker->numberBetween(1, 50),
            'expiration_date' => $this->faker->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'received_at'     => now()->toDateString(),
            'notes'           => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn() => ['expiration_date' => now()->subDays(5)->toDateString()]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn() => ['expiration_date' => now()->addDays(3)->toDateString()]);
    }
}
