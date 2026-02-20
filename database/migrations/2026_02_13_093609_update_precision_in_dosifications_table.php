<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dosifications', function (Blueprint $table) {
            $columns = [
                'energy',
                'water',
                'protein',
                'lipid',
                'carbohydrate',
                'fiber',
                'ash',
                'calcium',
                'phosphorus',
                'iron',
                'retinol',
                'thiamine',
                'riboflavin',
                'niacin',
                'a_asc',
                'sodium',
                'potassium',
                'magnesium',
                'zinc',
                'selenium',
                'a_folic',
                'v_b6',
                'v_e',
                'v_b12',
                'v_b9',
                'iodine',
                'cholesterol'
            ];

            foreach ($columns as $column) {
                $table->decimal($column, 11, 2)->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dosifications', function (Blueprint $table) {
            $columns = [
                'energy',
                'water',
                'protein',
                'lipid',
                'carbohydrate',
                'fiber',
                'ash',
                'calcium',
                'phosphorus',
                'iron',
                'retinol',
                'thiamine',
                'riboflavin',
                'niacin',
                'a_asc',
                'sodium',
                'potassium',
                'magnesium',
                'zinc',
                'selenium',
                'a_folic',
                'v_b6',
                'v_e',
                'v_b12',
                'v_b9',
                'iodine',
                'cholesterol'
            ];

            foreach ($columns as $column) {
                $table->decimal($column, 8, 2)->nullable()->change();
            }
        });
    }
};
