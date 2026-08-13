<?php

namespace Database\Seeders;

use App\Models\AtwaterFactor;
use Illuminate\Database\Seeder;

class AtwaterFactorSeeder extends Seeder
{
    /**
     * Factores específicos Atwater para alimentos (FAO 2005, Food and Nutrition Paper N° 77, Anexo 2).
     */
    public function run(): void
    {
        $groups = [
            'Huevos, productos cárnicos, productos lácteos:' => [
                ['Huevos', 4.36, 18.2, 9.02, 37.7, 3.68, 15.4, null],
                // "Carne/pescado" se conserva como categoría general (sin CHO propio) para
                // ingredientes que no encajan en ninguno de los tipos específicos de abajo.
                ['Carne/pescado', 4.27, 17.9, 9.02, 37.7, null, null, null],
                // Desglose específico: misma proteína/grasa que Carne/pescado, CHO disponible propio.
                ['Cerdo, carne sin hueso', 4.27, 17.9, 9.02, 37.7, 3.87, 16.2, null],
                ['Res, carne', 4.27, 17.9, 9.02, 37.7, 3.87, 16.2, null],
                ['Cordero, carne', 4.27, 17.9, 9.02, 37.7, 3.87, 16.2, null],
                ['Pescado', 4.27, 17.9, 9.02, 37.7, 3.87, 16.2, null],
                ['Sesos, corazón, riñón e hígado', 4.27, 17.9, 9.02, 37.7, 3.87, 16.2, null],
                ['Lengua', 4.27, 17.9, 9.02, 37.7, 4.11, 17.2, null],
                ['Crustáceos', 4.27, 17.9, 9.02, 37.7, 4.11, 17.2, null],
                ['Mariscos', 4.27, 17.9, 9.02, 37.7, 4.11, 17.2, null],
                ['Leche/ productos lácteos', 4.27, 17.9, 8.79, 36.8, 3.87, 16.2, null],
            ],
            'Grasas y aceites:' => [
                ['Mantequilla', 4.27, 17.9, 8.79, 36.8, 3.87, 16.2, null],
                ['Margarina, vegetal', 4.27, 17.9, 8.84, 37.0, 3.87, 16.2, null],
                ['Otras grasas y aceites vegetales', null, null, 8.84, 37.0, null, null, null],
            ],
            'Frutas:' => [
                ['Todas, excepto limones y limas', 3.36, 14.1, 8.37, 35.0, 3.60, 15.1, null],
                ['Jugo de fruta, excepto limón y lima#', 3.36, 14.1, 8.37, 35.0, 3.92, 16.4, null],
                ['Limón, limas', 3.36, 14.1, 8.37, 35.0, 2.48, 10.4, null],
                ['Jugo de limón, jugo de lima#', 3.36, 14.1, 8.37, 35.0, 2.70, 11.3, null],
            ],
            'Cereales y productos:' => [
                ['Cebada, perlada', 3.55, 14.9, 8.37, 35.0, 3.95, 16.5, null],
                ['Harina de maíz, integral', 2.73, 11.4, 8.37, 35.0, 4.03, 16.9, null],
                ['Fideos, macarrones, espagueti', 3.91, 16.4, 8.37, 35.0, 4.12, 17.2, null],
                ['Avena y harina de avena', 3.46, 14.5, 8.37, 35.0, 4.12, 17.2, null],
                ['Arroz, integral', 3.41, 14.3, 8.37, 35.0, 4.12, 17.2, null],
                ['Arroz, blanco o pulido', 3.82, 16.0, 8.37, 35.0, 4.16, 17.4, null],
                ['Centeno, harina integral', 3.05, 12.8, 8.37, 35.0, 3.86, 16.2, null],
                ['Centeno, harina ligera', 3.41, 14.3, 8.37, 35.0, 4.07, 17.0, null],
                ['Sorgo, harina integral', 0.91, 3.8, 8.37, 35.0, 4.03, 16.9, null],
                ['Trigo, 97 – 100 % extracción', 3.59, 15.0, 8.37, 35.0, 3.78, 15.8, null],
                ['Trigo, 70 – 74 % extracción', 4.05, 17.0, 8.37, 35.0, 4.12, 17.2, null],
                ['Otros cereales – refinados', 3.87, 16.2, 8.37, 35.0, 4.12, 17.2, null],
            ],
            'Leguminosas, nueces:' => [
                ['Frejoles maduros secos, arvejas, nueces', 3.47, 14.5, 8.37, 35.0, 4.07, 17.0, null],
                ['Soya', 3.47, 14.5, 8.37, 35.0, 4.07, 17.0, null],
            ],
            'Vegetales:' => [
                ['Papas, almidón de raíces', 2.78, 11.6, 8.37, 35.0, 4.03, 16.9, null],
                ['Otras raíces y tubérculos', 2.78, 11.6, 8.37, 35.0, 3.84, 16.1, null],
                ['Otros vegetales', 2.44, 10.2, 8.37, 35.0, 3.57, 14.9, null],
            ],
        ];

        $order = 0;

        foreach ($groups as $group => $rows) {
            foreach ($rows as [$name, $proteinKcal, $proteinKj, $fatKcal, $fatKj, $carbKcal, $carbKj, $footnote]) {
                $order++;

                AtwaterFactor::updateOrCreate(
                    ['group' => $group, 'name' => $name],
                    [
                        'order' => $order,
                        'protein_kcal' => $proteinKcal,
                        'protein_kj' => $proteinKj,
                        'fat_kcal' => $fatKcal,
                        'fat_kj' => $fatKj,
                        'carb_kcal' => $carbKcal,
                        'carb_kj' => $carbKj,
                        'footnote' => $footnote,
                    ],
                );
            }
        }
    }
}
