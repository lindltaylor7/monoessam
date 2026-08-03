<?php

namespace Database\Factories;

use App\Models\Mercantil;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class MercantilFactory extends Factory
{
    protected $model = Mercantil::class;

    public function definition(): array
    {
        return [
            'name'      => $this->faker->unique()->company . ' Mercantil',
            'unit_id'   => Unit::factory(),
            'address'   => $this->faker->address(),
            'is_active' => true,
        ];
    }
}
