<?php

namespace Database\Factories;

use App\Models\Dealership;
use Illuminate\Database\Eloquent\Factories\Factory;

class DealershipFactory extends Factory
{
    protected $model = Dealership::class;

    public function definition(): array
    {
        die("I AM HERE IN FACTORY");
        return [
            'name' => 'Fixed Dealership',
            'ruc' => '12345678901',
            'fiscal_address' => 'Fixed Address',
            'legal_address' => 'Fixed Address',
            'phone' => '123456789',
            'email' => 'test@example.com',
        ];
    }
}
