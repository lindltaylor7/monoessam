<?php

namespace App\Imports;

use App\Models\Dish;
use App\Models\Ingredient;
use App\Models\Level;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
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
 * El resto de columnas que exporta el SQL (nTipUndBas, nVolumen, nCodPlato,
 * nCodProd) viajan como trazabilidad para auditar a mano; aqui no se leen.
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
 *
 * POR QUE SE LEE POR BLOQUES
 *
 * Antes esto era ToCollection a secas y reventaba con un 500 sin dejar nada en
 * el log, porque un fatal de memoria no pasa por el handler de Laravel. Medido
 * con el volumen real del recetario (169,737 filas): 156 MB solo la Collection
 * de Collections, 168 MB despues del groupBy, y encima PhpSpreadsheet con 1.5
 * millones de celdas. Contra un memory_limit de 512M no habia forma.
 *
 * WithChunkReading mantiene el uso de memoria plano, pero obliga a cambiar como
 * se escribe el pivote (ver mas abajo).
 *
 * EL PROBLEMA QUE INTRODUCE LEER POR BLOQUES, Y COMO SE RESUELVE
 *
 * syncWithoutDetaching() no toca una fila del pivote que ya existe. Con una
 * sola pasada eso daba igual, pero con bloques un plato que cae justo en el
 * corte se procesa dos veces: el segundo pedazo se descartaria entero y la
 * receta quedaria con las cantidades incompletas, sin ningun error visible.
 *
 * La solucion es $acumulado: un mapa [recipe_id][ingredient_id] => cantidad con
 * lo que YA escribio esta corrida. La regla de escritura sale de ahi:
 *
 *   - par que no esta en el mapa  -> se escribe tal cual, pisando lo que
 *     hubiera de una corrida anterior. Hace la importacion repetible.
 *   - par que ya esta en el mapa  -> se suma. Es el caso legitimo: el detalle
 *     de Tiburon lista el mismo producto varias veces cuando se usa en pasos
 *     distintos de la preparacion, y quedarse con el ultimo perderia cantidad.
 *
 * Asi el corte de bloque deja de importar: el resultado es el mismo sin que
 * importe donde caiga.
 */
class DishRecipesImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    /** Platos creados en esta corrida. */
    private int $platosNuevos = 0;

    /** Insumos creados en esta corrida. */
    private int $insumosNuevos = 0;

    /** Lineas con cantidad distinta de cero. */
    private int $conCantidad = 0;

    /** nombre del insumo => ingredients.id. Evita releer la tabla por fila. */
    private array $ingredientCache = [];

    /** nombre del plato => dish_recipes.id del nivel MASTER. */
    private array $recipeCache = [];

    /** [recipe_id][ingredient_id] => cantidad escrita por esta corrida. */
    private array $acumulado = [];

    /** levels.id de MASTER, resuelto una sola vez para toda la corrida. */
    private ?int $masterLevelId = null;

    public function collection(Collection $rows)
    {
        $masterLevelId = $this->masterLevelId();
        $ahora = now();

        $groupedDishes = $rows->groupBy(fn ($row) => trim((string) ($row['cnomplato'] ?? '')));

        foreach ($groupedDishes as $dishName => $items) {
            if ($dishName === '') {
                continue;
            }

            $recipeId = $this->recipeId($dishName, $masterLevelId);

            $filas = [];
            foreach ($items as $item) {
                $ingredientName = trim((string) ($item['cnomprod'] ?? ''));
                if ($ingredientName === '') {
                    continue;
                }

                $ingredientId = $this->ingredientId($ingredientName);

                $gross = $this->cantidad($item);
                if ($gross > 0) {
                    $this->conCantidad++;
                }

                // Aqui vive la regla explicada en el docblock: si el par ya se
                // escribio en esta corrida se suma, si no se arranca de cero y
                // se pisa lo que hubiera de antes.
                $previo = $this->acumulado[$recipeId][$ingredientId] ?? 0.0;
                $total  = $previo + $gross;

                $this->acumulado[$recipeId][$ingredientId] = $total;

                $filas[$ingredientId] = [
                    'dish_recipe_id' => $recipeId,
                    'ingredient_id'  => $ingredientId,
                    'gross_weight'   => $total,
                    'solid_waste'    => 0,
                    'liquid_waste'   => 0,
                    'calories'       => 0,
                    'cost'           => 0,
                    'unit_price'     => 0,
                    // net_weight arranca igual al bruto: sin dato de merma no se
                    // puede descontar nada, y dejarlo en 0 haria ver la receta
                    // como si no rindiera.
                    'net_weight'     => $total,
                    'created_at'     => $ahora,
                    'updated_at'     => $ahora,
                ];
            }

            if (empty($filas)) {
                continue;
            }

            // upsert contra el indice unico (dish_recipe_id, ingredient_id) que
            // crea la migracion 2026_09_30_210000. Se mandan los totales ya
            // acumulados, asi que reescribir la fila es lo correcto.
            DB::table('dish_recipe_ingredients')->upsert(
                array_values($filas),
                ['dish_recipe_id', 'ingredient_id'],
                ['gross_weight', 'net_weight', 'updated_at']
            );

            // Los totales se recalculan desde el mapa y no desde $filas, porque
            // $filas solo tiene los insumos de ESTE bloque: un plato partido en
            // el corte dejaria la cabecera con el total de su ultimo pedazo.
            $sumaGross = array_sum($this->acumulado[$recipeId]);

            DB::table('dish_recipes')->where('id', $recipeId)->update([
                'total_gross_weight' => $sumaGross,
                'total_net_weight'   => $sumaGross,
                'updated_at'         => $ahora,
            ]);
        }
    }

    /**
     * Tamano de bloque. 2,000 filas deja el pico de memoria en unos pocos MB.
     *
     * Subirlo acelera el XLSX (chunk reading vuelve a abrir el archivo en cada
     * pasada, asi que menos bloques es menos parseos) a cambio de mas memoria.
     * Con CSV el costo por pasada es bajo y no hace falta tocarlo.
     */
    public function chunkSize(): int
    {
        return 2000;
    }

    /**
     * El recetario importado vive en el nivel MASTER, la misma clave
     * (dish_id, level_id) que usa la pantalla de Platos y Recetas.
     *
     * La busqueda ignora mayusculas porque la tabla tiene 'MASTER' y antes aqui
     * decia firstOrCreate(['name' => 'Master']): en MySQL coincidian por
     * collation, pero en SQLite (donde corren los tests) se creaba un quinto
     * nivel duplicado y el recetario entraba colgado de el.
     */
    private function masterLevelId(): int
    {
        if ($this->masterLevelId === null) {
            $level = Level::whereRaw('LOWER(name) = ?', ['master'])->first()
                ?? Level::create(['name' => 'MASTER']);

            $this->masterLevelId = (int) $level->id;
        }

        return $this->masterLevelId;
    }

    private function recipeId(string $dishName, int $levelId): int
    {
        if (isset($this->recipeCache[$dishName])) {
            return $this->recipeCache[$dishName];
        }

        $dish = Dish::firstOrCreate(
            ['name' => $dishName],
            ['description' => 'Importado de Excel']
        );
        if ($dish->wasRecentlyCreated) {
            $this->platosNuevos++;
        }

        $recipe = $dish->recipes()->firstOrCreate(
            ['level_id' => $levelId],
            [
                'name' => 'Receta ' . $dish->name,
                'total_gross_weight' => 0,
                'total_waste_weight' => 0,
                'total_calories' => 0,
                'total_cost' => 0,
                'total_net_weight' => 0,
            ]
        );

        return $this->recipeCache[$dishName] = (int) $recipe->id;
    }

    private function ingredientId(string $name): int
    {
        if (isset($this->ingredientCache[$name])) {
            return $this->ingredientCache[$name];
        }

        $ingredient = Ingredient::firstOrCreate(
            ['name' => $name],
            ['description' => '']
        );
        if ($ingredient->wasRecentlyCreated) {
            $this->insumosNuevos++;
        }

        return $this->ingredientCache[$name] = (int) $ingredient->id;
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
