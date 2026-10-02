<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pasa a GRAMOS las cantidades del recetario que quedaron guardadas en KILOS.
 *
 * EL SINTOMA
 *
 * En food / Quebrados, la columna "Costo Base" daba S/. 0.00 en toda la receta
 * aunque el insumo tuviera precio, y el "Peso Bruto" de un plato salia 0.2575 g.
 * Una racion que pesa un cuarto de gramo no existe.
 *
 * LA CAUSA
 *
 * `dish_recipe_ingredients.gross_weight` esta en KILOS para el 95% del
 * recetario, pero TODO el modulo lo lee como gramos:
 *
 *   - Quebrados.vue muestra la columna "Mat. Prima" con el sufijo " g"
 *   - onWeightInput() calcula el costo como (cantidad / 1000) * precio_por_kilo
 *   - PlanningController hace lo mismo: gramos / 1000 * cost_price
 *
 * Asi que 0.0020 (que son 2 gramos de ajos, o sea 0.0020 Kg) se muestra como
 * "0.0020 g" y se cobra como 0.0020/1000 = 0.000002 Kg. El costo sale mil veces
 * mas chico de lo que deberia, que redondeado a dos decimales es 0.00.
 *
 * De donde sale: ImportarRecetarioCsv guarda `nCantBas` tal cual viene del
 * export de SQL Server, y ese campo esta en kilos por racion. Su propio docblock
 * lo dice ("todas a escala de kilos por racion"). El unico mecanismo que
 * convertia -- Recipe::applyPreciseQuantities(), que multiplica por 1000 y
 * expone source_unit='Kg' -- no se activa nunca: depende de la tabla `recipes`,
 * que tiene 0 filas, y ademas su condicion (round(quantity,2) == gross_weight)
 * ya no se cumple desde que gross_weight paso a decimal(12,5) y guarda el valor
 * sin redondear.
 *
 * COMO SE SEPARA LO QUE HAY QUE CONVERTIR
 *
 * Por el maximo de linea de cada receta, y la separacion resulto ser limpia:
 *
 *     maximo de linea < 1        15,946 recetas   162,368 lineas   -> KILOS
 *     maximo de linea >= 100        903 recetas     7,372 lineas   -> ya en gramos
 *     entre 1 y 100                   0 recetas
 *
 * Cero recetas en la zona gris, asi que no hay nada que adivinar. El umbral
 * va sobre el MAXIMO de la receta y no sobre cada linea suelta: dentro de una
 * misma receta conviven 0.0002 Kg de laurel y 0.96 Kg de carne, y convertir
 * linea por linea partiria la receta en dos escalas.
 *
 * Las 903 que ya estan en gramos no se tocan. Son las que entraron por otra via
 * (respaldo restaurado o edicion a mano desde la pantalla).
 *
 * QUE CAMBIA
 *
 *   dish_recipe_ingredients   gross_weight y net_weight x 1000
 *   dish_recipes              total_gross_weight y total_net_weight x 1000
 *
 * `solid_waste` y `liquid_waste` estan en 0 en todas las lineas afectadas (el
 * CSV no trae merma), asi que multiplicarlas no haria nada; se incluyen igual
 * para que el dia que traigan dato no quede media tabla en otra escala.
 *
 * `total_cost` y `calories` NO se tocan: son 0 en todo el recetario y se
 * derivan en la pantalla, no son una magnitud de peso.
 *
 * REVERSIBLE
 *
 * Dividiendo por 1000 las mismas filas. Por eso el comando marca las recetas
 * convertidas en la salida y acepta --deshacer, que aplica el factor inverso
 * sobre el mismo criterio invertido (max de linea >= 1000).
 *
 * USO
 *
 *     php artisan recetas:normalizar-escala                # simulacro
 *     php artisan recetas:normalizar-escala --ejecutar
 *     php artisan recetas:normalizar-escala --deshacer --ejecutar
 */
class NormalizarEscalaRecetario extends Command
{
    protected $signature = 'recetas:normalizar-escala
                            {--ejecutar : Aplica la conversion. Sin este flag solo informa que haria}
                            {--deshacer : Invierte la conversion (divide por 1000) sobre lo que quedo en gramos}
                            {--umbral=1 : Maximo de linea por debajo del cual la receta se considera en kilos}';

    protected $description = 'Convierte a gramos las cantidades del recetario que quedaron guardadas en kilos';

    private const FACTOR = 1000;

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $deshacer = (bool) $this->option('deshacer');
        $umbral   = (float) $this->option('umbral');

        if ($umbral <= 0) {
            $this->error('El umbral debe ser mayor que cero.');

            return self::FAILURE;
        }

        // Al deshacer, lo que hay que tocar es justo lo contrario: las recetas
        // que quedaron en gramos por una corrida anterior.
        $factor = $deshacer ? 1 / self::FACTOR : self::FACTOR;
        $corte  = $deshacer ? $umbral * self::FACTOR : $umbral;

        $this->info($deshacer
            ? 'DESHACER: dividir por ' . self::FACTOR . ' las recetas cuyo maximo de linea sea >= ' . $corte
            : 'Convertir a gramos: multiplicar por ' . self::FACTOR . ' las recetas cuyo maximo de linea sea < ' . $corte);
        $this->newLine();

