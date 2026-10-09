<?php

namespace App\Console\Commands;

use App\Support\DumpSqlReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trae del dump toda la cadena que hace falta para costear un plato:
 * proveedores, unidades de medida, ciudades y la tabla de precios
 * `ingredient_city_providers`, reenganchada a los ingredientes VIVOS.
 *
 * DE DONDE SALE EL COSTO DE UN PLATO
 *
 * PlanningController (pantalla de food / Quebrados) lo calcula asi:
 *
 *     gramos_del_insumo / 1000 * cost_price
 *
 * donde cost_price es el MAS BARATO registrado para ese insumo EN LA CIUDAD
 * elegida (`Ingredient_city_provider::where('city_id', ...)` y despues min()).
 * O sea: sin filas en `ingredient_city_providers` para la ciudad que se esta
 * mirando, el costo sale en blanco aunque el recetario este completo. Esa tabla
 * es la pieza que faltaba.
 *
 * Importante: el calculo NO filtra por `is_active`. Alcanza con que la fila
 * exista y tenga precio.
 *
 * EL ORDEN DE LAS TABLAS NO ES NEGOCIABLE
 *
 * `ingredient_city_providers` tiene cuatro FKs (ingredient, provider, city,
 * measurement_unit). Hay que poblar los cuatro catalogos antes de la tabla de
 * precios, y traducir los cuatro ids en el camino. Un provider_id del dump que
 * apunte a otro proveedor en la base viva no da error: deja el precio de las
 * verduras colgado del carnicero.
 *
 * DOS ESTRATEGIAS DE ID, SEGUN COMO ESTE LA TABLA DESTINO
 *
 *   tabla VACIA      se insertan los ids del dump tal cual. Es lo que pasa hoy
 *                    con `providers` y `measurement_units` (0 filas, y ninguna
 *                    tabla con datos las referencia). Preservar el id evita una
 *                    capa de traduccion y deja respaldo y base alineados.
 *
 *   tabla CON FILAS  se mapea por nombre y se da de alta solo lo que falta, con
 *                    id nuevo. Es el caso de `cities`: la base viva tiene 8
 *                    (LIMA, HUANCAYO, AREQUIPA, ...) y el dump 2. Da la
 *                    casualidad de que LIMA y HUANCAYO coinciden en id, pero el
 *                    comando no se apoya en esa casualidad: traduce igual.
 *
 * `measurement_units` se mapea por nombre + abreviatura, no por nombre solo,
 * porque el catalogo del dump trae nombres repetidos a proposito ('Botella' es
 * 11 y 12, 'Caja' es 14 y 15) y por nombre solo serian ambiguos.
 *
 * POR QUE SON DOS TRANSACCIONES Y NO UNA
 *
 * Un catalogo que entra con id nuevo (tabla destino ya poblada) recien revela su
 * id al insertarse, y ese id hace falta para armar las filas de precio. Asi que
 * primero se cierran los catalogos y despues se arman los precios con el mapa ya
 * resuelto. Si fallara entre las dos fases quedan catalogos sin precios, que no
 * rompe nada y se arregla volviendo a correr el comando: es idempotente, la
 * segunda vez encuentra los catalogos existentes y solo inserta lo que falta.
 *
 * EL PUENTE DE INGREDIENTES ES EL NOMBRE
 *
 * Igual que en dosificaciones:importar: `ingredients.code` esta NULL en toda la
 * base viva, asi que el unico dato comun es el nombre normalizado
 * (DumpSqlReader::normalizar). De las 2134 filas de precios del dump, 2120
 * encuentran insumo vivo; las 14 restantes son insumos que ya no existen y se
 * listan con --reporte en lugar de inventarles una pareja.
 *
 * Si dos filas vivas comparten nombre normalizado son el mismo insumo duplicado
 * en el catalogo, y las dos reciben el precio: 2134 filas del dump terminan en
 * 2336 filas vivas.
 *
 * QUE NO PISA
 *
 * Nada. Las filas de precio se identifican por (insumo, proveedor, ciudad); si
 * esa combinacion ya existe viva se deja como esta -- alguien pudo haber
 * corregido el precio a mano en la pantalla de Proveedores despues de la fecha
 * del respaldo. Con --pisar se actualizan cost_price, measurement_unit_id e
 * is_active de las que ya existen.
 *
 * LO QUE ESTE COMANDO NO PUEDE ARREGLAR
 *
 * 972 de las 2134 filas del dump tienen cost_price NULL: nunca se les cargo
 * precio. Por eso, de los insumos que el recetario usa de verdad, quedan con
 * precio alrededor del 72% y no el 100%. El resto hay que cargarlo en la
 * pantalla de Proveedores; --reporte-sin-precio los lista.
 *
 * USO
 *
 *     php artisan precios:importar C:/ruta/backendlaravel.sql
 *     php artisan precios:importar C:/ruta/backendlaravel.sql --ejecutar
 *     php artisan precios:importar dump.sql --ejecutar --pisar
 *     php artisan precios:importar dump.sql --reporte=storage/app/sin-pareja.csv \
 *                                           --reporte-sin-precio=storage/app/sin-precio.csv
 */
