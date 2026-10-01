<?php

namespace App\Imports;

use App\Models\Dish;
use App\Models\DishRecipe;
use App\Models\Ingredient;
use App\Models\Level;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importa el recetario desde el Excel extraido del sistema viejo (Tiburon).
 *
 * El archivo trae una fila por par plato-insumo. Columnas minimas:
 *
 *   cNomPlato   nombre del plato
 *   cNomProd    nombre del insumo
 *
 * Opcionales, usadas si vienen (ver database/legacy/02_exportar_recetas.sql):
 *
 *   nCantBas    cantidad en unidad base -> gross_weight
 *   nCantReq    cantidad requerida para nNumRac raciones
 *   nNumRac     raciones del plato, para derivar la porcion unitaria
 *
 * POR QUE TODO VA POR NOMBRE
 *
 * El emparejamiento es por nombre con firstOrCreate, nunca por id, y eso no es
 * un detalle de estilo. La carga anterior metio el nCodPlato del sistema viejo
 * directamente en dish_recipes.dish_id sin traducirlo: el numero cuadraba con
 * la FK por simple coincidencia de rango, asi que no fallo nada y cada receta
 * quedo colgada de un plato que no era el suyo (una infusion de menta con
 * esparragos y comino). Un emparejamiento por nombre no puede equivocarse asi:
 * si el plato no existe se crea, y si existe es porque se llama igual.
 */
class DishRecipesImport implements ToCollection, WithHeadingRow
{
    /** Platos creados en esta corrida. */
    private int $platosNuevos = 0;

    /** Insumos creados en esta corrida. */
    private int $insumosNuevos = 0;

    /** Lineas con cantidad distinta de cero. */
    private int $conCantidad = 0;

    public function collection(Collection $rows)
    {
        // El recetario importado vive en el nivel Master, la misma clave
        // (dish_id, level_id) que usa la pantalla de Platos y Recetas.
        $masterLevel = Level::firstOrCreate(['name' => 'Master']);

        $ingredientCache = [];

        $groupedDishes = $rows->groupBy(fn ($row) => trim((string) ($row['cnomplato'] ?? '')));

        foreach ($groupedDishes as $dishName => $items) {
            if ($dishName === '') {
                continue;
            }

            $dish = Dish::firstOrCreate(
                ['name' => $dishName],
                ['description' => 'Importado de Excel']
            );
            if ($dish->wasRecentlyCreated) {
                $this->platosNuevos++;
            }

            $recipe = $dish->recipes()->firstOrCreate(
                ['level_id' => $masterLevel->id],
                [
                    'name' => 'Receta ' . $dish->name,
                    'total_gross_weight' => 0,
                    'total_waste_weight' => 0,
                    'total_calories' => 0,
                    'total_cost' => 0,
                    'total_net_weight' => 0,
                ]
            );

            $ingredientsSync = [];
            foreach ($items as $item) {
                $ingredientName = trim((string) ($item['cnomprod'] ?? ''));
                if ($ingredientName === '') {
                    continue;
                }

                if (isset($ingredientCache[$ingredientName])) {
                    $ingredientId = $ingredientCache[$ingredientName];
                } else {
                    $ingredient = Ingredient::firstOrCreate(
                        ['name' => $ingredientName],
                        ['description' => '']
                    );
                    if ($ingredient->wasRecentlyCreated) {
                        $this->insumosNuevos++;
                    }
                    $ingredientId = $ingredient->id;
                    $ingredientCache[$ingredientName] = $ingredientId;
                }

                $gross = $this->cantidad($item);
                if ($gross > 0) {
                    $this->conCantidad++;
                }

                // Un insumo repetido en el mismo plato se acumula en vez de
                // pisarse: el detalle del sistema viejo lo lista mas de una vez
                // cuando se usa en pasos distintos de la preparacion, y quedarse
                // con el ultimo perderia parte de la cantidad.
                $previo = $ingredientsSync[$ingredientId]['gross_weight'] ?? 0;

                $ingredientsSync[$ingredientId] = [
                    'gross_weight'  => $previo + $gross,
                    'solid_waste'   => 0,
                    'liquid_waste'  => 0,
                    'calories'      => 0,
                    'cost'          => 0,
                    'unit_price'    => 0,
                    // net_weight arranca igual al bruto: sin dato de merma no se
                    // puede descontar nada, y dejarlo en 0 haria ver la receta
                    // como si no rindiera.
                    'net_weight'    => $previo + $gross,
                ];
            }

            if (!empty($ingredientsSync)) {
                $recipe->ingredients()->syncWithoutDetaching($ingredientsSync);

                $recipe->update([
                    'total_gross_weight' => array_sum(array_column($ingredientsSync, 'gross_weight')),
                    'total_net_weight'   => array_sum(array_column($ingredientsSync, 'net_weight')),
                ]);
            }
        }
    }

    /**
     * Cantidad bruta de una fila, en unidad base.
     *
     * Se prefiere nCantBas, que ya viene por racion. nCantReq es el total para
     * las nNumRac raciones del plato, asi que hay que dividirlo; sin nNumRac no
     * se puede normalizar y se descarta antes que cargar un numero inflado.
     */
    private function cantidad(mixed $item): float
    {
        $bas = $this->aNumero($item['ncantbas'] ?? null);
        if ($bas > 0) {
            return $bas;
        }

        $req = $this->aNumero($item['ncantreq'] ?? null);
        $rac = $this->aNumero($item['nnumrac'] ?? null);
        if ($req > 0 && $rac > 0) {
            return $req / $rac;
        }

        return 0.0;
    }

    private function aNumero(mixed $v): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }
        // Excel puede entregar "1,250.00" o "1.250,00" segun la configuracion
        // regional con la que se exporto.
        $s = preg_replace('/[^0-9,.\-]/', '', (string) $v);
        if (substr_count($s, ',') === 1 && substr_count($s, '.') === 0) {
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        return is_numeric($s) ? (float) $s : 0.0;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function resumen(): array
    {
        return [
            'platos_nuevos'  => $this->platosNuevos,
            'insumos_nuevos' => $this->insumosNuevos,
            'con_cantidad'   => $this->conCantidad,
        ];
    }
}
