<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recrea tres tablas que faltan en bases restauradas desde un dump incompleto:
 * `weekly_programs` y `weekly_program_items` (planificación de menús) y
 * `user_role_area`. Sus migraciones originales figuran como ejecutadas, así que
 * `migrate` no las vuelve a crear.
 *
 * La estructura es la definitiva —incluye las columnas que agregaron migraciones
 * posteriores (`structure_id`, `meal_type`, `percentage`)—, no la original. Las
 * foreign keys las añade 2026_09_25_170000_repair_missing_keys_and_indexes, que
 * corre a continuación y verifica que cada tabla referenciada exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('weekly_programs')) {
            Schema::create('weekly_programs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cafe_id');
                $table->foreignId('structure_id')->nullable();
                $table->string('meal_type')->nullable();
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status')->default('borrador');
                $table->foreignId('user_id');
                $table->timestamps();

                $table->index('cafe_id', 'weekly_programs_cafe_id_foreign');
                $table->index('user_id', 'weekly_programs_user_id_foreign');
                $table->index('structure_id', 'weekly_programs_structure_id_foreign');
            });
        }

        if (!Schema::hasTable('weekly_program_items')) {
            Schema::create('weekly_program_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('weekly_program_id');
                $table->date('date');
                $table->string('meal_type');
                $table->foreignId('dish_category_id');
                $table->foreignId('dish_id');
                $table->decimal('percentage', 5, 2)->default(100.00);
                $table->timestamps();

                $table->index('weekly_program_id', 'weekly_program_items_weekly_program_id_foreign');
                $table->index('dish_category_id', 'weekly_program_items_dish_category_id_foreign');
                $table->index('dish_id', 'weekly_program_items_dish_id_foreign');
            });
        }

        if (!Schema::hasTable('user_role_area')) {
            Schema::create('user_role_area', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable();
                $table->foreignId('role_id')->nullable();
                $table->foreignId('area_id')->nullable();
                $table->timestamps();

                $table->index('user_id', 'user_role_area_user_id_foreign');
                $table->index('role_id', 'user_role_area_role_id_foreign');
                $table->index('area_id', 'user_role_area_area_id_foreign');
            });
        }
    }

    /**
     * No se revierte: son tablas que deberían existir desde sus migraciones
     * originales, y borrarlas dejaría la base peor de como estaba.
     */
    public function down(): void
    {
        //
    }
};