class ImportarPreciosInsumos extends Command
{
    protected $signature = 'precios:importar
                            {ruta : Ruta del dump .sql con providers, measurement_units, cities e ingredient_city_providers}
                            {--ejecutar : Aplica los cambios. Sin este flag solo informa que haria}
                            {--pisar : Actualiza precio/unidad/estado de las asignaciones que ya existen}
                            {--reporte= : CSV con las filas de precio cuyo insumo no existe en la base viva}
                            {--reporte-sin-precio= : CSV con los insumos del recetario que quedan sin precio}';

    protected $description = 'Importa proveedores, unidades, ciudades y precios de insumos desde un dump, reenganchando los ids a la base viva';

    private const LOTE = 500;

    /** Catalogos a resolver antes de los precios, en orden de dependencia. */
    private const CATALOGOS = ['providers', 'measurement_units', 'cities'];

    public function handle(): int
    {
        $ruta     = (string) $this->argument('ruta');
        $ejecutar = (bool) $this->option('ejecutar');

        $lector = new DumpSqlReader($ruta);
        if (! $lector->existe()) {
            $this->error("No existe el archivo: {$ruta}");

            return self::FAILURE;
        }

        $this->info('Dump: ' . $ruta . ' (' . DumpSqlReader::humano($lector->tamano()) . ')');
        $this->newLine();

        // Una sola pasada por los 38 MB para las seis tablas.
        $dump = $lector->leerTodas([
            'providers',
            'measurement_units',
            'cities',
            'ingredient_city_providers',
            'ingredients',
        ]);

        if ($dump['ingredient_city_providers'] === []) {
            $this->error('El dump no trae filas de `ingredient_city_providers`; sin eso no hay precios que importar.');

            return self::FAILURE;
        }

        $this->table(
            ['Tabla del dump', 'Filas'],
            array_map(fn ($t) => [$t, count($dump[$t])], array_keys($dump))
        );

        // ---------- fase 1: catalogos ----------

        $plan = [
            'providers' => $this->planearCatalogo(
                'providers',
                $dump['providers'],
                fn (array $f) => DumpSqlReader::normalizar($f['name'] ?? null),
                ['name', 'ruc', 'email', 'phone', 'type']
            ),
            'measurement_units' => $this->planearCatalogo(
                'measurement_units',
                $dump['measurement_units'],
                // nombre + abreviatura: el catalogo del dump repite nombres a proposito
                fn (array $f) => DumpSqlReader::normalizar($f['name'] ?? null)
                    . '|' . DumpSqlReader::normalizar($f['abbreviation'] ?? null),
                ['name', 'abbreviation']
            ),
            'cities' => $this->planearCatalogo(
                'cities',
                $dump['cities'],
                fn (array $f) => DumpSqlReader::normalizar($f['name'] ?? null),
                ['name']
            ),
        ];

        foreach (self::CATALOGOS as $tabla) {
            $p = $plan[$tabla];
            $this->line(sprintf(
                '  %-18s %4d en el dump | %4d ya existian | %4d altas%s',
                $tabla,
                count($p['mapa']),
                count($p['mapa']) - count($p['altas']),
                count($p['altas']),
                $p['preservaIds'] ? '  (ids del dump preservados: la tabla viva esta vacia)' : ''
            ));
        }

        if ($ejecutar) {
            $this->aplicarCatalogos($plan);
        }

        $this->newLine();

        // ---------- fase 2: precios ----------

        $r = $this->construirPrecios($plan, $dump);

        $this->table(
            ['Precios', 'Filas'],
            [
                ['Asignaciones nuevas a insertar', count($r['insertar'])],
                ['  de esas, con cost_price cargado', $r['conPrecio']],
                ['  de esas, sin precio en el dump', count($r['insertar']) - $r['conPrecio']],
                [
                    $this->option('pisar') ? 'Asignaciones vivas a actualizar' : 'Ya existian (intactas)',
                    $this->option('pisar') ? count($r['pisar']) : $r['intactas'],
                ],
                ['Descartadas: el insumo no existe vivo', count($r['sinInsumo'])],
                ['Descartadas: proveedor o ciudad ausente del dump', $r['sinFk']],
            ]
        );

        if ($r['sinInsumo'] !== []) {
            $this->warn(count($r['sinInsumo']) . ' filas de precio apuntan a un insumo que no existe en la base viva:');
            foreach (array_slice($r['sinInsumo'], 0, 15) as [$id, $nombre, $precio]) {
                $this->line('  ' . str_pad((string) $id, 7) . str_pad(mb_substr($nombre, 0, 50), 52)
                    . ($precio === null || $precio === '' ? 's/precio' : $precio));
            }
        }

        if (($reporte = $this->option('reporte')) !== null) {
            $this->escribirCsv((string) $reporte, ['ingredient_id_dump', 'nombre_en_el_dump', 'cost_price'], $r['sinInsumo']);
        }

        if ($r['insertar'] === [] && $r['pisar'] === []) {
            $this->newLine();
            $this->info('No hay precios que aplicar.');
            $this->reportarSinPrecio();

            return self::SUCCESS;
        }

        if (! $ejecutar) {
            $this->mostrarMuestra($r['insertar'], $dump['providers']);
            $this->newLine();
            $this->warn('SIMULACRO: no se escribio nada. Agrega --ejecutar para aplicarlo.');

            return self::SUCCESS;
        }

        $ahora   = now();
        $puestas = 0;
        $pisadas = 0;

        DB::transaction(function () use ($r, $ahora, &$puestas, &$pisadas) {
            foreach (array_chunk($r['insertar'], self::LOTE, true) as $lote) {
                $filas = [];
                foreach ($lote as $datos) {
                    $datos['created_at'] ??= $ahora;
                    $datos['updated_at'] = $ahora;
                    $filas[] = $datos;
                }
                DB::table('ingredient_city_providers')->insert($filas);
                $puestas += count($filas);
            }

            foreach ($r['pisar'] as $idFila => $datos) {
                $datos['updated_at'] = $ahora;
                DB::table('ingredient_city_providers')->where('id', $idFila)->update($datos);
                $pisadas++;
            }
        });

        $this->newLine();
        $this->info("Listo: {$puestas} asignaciones de precio insertadas"
            . ($pisadas > 0 ? ", {$pisadas} actualizadas" : '') . '.');
        $this->newLine();

        $this->reportarSinPrecio();

        return self::SUCCESS;
    }

