<?php

namespace App\Console\Commands;

use App\Support\DumpSqlReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trae la tabla `dosifications` desde un dump de phpMyAdmin y la reengancha a
 * los ingredientes VIVOS, que tienen otros ids.
 *
 * POR QUE NO SE PUEDE COPIAR Y PEGAR EL INSERT DEL DUMP
 *
 * `dosifications.ingredient_id` del dump apunta a los ids de `ingredients` DE
 * ESE dump. La base viva se repoblo despues desde el legado de SQL Server, asi
 * que los ids no tienen nada que ver: el mismo insumo puede ser 2 alla y 6 aca.
 * Ejecutar el INSERT del dump tal cual no falla de forma visible -- la FK deja
 * pasar casi todo porque los ids existen, solo apuntan a OTRO insumo -- y
 * termina con tablas de composicion nutricional cruzadas. De ahi salen las
 * kilocalorias de un plato, asi que el error se propaga a todos los reportes.
 *
 * COMO SE RESUELVE EL PUENTE ENTRE LAS DOS BASES
 *
 * `ingredients.code` esta en NULL en toda la base viva, asi que el codigo del
 * dump (`010000002`, ...) no sirve de llave. El unico dato comun es el nombre,
 * y hay que normalizarlo porque el legado lo maltrato:
 *
 *   - padding de CHAR:  'QUESO FRESCO                    '
 *   - mayusculas o no:  'Acelga x 1 Kg.' / 'ACELGA X 1 KG'
 *   - puntuacion suelta:'Queso Fresco EXTRA- "El Valle"' / sin las comillas
 *   - tildes rotas:     'Ralladura de Limon' quedo con bytes C3B2 ('o grave')
 *                       en lugar de C3B3 ('o aguda')
 *
 * Por eso DumpSqlReader::normalizar() pasa a mayusculas, translitera CUALQUIER
 * vocal acentuada a su letra base (no solo las correctas: tambien las rotas) y
 * convierte todo lo que no sea A-Z0-9 en un solo espacio. El detalle de por que
 * se hace con un strtr explicito y no con iconv //TRANSLIT esta en esa clase.
 *
 * Con eso el dump empareja 1507 de sus 1859 dosificaciones. Las 352 restantes
 * son insumos que ya no existen en la base viva (el dump tiene 2815 ingredientes
 * contra 2176 vivos); se listan con --reporte en vez de adivinarles una pareja.
 * NO se hace emparejado difuso a proposito: a 90% de similitud 'GASEOSA COCA
 * COLA X 300 ML' se "parece" a la de 500 ML y 'PIERNITAS DE POLLO' a 'ALITAS DE
 * POLLO'. Un match inventado es peor que una dosificacion ausente, porque nadie
 * lo nota.
 *
 * DOS CASOS QUE NO SON UNO A UNO
 *
 *   varios vivos, un origen    Dos filas vivas distintas con el mismo nombre
 *                              normalizado son el mismo insumo duplicado.
 *                              Las dos reciben la dosificacion.
 *
 *   un vivo, varios origenes   El dump tambien tiene duplicados (54 casos). Si
 *                              los nutrientes coinciden da igual cual se tome;
 *                              si difieren gana el id de dump mas alto (el mas
 *                              reciente) y se avisa por pantalla.
 *
 * LO VIVO MANDA
 *
 * Un ingrediente que YA tiene dosificacion no se toca: alguien pudo haberla
 * cargado a mano desde la pantalla despues de la fecha del respaldo. Para
 * pisarla hay que pedirlo con --pisar.
 *
 * USO
 *
 *     php artisan dosificaciones:importar C:/ruta/backendlaravel.sql
 *     php artisan dosificaciones:importar C:/ruta/backendlaravel.sql --ejecutar
 *     php artisan dosificaciones:importar dump.sql --ejecutar --pisar
 *     php artisan dosificaciones:importar dump.sql --reporte=storage/sin-pareja.csv
 */
class ImportarDosificaciones extends Command
{
    protected $signature = 'dosificaciones:importar
                            {ruta : Ruta del dump .sql que trae las tablas dosifications e ingredients}
                            {--ejecutar : Aplica los cambios. Sin este flag solo informa que haria}
                            {--pisar : Sobreescribe la dosificacion de un ingrediente que ya tiene una}
                            {--reporte= : Archivo CSV donde volcar los insumos del dump que no encontraron pareja}';

    protected $description = 'Importa dosifications desde un dump SQL reenganchandolas al ingredient_id correcto de la base viva';

    private const LOTE = 500;

