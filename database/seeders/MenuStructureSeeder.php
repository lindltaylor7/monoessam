<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MenuStructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = \App\Models\Dish_category::limit(5)->get();
        if ($categories->isEmpty()) return;

        $meals = ['Desayuno', 'Almuerzo', 'Cena', 'Refrigerio'];

        foreach ($meals as $meal) {
            foreach ($categories as $index => $cat) {
                // Just take 2 categories per meal for now as default
                if ($index > 1) continue;

                \App\Models\MenuStructure::updateOrCreate([
                    'meal_type' => $meal,
                    'dish_category_id' => $cat->id,
                ], [
                    'sort_order' => $index,
                ]);
            }
        }
    }
}