    /**
     * Decide, para un catalogo, que id vivo le corresponde a cada id del dump y
     * que filas hay que dar de alta.
     *
     * Si la tabla viva esta vacia se preservan los ids del dump: no hay nada que
     * pueda colisionar y deja las dos bases alineadas. Si ya tiene filas se mapea
     * por la clave que da $clave, y lo que falte entra con id nuevo (que recien
     * se conoce al insertar, de ahi el null en el mapa).
     *
     * @param  array<int, array<string, string|null>>  $filasDump
     * @param  callable(array<string, string|null>): string  $clave
     * @param  array<int, string>  $copiar  columnas que viajan al alta
     * @return array{mapa: array<int, int|null>, altas: array<int, array<string, mixed>>, alias: array<int, int>, preservaIds: bool}
     */
    private function planearCatalogo(string $tabla, array $filasDump, callable $clave, array $copiar): array
    {
        $copiar      = array_values(array_intersect($copiar, Schema::getColumnListing($tabla)));
        $preservaIds = DB::table($tabla)->doesntExist();

        $vivos     = [];   // clave => id vivo
        $pendiente = [];   // clave => id del dump cuya alta la representa
        if (! $preservaIds) {
            foreach (DB::table($tabla)->get() as $r) {
                $vivos[$clave((array) $r)] = (int) $r->id;
            }
        }

        $mapa  = [];
        $altas = [];
        $alias = [];   // id del dump => id del dump que representa su alta
        foreach ($filasDump as $f) {
            if (! isset($f['id'])) {
                continue;
            }
            $idDump = (int) $f['id'];

            $datos = [];
            foreach ($copiar as $col) {
                $datos[$col] = $f[$col] ?? null;
            }

            // Con ids preservados cada fila del dump entra con el suyo y punto. NO
            // se deduplica por clave: el catalogo de unidades repite nombres a
            // proposito, y fusionar 'Botella'(12) sobre 'Botella'(11) mandaria las
            // FKs que apuntan a 12 contra otra fila sin ninguna necesidad.
            if ($preservaIds) {
                $altas[$idDump] = $datos;
                $mapa[$idDump]  = $idDump;

                continue;
            }

            $k = $clave($f);
            if (array_key_exists($k, $vivos)) {
                $mapa[$idDump] = $vivos[$k];

                continue;
            }
            if (isset($pendiente[$k])) {
                // Otra fila del dump con la misma clave ya pidio el alta: esta se
                // cuelga de ese id, que recien se sabra al insertar.
                $alias[$idDump] = $pendiente[$k];
                $mapa[$idDump]  = null;

                continue;
            }

            $altas[$idDump]  = $datos;
            $mapa[$idDump]   = null;   // lo llena aplicarCatalogos() al insertar
            $pendiente[$k]   = $idDump;
        }

        return ['mapa' => $mapa, 'altas' => $altas, 'alias' => $alias, 'preservaIds' => $preservaIds];
    }

