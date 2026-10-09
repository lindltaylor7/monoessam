<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara menu_cycles para recibir los 8,127 ciclos de BdTiburonR3.
 *
 * Por que cada columna:
 *
 *  meal_type            La llave del ciclico antiguo es
 *                       (nCodCampo, nTipBasProd, nTipCatMnu, nNumSem) y un ciclo
 *                       pertenece a UNA categoria de menu. menu_cycles no tenia
 *                       donde ponerla. Se guarda como varchar igual que en
 *                       menu_structures y weekly_programs, para que las tres
 *                       tablas hablen el mismo idioma.
 *
 *  legacy_cycle_number  El nNumSem original (1..354). No es una semana ni una
 *                       fecha: es el numero de version del ciclo. Sin esto no se
 *                       puede distinguir un ciclo de otro ni volver a auditar.
 *
 *  legacy_key           'campo-base-catmnu-numsem'. Unico. Hace la carga
 *                       idempotente: se puede reejecutar con upsert sin duplicar.
 *
 *  is_current           Cual de las N versiones esta en uso. Con 354 ciclos por
 *                       comedor, sin esta bandera la pantalla no sabe cual mostrar.
 *
 *  source_registered_at dFecReg del origen. Va como DATETIME y no TIMESTAMP a
 *                       proposito: el rango de TIMESTAMP es 1970-2038 y en la base
 *                       antigua aparecen fechas 1900-01-01 (default basura de SQL
 *                       Server). Con TIMESTAMP esas filas fallan al insertar.
 *
 * Tambien: serviceable_id no tenia FK ni indice, y no habia unico, asi que se
 * podian insertar ciclos huerfanos y duplicados en silencio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_cycles', function (Blueprint $table) {
            $table->string('meal_type')->nullable()->after('name');

            $table->unsignedInteger('legacy_cycle_number')->nullable()->after('days');
            $table->string('legacy_key', 64)->nullable()->after('legacy_cycle_number');
            $table->boolean('is_current')->default(false)->after('legacy_key');
            $table->dateTime('source_registered_at')->nullable()->after('is_current');

            $table->unique('legacy_key', 'menu_cycles_legacy_key_unique');
            $table->index(['serviceable_id', 'meal_type'], 'menu_cycles_serviceable_meal_index');
            $table->index(['serviceable_id', 'meal_type', 'is_current'], 'menu_cycles_current_index');

            $table->foreign('serviceable_id', 'menu_cycles_serviceable_id_foreign')
                ->references('id')->on('serviceables')
                ->nullOnDelete();
        });

        // days tenia default 7. El ciclo real es de 10 dias en el 75% de los casos
        // (y de 8 o 9 en un 23%), asi que el default enganaba. Se quita el default
        // para forzar que cada ciclo declare su duracion.
        DB::statement('ALTER TABLE `menu_cycles` MODIFY `days` INT NOT NULL');
    }

    public function down(): void
    {
        Schema::table('menu_cycles', function (Blueprint $table) {
            $table->dropForeign('menu_cycles_serviceable_id_foreign');
            $table->dropUnique('menu_cycles_legacy_key_unique');
            $table->dropIndex('menu_cycles_serviceable_meal_index');
            $table->dropIndex('menu_cycles_current_index');
            $table->dropColumn([
                'meal_type',
                'legacy_cycle_number',
                'legacy_key',
                'is_current',
                'source_registered_at',
            ]);
        });

        DB::statement('ALTER TABLE `menu_cycles` MODIFY `days` INT NOT NULL DEFAULT 7');
    }
};

/* ============================================================================
   ESQUEMA DE cycle_data  (contrato que la carga y el front tienen que respetar)
   ----------------------------------------------------------------------------
   Un ciclo = una categoria de menu, N dias, y por dia una lista de posiciones.
   Se conservan las posiciones vacias (dish_id = null), segun lo definido.

   Los ids de dish_category_id y dish_id son los NUEVOS de backendlaravel.
   Los legacy_* quedan al lado solo para auditoria y para poder reconciliar.

   {
     "schema_version": 1,
     "meal_type": "almuerzo",
     "days": 10,
     "legacy": { "campo": 305, "base": 305, "catmnu": 2, "numsem": 354 },
     "days_data": [
       {
         "day": 1,
         "slots": [
           {
             "dish_category_id": 42,
             "option": 1,
             "dish_id": 7739,
             "legacy_tipplato": 109,
             "legacy_subplato": 10901,
             "legacy_codplato": 7739
           },
           {
             "dish_category_id": 42,
             "option": 2,
             "dish_id": null,
             "legacy_tipplato": 109,
             "legacy_subplato": 10902,
             "legacy_codplato": 0
           }
         ]
       }
     ]
   }

   Reglas de transformacion:

   1. option = nTipsubpla % 100, y se valida que nTipsubpla DIV 100 = nTipPlato.
      Cuando nTipsubpla = 0 (taxonomia vieja zBase(Master)) -> option = 1.

   2. nCodPlato = 0  ->  dish_id = null  (posicion vacia, se conserva).

   3. Se descartan las filas con nTipPlato = 0: son 78,133 filas artefacto
      (nTipPlato=0, nTipsubpla=0, nCodPlato=0), una por dia, 100% vacias.

   4. days = COUNT(DISTINCT nDia) del ciclo, calculado, nunca 7 por defecto.

   5. MariaDB solo valida json_valid(). Toda la estructura de arriba se valida
      en codigo: un FormRequest o un cast, mas un validador previo a la carga.

   6. Como cycle_data es JSON, "en que ciclos aparece el plato X" deja de ser
      una consulta SQL. El sistema viejo la resolvia con PlaDProgSem. Si ese
      reporte se necesita, hace falta una tabla derivada o columnas generadas.
   ============================================================================ */