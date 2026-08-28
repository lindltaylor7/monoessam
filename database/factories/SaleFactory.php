<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Cafe;
use App\Models\Dinner;
use App\Models\Payment_method;
use App\Models\Sale;
use App\Models\Sale_type;
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
            // Sale_type y Payment_method son catálogos sin factory ni $fillable; se reutiliza
            // la primera fila existente y solo se crea una si la tabla está vacía.
            'sale_type_id' => fn() => Sale_type::query()->value('id')
                ?? Sale_type::forceCreate(['name' => 'Contrato'])->id,
            'payment_method_id' => fn() => Payment_method::query()->value('id')
                ?? Payment_method::forceCreate(['name' => 'Efectivo'])->id,
            'business_id' => Business::factory(),
            'total' => $this->faker->randomFloat(2, 10, 100),
            'total_igv' => 0,
            'status' => 1,
        ];
    }
}