    /**
     * Inserta las altas de los tres catalogos y completa los ids que faltaban en
     * el mapa. Se llama por referencia porque la fase de precios necesita el mapa
     * ya resuelto.
     *
     * @param  array<string, array{mapa: array<int, int|null>, altas: array<int, array<string, mixed>>, alias: array<int, int>, preservaIds: bool}>  $plan
     */
    private function aplicarCatalogos(array &$plan): void
    {
        $ahora = now();

        DB::transaction(function () use (&$plan, $ahora) {
            foreach (self::CATALOGOS as $tabla) {
                if ($plan[$tabla]['altas'] === []) {
                    continue;
                }

                if ($plan[$tabla]['preservaIds']) {
                    $filas = [];
                    foreach ($plan[$tabla]['altas'] as $idDump => $datos) {
                        $filas[] = $datos + ['id' => $idDump, 'created_at' => $ahora, 'updated_at' => $ahora];
                    }
                    foreach (array_chunk($filas, self::LOTE) as $lote) {
                        DB::table($tabla)->insert($lote);
                    }
                } else {
                    // Sin id propio hay que insertar de a una para saber que id le
                    // toco a cada fila del dump y poder traducir las FKs.
                    foreach ($plan[$tabla]['altas'] as $idDump => $datos) {
                        $plan[$tabla]['mapa'][$idDump] = (int) DB::table($tabla)->insertGetId(
                            $datos + ['created_at' => $ahora, 'updated_at' => $ahora]
                        );
                    }
                }

                $this->line('  -> ' . $tabla . ': ' . count($plan[$tabla]['altas']) . ' filas insertadas');
            }

            // Las filas del dump que se colgaron del alta de otra (misma clave)
            // recien ahora pueden conocer su destino.
            foreach ($plan[$tabla]['alias'] as $idDump => $canonico) {
                $plan[$tabla]['mapa'][$idDump] = $plan[$tabla]['mapa'][$canonico] ?? null;
            }
        });
    }