        $recetas = $this->recetasAfectadas($corte, $deshacer);

        if ($recetas === []) {
            $this->info('No hay recetas en esa escala. Nada que hacer.');

            return self::SUCCESS;
        }

        $lineas = DB::table('dish_recipe_ingredients')->whereIn('dish_recipe_id', $recetas)->count();

        $this->table(
            ['Alcance', 'Cantidad'],
            [
                ['Recetas a convertir', count($recetas)],
                ['Lineas de receta a convertir', $lineas],
                ['Recetas que NO se tocan', DB::table('dish_recipes')->count() - count($recetas)],
            ]
        );

        $this->mostrarMuestra($recetas, $factor);

        if (! $ejecutar) {
            $this->newLine();
            $this->warn('SIMULACRO: no se escribio nada. Agrega --ejecutar para aplicarlo.');

            return self::SUCCESS;
        }

        $tocadas = 0;
        $filas   = 0;

        DB::transaction(function () use ($recetas, $factor, &$tocadas, &$filas) {
            foreach (array_chunk($recetas, 500) as $lote) {
                $filas += DB::table('dish_recipe_ingredients')
                    ->whereIn('dish_recipe_id', $lote)
                    ->update([
                        'gross_weight'  => DB::raw("gross_weight * {$factor}"),
                        'net_weight'    => DB::raw("net_weight * {$factor}"),
                        'solid_waste'   => DB::raw("solid_waste * {$factor}"),
                        'liquid_waste'  => DB::raw("liquid_waste * {$factor}"),
                        'updated_at'    => now(),
                    ]);

                $tocadas += DB::table('dish_recipes')
                    ->whereIn('id', $lote)
                    ->update([
                        'total_gross_weight' => DB::raw("total_gross_weight * {$factor}"),
                        'total_net_weight'   => DB::raw("total_net_weight * {$factor}"),
                        'total_waste_weight' => DB::raw("total_waste_weight * {$factor}"),
                        'updated_at'         => now(),
                    ]);
            }
        });

        $this->newLine();
        $this->info("Listo: {$filas} lineas y {$tocadas} cabeceras de receta convertidas.");
        $this->verificar();

        return self::SUCCESS;
    }

    /**
     * Recetas cuyo MAXIMO de linea cae del lado que hay que convertir.
     *
     * Se mira el maximo y no cada linea porque dentro de una misma receta
     * conviven 0.0002 de laurel y 0.96 de carne: la escala es una propiedad de
     * la receta entera, no de la linea.
     *
     * @return array<int, int>
     */
    private function recetasAfectadas(float $corte, bool $deshacer): array
    {
        return DB::table('dish_recipes as dr')
            ->join('dish_recipe_ingredients as dri', 'dri.dish_recipe_id', '=', 'dr.id')
            ->groupBy('dr.id')
            ->havingRaw($deshacer ? 'MAX(dri.gross_weight) >= ?' : 'MAX(dri.gross_weight) < ?', [$corte])
            // Una receta cuyas lineas son todas 0 no tiene escala que corregir.
            ->havingRaw('MAX(dri.gross_weight) > 0')
            ->pluck('dr.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @param  array<int, int>  $recetas */
    private function mostrarMuestra(array $recetas, float $factor): void
    {
        $ejemplo = DB::table('dish_recipe_ingredients as dri')
            ->join('ingredients as i', 'i.id', '=', 'dri.ingredient_id')
            ->join('dish_recipes as dr', 'dr.id', '=', 'dri.dish_recipe_id')
            ->join('dishes as d', 'd.id', '=', 'dr.dish_id')
            ->whereIn('dri.dish_recipe_id', array_slice($recetas, 0, 1))
            ->orderByDesc('dri.gross_weight')
            ->limit(8)
            ->get(['d.name as plato', 'i.name as insumo', 'dri.gross_weight']);

        if ($ejemplo->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line('Ejemplo -- ' . $ejemplo->first()->plato . ':');
        $this->table(
            ['Insumo', 'Antes', 'Despues'],
            $ejemplo->map(fn ($r) => [
                mb_substr($r->insumo, 0, 44),
                rtrim(rtrim(number_format((float) $r->gross_weight, 5, '.', ''), '0'), '.'),
                rtrim(rtrim(number_format((float) $r->gross_weight * $factor, 5, '.', ''), '0'), '.') . ' g',
            ])->all()
        );
    }

    /** Reporta como queda el reparto de escalas despues de convertir. */
    private function verificar(): void
    {
        $filas = DB::table('dish_recipes as dr')
            ->join('dish_recipe_ingredients as dri', 'dri.dish_recipe_id', '=', 'dr.id')
            ->groupBy('dr.id')
            ->selectRaw('MAX(dri.gross_weight) as mx')
            ->pluck('mx');

        $bajo = $filas->filter(fn ($m) => (float) $m > 0 && (float) $m < 1)->count();

        $this->newLine();
        $this->line('Recetas que siguen con todas sus lineas por debajo de 1 g: ' . $bajo);
        if ($bajo > 0) {
            $this->warn('  Revisalas: o son recetas vacias o quedo algo sin convertir.');
        }
    }
}
