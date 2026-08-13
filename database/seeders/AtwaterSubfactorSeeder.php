<?php

namespace Database\Seeders;

use App\Models\AtwaterFactor;
use App\Models\AtwaterSubfactor;
use Illuminate\Database\Seeder;

class AtwaterSubfactorSeeder extends Seeder
{
    /**
     * Excepciones al factor de carbohidrato de la fila "Huevos" (Anexo 2, nota *): algunos
     * despojos y mariscos usan un factor distinto al genérico de 3,68 kcal/g.
     */
    public function run(): void
    {
        $huevos = AtwaterFactor::where('group', 'Huevos, productos cárnicos, productos lácteos:')
            ->where('name', 'Huevos')
            ->first();

        $subfactors = [
            ['Sesos, corazón, riñón e hígado', 3.87, 16.2],
            ['Lengua, crustáceos y mariscos', 4.11, 17.2],
        ];

        foreach ($subfactors as [$name, $carbKcal, $carbKj]) {
            AtwaterSubfactor::updateOrCreate(
                ['name' => $name],
                [
                    'atwater_factor_id' => $huevos?->id,
                    'carb_kcal' => $carbKcal,
                    'carb_kj' => $carbKj,
                ],
            );
        }
    }
}