    /**
     * Traduce las filas de precio del dump a la base viva.
     *
     * @param  array<string, array{mapa: array<int, int|null>, altas: array<int, array<string, mixed>>, alias: array<int, int>, preservaIds: bool}>  $plan
     * @param  array<string, array<int, array<string, string|null>>>  $dump
     * @return array{insertar: array<string, array<string, mixed>>, pisar: array<int, array<string, mixed>>, intactas: int, sinInsumo: array<int, array{0: int, 1: string, 2: string|null}>, sinFk: int, conPrecio: int}
     */
    private function construirPrecios(array $plan, array $dump): array
    {
        $pisar = (bool) $this->option('pisar');

        // El puente con los ingredientes vivos es el nombre normalizado.
        $porNombre = [];
        foreach (DB::table('ingredients')->select('id', 'name')->cursor() as $row) {
            $clave = DumpSqlReader::normalizar($row->name);
            if ($clave !== '') {
                $porNombre[$clave][] = (int) $row->id;
            }
        }

        $insumoDump = [];   // id del dump => [ids vivos]
        $nombreDump = [];   // id del dump => nombre crudo
        foreach ($dump['ingredients'] as $f) {
            if (! isset($f['id'])) {
                continue;
            }
            $id              = (int) $f['id'];
            $nombreDump[$id] = (string) ($f['name'] ?? '');
            $insumoDump[$id] = $porNombre[DumpSqlReader::normalizar($f['name'] ?? null)] ?? [];
        }

        $colsVivas = Schema::getColumnListing('ingredient_city_providers');
        $propias   = ['id', 'ingredient_id', 'provider_id', 'city_id', 'measurement_unit_id'];

        // Lo que ya hay vivo, por (insumo, proveedor, ciudad).
        $existentes = [];
        foreach (DB::table('ingredient_city_providers')->select('id', 'ingredient_id', 'provider_id', 'city_id')->cursor() as $row) {
            $existentes[$row->ingredient_id . '-' . $row->provider_id . '-' . $row->city_id] = (int) $row->id;
        }

        $insertar  = [];
        $aPisar    = [];
        $intactas  = 0;
        $sinInsumo = [];
        $sinFk     = 0;

        foreach ($dump['ingredient_city_providers'] as $f) {
            $ingDump = (int) ($f['ingredient_id'] ?? 0);
            $vivos   = $insumoDump[$ingDump] ?? [];

            if ($vivos === []) {
                $sinInsumo[] = [$ingDump, trim($nombreDump[$ingDump] ?? '(el dump no incluye el insumo)'), $f['cost_price'] ?? null];
                continue;
            }

            $provVivo = $plan['providers']['mapa'][(int) ($f['provider_id'] ?? 0)] ?? null;
            $cityVivo = $plan['cities']['mapa'][(int) ($f['city_id'] ?? 0)] ?? null;
            if ($provVivo === null || $cityVivo === null) {
                $sinFk++;   // proveedor o ciudad que el dump referencia pero no incluye
                continue;
            }
            $unidadVivo = $f['measurement_unit_id'] === null
                ? null
                : ($plan['measurement_units']['mapa'][(int) $f['measurement_unit_id']] ?? null);

            // Columnas propias del dump que la tabla viva tambien tiene (cost_price,
            // is_active, created_at...). `presentation` existe solo viva: queda NULL.
            $datos = [];
            foreach ($f as $col => $val) {
                if (! in_array($col, $propias, true) && in_array($col, $colsVivas, true)) {
                    $datos[$col] = $val;
                }
            }
            $datos['provider_id']         = $provVivo;
            $datos['city_id']             = $cityVivo;
            $datos['measurement_unit_id'] = $unidadVivo;
            $datos['is_active']           = (int) ($f['is_active'] ?? 0);

            foreach ($vivos as $ingVivo) {
                $clave = $ingVivo . '-' . $provVivo . '-' . $cityVivo;

                if (isset($existentes[$clave])) {
                    if ($pisar) {
                        $aPisar[$existentes[$clave]] = [
                            'cost_price'          => $datos['cost_price'] ?? null,
                            'measurement_unit_id' => $unidadVivo,
                            'is_active'           => $datos['is_active'],
                        ];
                    } else {
                        $intactas++;
                    }

                    continue;
                }

                // Dos filas del dump pueden caer en la misma clave viva si el
                // catalogo vivo fusiono insumos; la ultima gana, igual que en MySQL.
                $insertar[$clave] = $datos + ['ingredient_id' => $ingVivo];
            }
        }

        return [
            'insertar'  => $insertar,
            'pisar'     => $aPisar,
            'intactas'  => $intactas,
            'sinInsumo' => $sinInsumo,
            'sinFk'     => $sinFk,
            'conPrecio' => count(array_filter($insertar, fn ($d) => ($d['cost_price'] ?? null) !== null)),
        ];
    }