    public function handle(): int
    {
        $ruta     = (string) $this->argument('ruta');
        $ejecutar = (bool) $this->option('ejecutar');
        $pisar    = (bool) $this->option('pisar');

        $lector = new DumpSqlReader($ruta);
        if (! $lector->existe()) {
            $this->error("No existe el archivo: {$ruta}");

            return self::FAILURE;
        }

        $this->info('Dump: ' . $ruta . ' (' . DumpSqlReader::humano($lector->tamano()) . ')');
        $this->newLine();

        // Las columnas a copiar son las que el dump y la tabla viva tienen en
        // comun, menos id (autoincrement) e ingredient_id (se recalcula). Asi
        // una columna agregada por migracion en un lado y no en el otro no
        // rompe el INSERT: simplemente no viaja.
        $vivas = Schema::getColumnListing('dosifications');

        // ---------- lado dump ----------

        // Una sola pasada por los 38 MB para las dos tablas.
        $leido = $lector->leerTodas(['ingredients', 'dosifications']);

        $nombresDump = [];   // id del dump => nombre crudo
        foreach ($leido['ingredients'] as $fila) {
            if (isset($fila['id'], $fila['name'])) {
                $nombresDump[(int) $fila['id']] = (string) $fila['name'];
            }
        }
        if ($nombresDump === []) {
            $this->error('El dump no trae filas de `ingredients`; sin eso no hay con que resolver los nombres.');

            return self::FAILURE;
        }

        $dosisDump = [];     // ingredient_id del dump => ['id' => .., 'datos' => [col => val]]
        $columnas  = null;
        $huerfanas = 0;
        foreach ($leido['dosifications'] as $fila) {
            $columnas ??= array_keys($fila);
            $ingDump = isset($fila['ingredient_id']) ? (int) $fila['ingredient_id'] : 0;
            if ($ingDump === 0) {
                continue;
            }
            if (! isset($nombresDump[$ingDump])) {
                $huerfanas++;   // dosificacion huerfana dentro del propio dump
                continue;
            }

            $datos = [];
            foreach ($fila as $col => $val) {
                if ($col !== 'id' && $col !== 'ingredient_id' && in_array($col, $vivas, true)) {
                    $datos[$col] = $val;
                }
            }

            $previo = $dosisDump[$ingDump] ?? null;
            if ($previo !== null && $previo['datos'] !== $datos) {
                $this->warn(sprintf(
                    'Dump: el insumo %d (%s) trae dos dosificaciones distintas; se usa la mas reciente.',
                    $ingDump,
                    trim($nombresDump[$ingDump])
                ));
            }
            // Gana la ultima aparicion, que en un dump de phpMyAdmin es la de id mas alto.
            $dosisDump[$ingDump] = ['id' => (int) ($fila['id'] ?? 0), 'datos' => $datos];
        }

        if ($dosisDump === []) {
            $this->error('El dump no trae filas de `dosifications`.');

            return self::FAILURE;
        }

        $viajan = $columnas === null ? [] : array_intersect($columnas, $vivas);
        $this->line('Dump leido: ' . count($nombresDump) . ' insumos, ' . count($dosisDump) . ' dosificaciones.');
        if ($huerfanas > 0) {
            $this->warn("  {$huerfanas} dosificaciones del dump apuntan a un insumo que el dump no incluye; se ignoran.");
        }
        $this->line('Columnas que viajan: ' . (count($viajan) - 2) . ' nutrientes + created_at/updated_at.');
        $faltan = array_diff($columnas ?? [], $vivas);
        if ($faltan !== []) {
            $this->warn('  Columnas del dump que la tabla viva no tiene: ' . implode(', ', $faltan));
        }
        $this->newLine();

        // ---------- lado vivo ----------

        $porNombre = [];   // nombre normalizado => [ids vivos]
        $nombreDe  = [];   // id vivo => nombre crudo
        foreach (DB::table('ingredients')->select('id', 'name')->cursor() as $r) {
            $clave = DumpSqlReader::normalizar((string) $r->name);
            if ($clave === '') {
                continue;
            }
            $porNombre[$clave][]    = (int) $r->id;
            $nombreDe[(int) $r->id] = (string) $r->name;
        }

        $yaTienen = [];    // id vivo => true
        foreach (DB::table('dosifications')->select('ingredient_id')->cursor() as $r) {
            $yaTienen[(int) $r->ingredient_id] = true;
        }

        $this->line('Base viva: ' . count($nombreDe) . ' insumos, ' . count($yaTienen) . ' con dosificacion.');
        $this->newLine();

        // ---------- emparejar ----------

        $aInsertar = [];   // id vivo => datos
        $aPisar    = [];   // id vivo => datos
        $intactos  = [];   // id vivo => true (set, no contador: un vivo puede tener varios origenes)
        $sinPareja = [];   // [id dump, nombre dump]
        $origenDe  = [];   // id vivo => id del dump que gano (solo para el detalle)

        foreach ($dosisDump as $ingDump => $info) {
            $clave = DumpSqlReader::normalizar($nombresDump[$ingDump]);
            $ids   = $clave === '' ? [] : ($porNombre[$clave] ?? []);

            if ($ids === []) {
                $sinPareja[] = [$ingDump, trim($nombresDump[$ingDump])];
                continue;
            }

            foreach ($ids as $idVivo) {
                $origenDe[$idVivo] = $ingDump;

                if (isset($yaTienen[$idVivo])) {
                    if ($pisar) {
                        $aPisar[$idVivo] = $info['datos'];
                    } else {
                        $intactos[$idVivo] = true;
                    }

                    continue;
                }
                $aInsertar[$idVivo] = $info['datos'];
            }
        }

        $this->table(
            ['Resultado', 'Insumos vivos'],
            [
                ['Dosificacion nueva a insertar', count($aInsertar)],
                [$pisar ? 'Dosificacion viva a sobreescribir' : 'Ya tenian dosificacion (intactos)', $pisar ? count($aPisar) : count($intactos)],
                ['Dosificaciones del dump sin pareja viva', count($sinPareja)],
            ]
        );

        if (! $pisar && $intactos !== []) {
            $this->line('Los ' . count($intactos) . ' intactos no se tocan. Para pisarlos: --pisar');
        }
        if ($sinPareja !== []) {
            $this->warn(count($sinPareja) . ' insumos del dump no existen en la base viva. Primeros 10:');
            foreach (array_slice($sinPareja, 0, 10) as [$id, $nombre]) {
                $this->line('  ' . str_pad((string) $id, 7) . $nombre);
            }
            $this->line('  Lista completa con --reporte=ruta.csv');
        }

        if (($reporte = $this->option('reporte')) !== null) {
            $this->escribirReporte((string) $reporte, $sinPareja);
        }

        if ($aInsertar === [] && $aPisar === []) {
            $this->newLine();
            $this->info('No hay nada que aplicar.');

            return self::SUCCESS;
        }

        if (! $ejecutar) {
            $this->mostrarMuestra($aInsertar !== [] ? $aInsertar : $aPisar, $nombreDe, $origenDe, $nombresDump);
            $this->newLine();
            $this->warn('SIMULACRO: no se escribio nada. Agrega --ejecutar para aplicarlo.');

            return self::SUCCESS;
        }

        // ---------- aplicar ----------

        $ahora      = now();
        $insertadas = 0;
        $pisadas    = 0;

        DB::transaction(function () use ($aInsertar, $aPisar, $ahora, &$insertadas, &$pisadas) {
            foreach (array_chunk($aInsertar, self::LOTE, true) as $lote) {
                $filas = [];
                foreach ($lote as $idVivo => $datos) {
                    $datos['ingredient_id'] = $idVivo;
                    $datos['created_at'] ??= $ahora;
                    $datos['updated_at'] = $ahora;
                    $filas[] = $datos;
                }
                DB::table('dosifications')->insert($filas);
                $insertadas += count($filas);
            }

            foreach ($aPisar as $idVivo => $datos) {
                unset($datos['created_at']);   // la fecha de alta original se respeta
                $datos['updated_at'] = $ahora;
                DB::table('dosifications')->where('ingredient_id', $idVivo)->update($datos);
                $pisadas++;
            }
        });

        $this->newLine();
        $this->info("Listo: {$insertadas} dosificaciones insertadas" . ($pisadas > 0 ? ", {$pisadas} sobreescritas" : '') . '.');
        $this->line('Total en `dosifications`: ' . DB::table('dosifications')->count());

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $datos
     * @param  array<int, string>  $nombreDe
     * @param  array<int, int>  $origenDe
     * @param  array<int, string>  $nombresDump
     */
    private function mostrarMuestra(array $datos, array $nombreDe, array $origenDe, array $nombresDump): void
    {
        $filas = [];
        foreach (array_slice($datos, 0, 10, true) as $idVivo => $d) {
            $origen  = $origenDe[$idVivo] ?? 0;
            $filas[] = [
                $origen . ' -> ' . $idVivo,
                mb_substr(trim($nombresDump[$origen] ?? '?'), 0, 38),
                mb_substr(trim($nombreDe[$idVivo] ?? '?'), 0, 38),
                $d['energy'] ?? '-',
                $d['protein'] ?? '-',
            ];
        }
        $this->newLine();
        $this->line('Muestra del reenganche (primeras 10):');
        $this->table(['dump -> vivo', 'Nombre en el dump', 'Nombre vivo', 'energy', 'protein'], $filas);
    }

    /** @param  array<int, array{0: int, 1: string}>  $sinPareja */
    private function escribirReporte(string $ruta, array $sinPareja): void
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
        fputcsv($fh, ['ingredient_id_dump', 'nombre_en_el_dump']);
        foreach ($sinPareja as $linea) {
            fputcsv($fh, $linea);
        }
        fclose($fh);
        $this->info('Reporte de sin-pareja: ' . $ruta);
    }
}
