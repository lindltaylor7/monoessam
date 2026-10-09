<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Vacia el recetario MASTER para poder reimportarlo desde cero.
 *
 * POR QUE HACE FALTA UN COMANDO Y NO BASTA CON REIMPORTAR ENCIMA
 *
 * DishRecipesImport no reemplaza: acumula. Dos razones concretas:
 *
 *   1. syncWithoutDetaching() no pisa una fila que ya existe en el pivote.
 *      Si la carga anterior dejo (receta 500, insumo 12) con gross_weight 3,
 *      la nueva importacion con gross_weight 0.5 NO lo corrige: se queda el 3.
 *
 *   2. Dentro de una misma corrida, un insumo repetido se suma a proposito
 *      ($previo + $gross) porque el detalle de Tiburon lista el mismo producto
 *      en pasos distintos de la preparacion. Correcto dentro de una corrida,
 *      veneno entre corridas.
 *
 * El resultado de reimportar sin limpiar es un recetario con cantidades
 * infladas que se ve perfectamente normal al abrirlo. Por eso esto va primero.
 *
 * QUE BORRA Y QUE NO
 *
 * Borra SOLO el nivel MASTER, que es donde cae todo lo importado:
 *
 *     dish_recipe_ingredients   las lineas de receta
 *     dish_recipe_levels        asignaciones de nivel por receta
 *     dish_recipes              la cabecera
 *
 * NO borra dishes ni ingredients, y no es por prudencia: son el catalogo vivo
 * del sistema. Hay FKs apuntando a ellos desde weekly_program_items,
 * purchase_order_items, dosifications, nutritional_factors,
 * ingredient_city_providers y dish_category_dish. Vaciarlos se llevaria
 * programaciones semanales y ordenes de compra por delante. Ademas no hace
 * falta: el importador los empareja por nombre con firstOrCreate, asi que
 * reutiliza el que ya existe y solo da de alta lo que de verdad es nuevo.
 *
 * NO borra las recetas de los otros niveles (STAFF, EMPLEADO, OBRERO). Esas
 * las arman los usuarios en la pantalla de Platos y Recetas y no salen de
 * ninguna importacion; al 30-09 son 14 recetas con 30 lineas. Para llevarselas
 * tambien hay que pedirlo explicito con --todos-los-niveles.
 *
 * USO
 *
 *     php artisan recetas:limpiar                      # simulacro, no borra
 *     php artisan recetas:limpiar --ejecutar           # borra el nivel MASTER
 *     php artisan recetas:limpiar --ejecutar --todos-los-niveles
 */
class LimpiarRecetario extends Command
{
    protected $signature = 'recetas:limpiar
                            {--ejecutar : Aplica el borrado. Sin este flag solo informa que haria}
                            {--todos-los-niveles : Tambien borra las recetas hechas a mano en STAFF/EMPLEADO/OBRERO}';

    protected $description = 'Vacia el recetario MASTER para poder reimportar el Excel de Tiburon sin acumular cantidades';

    public function handle(): int
    {
        $ejecutar  = (bool) $this->option('ejecutar');
        $todos     = (bool) $this->option('todos-los-niveles');

        // El nivel se busca sin distinguir mayusculas a proposito: la tabla
        // tiene 'MASTER' y el importador hace firstOrCreate(['name' => 'Master']).
        // En MySQL coinciden por collation, en SQLite (los tests) no.
        $master = DB::table('levels')->whereRaw('LOWER(name) = ?', ['master'])->first();

        if (!$master && !$todos) {
            $this->error('No existe el nivel MASTER en la tabla levels. Nada que limpiar.');

            return self::FAILURE;
        }

        $recetas = DB::table('dish_recipes');
        if (!$todos) {
            $recetas->where('level_id', $master->id);
        }

        $idsRecetas = $recetas->pluck('id');

        if ($idsRecetas->isEmpty()) {
            $this->info('El recetario ya esta vacio. No hay nada que borrar.');

            return self::SUCCESS;
        }

        // Se cuenta antes de borrar para poder informar, y para que el simulacro
        // muestre exactamente los mismos numeros que mostraria la corrida real.
        $conteos = [
            'dish_recipe_ingredients' => DB::table('dish_recipe_ingredients')->whereIn('dish_recipe_id', $idsRecetas)->count(),
            'dish_recipe_levels'      => DB::table('dish_recipe_levels')->whereIn('dish_recipe_id', $idsRecetas)->count(),
            'dish_recipes'            => $idsRecetas->count(),
        ];

        $this->newLine();
        $this->line($todos
            ? 'Alcance: TODOS los niveles'
            : 'Alcance: solo nivel MASTER (id ' . $master->id . ')');
        $this->newLine();

        $this->table(['Tabla', 'Filas a borrar'], collect($conteos)->map(
            fn ($n, $tabla) => [$tabla, number_format($n)]
        )->values()->all());

        // Lo que se conserva se informa siempre: un comando de borrado que solo
        // dice cuanto borro deja al que lo corre sin forma de notar que algo
        // que creia incluido quedo afuera.
        $conservadas = $todos
            ? 0
            : DB::table('dish_recipes')->where('level_id', '!=', $master->id)->count();

        $this->line('Se conservan:');
        $this->line('  dishes                  ' . number_format(DB::table('dishes')->count()) . '   (catalogo, con FKs desde programacion y compras)');
        $this->line('  ingredients             ' . number_format(DB::table('ingredients')->count()) . '   (idem; el importador los reutiliza por nombre)');
        $this->line('  dish_recipes no-MASTER  ' . number_format($conservadas) . '   (recetas hechas a mano en la app)');
        $this->newLine();

        if (!$ejecutar) {
            $this->warn('SIMULACRO: no se borro nada. Agregar --ejecutar para aplicar.');

            return self::SUCCESS;
        }

        if ($todos && !$this->confirm('--todos-los-niveles se lleva tambien las recetas hechas a mano por usuarios. Continuar?', false)) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        // Orden obligado por las FKs: primero las hijas, al final la cabecera.
        // Todo en una transaccion para no dejar el recetario a medio borrar si
        // algo revienta a la mitad.
        DB::transaction(function () use ($idsRecetas) {
            // Los ids van en lotes porque un whereIn con 10,886 elementos arma
            // una sentencia que puede pasarse de max_allowed_packet.
            foreach ($idsRecetas->chunk(2000) as $lote) {
                $ids = $lote->all();
                DB::table('dish_recipe_ingredients')->whereIn('dish_recipe_id', $ids)->delete();
                DB::table('dish_recipe_levels')->whereIn('dish_recipe_id', $ids)->delete();
                DB::table('dish_recipes')->whereIn('id', $ids)->delete();
            }
        });

        $this->info('Listo. Recetario limpio.');
        $this->newLine();
        $this->line('Siguiente paso: subir el Excel por Platos y Recetas > Importar.');
        $this->line('El importador reporta platos nuevos, insumos nuevos y lineas con cantidad;');
        $this->line('si "lineas con cantidad" sale muy por debajo del total, el Excel se exporto');
        $this->line('sin la columna nCantBas y hay que volver a sacarlo.');

        return self::SUCCESS;
    }
}