    /**
     * Lista los insumos que el recetario usa de verdad y siguen sin precio, que
     * es lo que al final deja un plato sin costear. Se calcula contra la base
     * viva, asi que despues de --ejecutar refleja el estado real.
     */
    private function reportarSinPrecio(): void
    {
        $usados = DB::table('dish_recipe_ingredients')->distinct()->count('ingredient_id');
        if ($usados === 0) {
            return;
        }

        $sinPrecio = DB::table('dish_recipe_ingredients as dri')
            ->join('ingredients as i', 'i.id', '=', 'dri.ingredient_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('ingredient_city_providers as icp')
                    ->whereColumn('icp.ingredient_id', 'i.id')
                    ->whereNotNull('icp.cost_price');
            })
            ->distinct()
            ->orderBy('i.name')
            ->get(['i.id', 'i.name']);

        $faltan = $sinPrecio->count();

        $this->line(sprintf(
            'Insumos usados en recetas: %d | con precio: %d (%.1f%%) | sin precio: %d',
            $usados,
            $usados - $faltan,
            ($usados - $faltan) * 100 / $usados,
            $faltan
        ));

        if (($ruta = $this->option('reporte-sin-precio')) !== null) {
            $this->escribirCsv(
                (string) $ruta,
                ['ingredient_id', 'nombre'],
                $sinPrecio->map(fn ($r) => [$r->id, $r->name])->all()
            );
        } elseif ($faltan > 0) {
            $this->line('  Lista completa con --reporte-sin-precio=ruta.csv');
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $insertar
     * @param  array<int, array<string, string|null>>  $provsDump
     */
    private function mostrarMuestra(array $insertar, array $provsDump): void
    {
        if ($insertar === []) {
            return;
        }

        $muestra  = array_slice($insertar, 0, 10);
        $nombres  = DB::table('ingredients')->whereIn('id', array_column($muestra, 'ingredient_id'))->pluck('name', 'id');
        $ciudades = DB::table('cities')->pluck('name', 'id');

        // En simulacro los proveedores todavia no estan en la base: el nombre sale
        // del propio dump.
        $provs = [];
        foreach ($provsDump as $f) {
            $provs[(int) ($f['id'] ?? 0)] = (string) ($f['name'] ?? '');
        }

        $filas = [];
        foreach ($muestra as $d) {
            $filas[] = [
                $d['ingredient_id'],
                mb_substr((string) ($nombres[$d['ingredient_id']] ?? '?'), 0, 30),
                mb_substr((string) ($provs[$d['provider_id']] ?? ('#' . $d['provider_id'])), 0, 30),
                $ciudades[$d['city_id']] ?? ('#' . $d['city_id']),
                $d['cost_price'] ?? 's/precio',
            ];
        }

        $this->newLine();
        $this->line('Muestra de las asignaciones a insertar (primeras 10):');
        $this->table(['insumo vivo', 'Nombre', 'Proveedor', 'Ciudad', 'cost_price'], $filas);
    }

    /**
     * @param  array<int, string>  $cabecera
     * @param  array<int, array<int, mixed>>  $filas
     */
    private function escribirCsv(string $ruta, array $cabecera, array $filas): void
    {
        $dir = dirname($ruta);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
            $this->error("No se pudo crear el directorio del reporte: {$dir}");

            return;
        }
        $fh = fopen($ruta, 'w');
        if ($fh === false) {
            $this->error("No se pudo escribir el reporte: {$ruta}");

            return;
        }
        fwrite($fh, "\xEF\xBB\xBF");   // BOM, para que Excel no destroce las tildes
        fputcsv($fh, $cabecera);
        foreach ($filas as $fila) {
            fputcsv($fh, $fila);
        }
        fclose($fh);
        $this->info('Reporte: ' . $ruta);
    }
}
