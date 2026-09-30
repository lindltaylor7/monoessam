<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Restaura `dish_recipes` y `dish_recipe_ingredients` desde un dump de phpMyAdmin.
 *
 * POR QUE HACE FALTA
 *
 * El badge de nivel en Quebrados.vue tenia un icono de papelera que llamaba a
 * levels.destroy, o sea DELETE FROM levels. Las tres tablas que referencian
 * `levels` (dish_recipes, dish_recipe_levels, dish_ingredient_levels) lo hacen
 * con ON DELETE CASCADE, asi que un click ahi borraba el recetario de ese nivel
 * en TODOS los platos. Como el 99.9% del recetario vive en MASTER (level_id=1),
 * borrar ese nivel vaciaba practicamente toda la base de recetas de un golpe.
 *
 * El icono ya no existe (commit 92b7bed5d), pero el dato perdido hay que traerlo
 * de vuelta desde un respaldo.
 *
 * QUE HACE Y QUE NO HACE
 *
 * NUNCA borra ni pisa nada. Solo inserta lo que falta. Concretamente clasifica
 * cada receta del dump contra la base viva y actua asi:
 *
 *   NUEVA     no hay receta viva para ese (dish_id, level_id)
 *             -> inserta la receta y sus ingredientes
 *
 *   VACIA     hay receta viva pero con 0 ingredientes, y el dump tiene > 0
 *             -> inserta solo los ingredientes y copia los totales
 *             Este es el caso tipico del desastre: update() de DishController
 *             recrea la fila de dish_recipes al guardar el plato, pero sin
 *             ingredientes. Queda el cascaron.
 *
 *   INTACTA   hay receta viva CON ingredientes
 *             -> no se toca, aunque el dump diga otra cosa
 *             Lo vivo siempre gana. Es lo que protege el trabajo hecho despues
 *             de la fecha del respaldo.
 *
 * DECISIONES QUE NO SON OBVIAS
 *
 * 1. La clave de comparacion es (dish_id, level_id), NO el id de la receta.
 *    dish_recipes tiene UNIQUE dish_recipes_dish_level_unique sobre ese par, o
 *    sea que es la identidad real. Los ids NO sirven: al vaciarse la tabla el
 *    AUTO_INCREMENT de InnoDB se reinicia en max(id)+1 al siguiente restart, asi
 *    que las recetas creadas despues del borrado reusan ids que en el dump
 *    pertenecen a otro plato. Casar por id mezclaria recetas entre platos.
 *
 * 2. Se insertan con ids NUEVOS, no con los del dump, por lo mismo. El id del
 *    dump solo se usa como clave temporal para reengancharle sus ingredientes.
 *
 * 3. Se descarta toda fila cuyo destino de FK no exista hoy: receta cuyo
 *    dish_id ya no esta en `dishes`, ingrediente cuyo ingredient_id ya no esta
 *    en `ingredients`, receta cuyo level_id ya no esta en `levels`. Un dump de
 *    otra instancia puede traer ids que aqui no existen; insertarlos reventaria
 *    la transaccion entera por una sola fila mala. Se cuentan y se reportan.
 *
 * 4. Dos pasadas sobre el archivo, no una. dish_recipe_ingredients son ~162 mil
 *    filas; cargarlas todas en memoria para despues decidir cuales sirven se
 *    come cientos de MB. La primera pasada lee solo dish_recipes (17 mil, cabe
 *    de sobra), decide que restaurar, inserta las recetas y arma el mapa
 *    id_dump -> id_vivo; la segunda vuelve a leer el archivo y va soltando los
 *    ingredientes en lotes. La memoria queda plana.
 *
 * 5. Por defecto SIMULA. Hay que pasar --ejecutar para que escriba.
 *
 * USO
 *
 *   php artisan recetas:restaurar --dump="C:/ruta/backendlaravel.sql"
 *   php artisan recetas:restaurar --dump="..." --ejecutar
 *   php artisan recetas:restaurar --dump="..." --plato=17067 --plato=17068
 */
class RestaurarRecetasDesdeDump extends Command
{
    protected $signature = 'recetas:restaurar
        {--dump= : Ruta al archivo .sql de respaldo}
        {--ejecutar : Escribe en la base. Sin esta bandera solo simula}
        {--plato=* : Limitar a estos dish_id (repetible)}
        {--lote=500 : Filas por INSERT}
        {--forzar : Saltar el control de que el dump corresponda a esta base}';

    protected $description = 'Restaura recetas y sus ingredientes desde un dump SQL, sin pisar lo que ya existe';

    /** Tablas cuyas filas nos interesan del dump. */
    private const TABLAS = ['dishes', 'dish_recipes', 'dish_recipe_ingredients'];

    /** Minimo de nombres que deben coincidir para aceptar que el dump es de esta base. */
    private const UMBRAL_PROCEDENCIA = 80.0;

    public function handle(): int
    {
        $ruta = (string) $this->option('dump');
        if ($ruta === '' || !is_readable($ruta)) {
            $this->error("No se puede leer el dump: '{$ruta}'. Pase --dump=RUTA.");
            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $lote     = max(1, (int) $this->option('lote'));
        $filtro   = array_map('intval', (array) $this->option('plato'));
        $filtro   = $filtro ? array_flip($filtro) : null;

        $this->line('');
        $this->info('Dump:  ' . $ruta . '  (' . $this->humano((int) filesize($ruta)) . ')');
        $this->info('Base:  ' . DB::connection()->getDatabaseName() . ' @ ' . config('database.connections.mysql.host'));
        $this->info('Modo:  ' . ($ejecutar ? 'EJECUTAR (escribe)' : 'SIMULACION (no escribe)'));
        $this->line('');

        // ---------------------------------------------------------------- vivo
        $platosVivos       = $this->nombresVivos('dishes');   // id => nombre normalizado
        $ingredientesVivos = $this->idsVivos('ingredients');
        $nivelesVivos      = $this->idsVivos('levels');

        if (!$this->procedenciaOk($ruta, $platosVivos)) {
            return self::FAILURE;
        }

        // (dish_id, level_id) -> ['id' => ..., 'ingredientes' => n]
        $recetasVivas = [];
        $porId = [];
        foreach (DB::table('dish_recipes')->select('id', 'dish_id', 'level_id')->cursor() as $r) {
            $clave = $r->dish_id . ':' . $r->level_id;
            $recetasVivas[$clave] = ['id' => (int) $r->id, 'ingredientes' => 0];
            $porId[(int) $r->id] = $clave;
        }
        foreach (DB::table('dish_recipe_ingredients')
                    ->select('dish_recipe_id', DB::raw('COUNT(*) as n'))
                    ->groupBy('dish_recipe_id')->cursor() as $r) {
            if (isset($porId[(int) $r->dish_recipe_id])) {
                $recetasVivas[$porId[(int) $r->dish_recipe_id]]['ingredientes'] = (int) $r->n;
            }
        }
        unset($porId);

        $this->line(sprintf(
            'Estado vivo: %s platos, %s ingredientes, %s niveles, %s recetas (%s con ingredientes).',
            number_format(count($platosVivos)),
            number_format(count($ingredientesVivos)),
            number_format(count($nivelesVivos)),
            number_format(count($recetasVivas)),
            number_format(count(array_filter($recetasVivas, fn ($v) => $v['ingredientes'] > 0)))
        ));

        // ------------------------------------------------- pasada 1: recetas
        $this->line('');
        $this->line('Pasada 1/2 - leyendo dish_recipes del dump...');

        $nuevas = [];   // id_dump => fila a insertar
        $vacias = [];   // id_dump => ['id' => id_vivo, 'totales' => [...]]
        $conteo = ['intacta' => 0, 'sin_plato' => 0, 'sin_nivel' => 0, 'fuera_filtro' => 0, 'dump' => 0];

        foreach ($this->filas($ruta, 'dish_recipes') as $f) {
            $conteo['dump']++;
            $idDump  = (int) $f[0];
            $dishId  = (int) $f[1];
            $levelId = $f[2] === null ? null : (int) $f[2];

            if ($filtro !== null && !isset($filtro[$dishId])) { $conteo['fuera_filtro']++; continue; }
            if (!isset($platosVivos[$dishId]))                { $conteo['sin_plato']++;    continue; }
            if ($levelId === null || !isset($nivelesVivos[$levelId])) { $conteo['sin_nivel']++; continue; }

            $totales = [
                'total_gross_weight' => $f[4],
                'total_waste_weight' => $f[5],
                'total_calories'     => $f[6],
                'total_cost'         => $f[7],
                'total_net_weight'   => $f[8],
            ];

            $clave = $dishId . ':' . $levelId;
            if (!isset($recetasVivas[$clave])) {
                $nuevas[$idDump] = array_merge([
                    'dish_id'  => $dishId,
                    'level_id' => $levelId,
                    'name'     => $f[3] ?? 'Receta Estandar',
                ], $totales, [
                    'created_at' => $f[9],
                    'updated_at' => $f[10],
                ]);
            } elseif ($recetasVivas[$clave]['ingredientes'] === 0) {
                $vacias[$idDump] = ['id' => $recetasVivas[$clave]['id'], 'totales' => $totales];
            } else {
                $conteo['intacta']++;
            }
        }

        $this->line(sprintf(
            '  %s filas leidas -> %s nuevas, %s a rellenar, %s intactas (se respetan), '
            . '%s sin plato, %s sin nivel%s.',
            number_format($conteo['dump']),
            number_format(count($nuevas)),
            number_format(count($vacias)),
            number_format($conteo['intacta']),
            number_format($conteo['sin_plato']),
            number_format($conteo['sin_nivel']),
            $filtro !== null ? ', ' . number_format($conteo['fuera_filtro']) . ' fuera del filtro' : ''
        ));

        if (!$nuevas && !$vacias) {
            $this->line('');
            $this->info('No hay nada que restaurar.');
            return self::SUCCESS;
        }

        // ------------------------------------------ control de procedencia
        //
        // Un dump de OTRA instancia se lee sin errores y restaura igual, pero el
        // resultado es basura: los dish_id que casan lo hacen por coincidencia
        // numerica, no porque sean el mismo plato, asi que se le cuelgan recetas
        // ajenas a platos que no les corresponden. El sintoma es un porcentaje
        // alto de filas descartadas por "sin plato": si el dump fuera de esta
        // misma base, casi todos sus dish_id existirian aca.
        $utiles     = $conteo['dump'] - $conteo['fuera_filtro'];
        $porcentaje = $utiles > 0 ? round($conteo['sin_plato'] * 100 / $utiles, 1) : 0.0;

        if ($porcentaje > 20 && !$this->option('forzar')) {
            $this->line('');
            $this->error("ABORTADO: el {$porcentaje}% de las recetas del dump apunta a platos que no "
                . 'existen en esta base.');
            $this->line('');
            $this->line('  Eso indica que el dump NO es de esta base. Restaurarlo colgaria recetas');
            $this->line('  de un plato en otro distinto que casualmente tiene el mismo id.');
            $this->line('');
            $this->line('  Revise que --dump y la conexion apunten a la misma instancia. Si de verdad');
            $this->line('  quiere seguir asi, repita con --forzar.');
            return self::FAILURE;
        }
        if ($porcentaje > 5) {
            $this->warn("  Aviso: el {$porcentaje}% de las recetas se descarta por plato inexistente.");
        }

        // --------------------------------------------- insertar las recetas
        // mapa id_dump -> id_vivo, necesario para la pasada 2
        $mapa = [];
        foreach ($vacias as $idDump => $v) {
            $mapa[$idDump] = $v['id'];
        }

        if ($ejecutar) {
            DB::transaction(function () use ($nuevas, $vacias, $lote, &$mapa) {
                foreach (array_chunk($nuevas, $lote, true) as $trozo) {
                    DB::table('dish_recipes')->insert(array_values($trozo));

                    // insert() masivo no devuelve ids: se releen por (dish_id, level_id),
                    // que es justamente la clave unica de la tabla.
                    $filas = DB::table('dish_recipes')
                        ->select('id', 'dish_id', 'level_id')
                        ->whereIn('dish_id', array_unique(array_column($trozo, 'dish_id')))
                        ->get()
                        ->keyBy(fn ($r) => $r->dish_id . ':' . $r->level_id);

                    foreach ($trozo as $idDump => $f) {
                        $k = $f['dish_id'] . ':' . $f['level_id'];
                        if (isset($filas[$k])) {
                            $mapa[$idDump] = (int) $filas[$k]->id;
                        }
                    }
                }
                foreach ($vacias as $v) {
                    DB::table('dish_recipes')->where('id', $v['id'])->update($v['totales']);
                }
            });
            $this->line('  ' . number_format(count($nuevas)) . ' recetas insertadas, '
                . number_format(count($vacias)) . ' totales actualizados.');
        } else {
            // En simulacion el mapa solo necesita las claves, para poder contar.
            foreach ($nuevas as $idDump => $_) {
                $mapa[$idDump] = 0;
            }
        }

        // -------------------------------------------- pasada 2: ingredientes
        $this->line('');
        $this->line('Pasada 2/2 - leyendo dish_recipe_ingredients del dump...');

        $buffer         = [];
        $puestos        = 0;
        $sinIngrediente = 0;
        $ajenos         = 0;
        $leidos         = 0;

        $volcar = function () use (&$buffer, &$puestos, $ejecutar) {
            if (!$buffer) {
                return;
            }
            if ($ejecutar) {
                DB::table('dish_recipe_ingredients')->insert($buffer);
            }
            $puestos += count($buffer);
            $buffer = [];
        };

        foreach ($this->filas($ruta, 'dish_recipe_ingredients') as $f) {
            $leidos++;
            $recetaDump = (int) $f[1];
            if (!isset($mapa[$recetaDump])) { $ajenos++; continue; }

            $ingId = (int) $f[2];
            if (!isset($ingredientesVivos[$ingId])) { $sinIngrediente++; continue; }

            $buffer[] = [
                'dish_recipe_id' => $mapa[$recetaDump],
                'ingredient_id'  => $ingId,
                'gross_weight'   => $f[3],
                'solid_waste'    => $f[4],
                'liquid_waste'   => $f[5],
                'calories'       => $f[6],
                'cost'           => $f[7],
                'unit_price'     => $f[8],
                'net_weight'     => $f[9],
                'created_at'     => $f[10],
                'updated_at'     => $f[11],
            ];
            if (count($buffer) >= $lote) {
                $volcar();
            }
        }
        $volcar();

        $this->line(sprintf(
            '  %s filas leidas -> %s %s, %s de recetas no restauradas, %s con ingrediente inexistente.',
            number_format($leidos),
            number_format($puestos),
            $ejecutar ? 'insertadas' : 'a insertar',
            number_format($ajenos),
            number_format($sinIngrediente)
        ));

        $this->line('');
        if ($ejecutar) {
            $this->info('Listo. Recetas vivas ahora: ' . number_format(DB::table('dish_recipes')->count())
                . ', ingredientes de receta: ' . number_format(DB::table('dish_recipe_ingredients')->count()) . '.');
        } else {
            $this->warn('SIMULACION: no se escribio nada. Repita con --ejecutar para aplicar.');
        }

        if ($sinIngrediente > 0) {
            $this->line('');
            $this->warn(number_format($sinIngrediente) . ' lineas de ingrediente se descartan porque ese '
                . 'ingredient_id no existe en esta base. Si el dump viene de otra instancia, restaure '
                . 'primero la tabla `ingredients` y vuelva a correr esto.');
        }

        return self::SUCCESS;
    }

    /**
     * Comprueba que el dump sea de ESTA base comparando nombres de plato.
     *
     * Este es el control que de verdad importa, y va antes que cualquier otro.
     * Un dump ajeno se parsea sin un solo error y restaura sin una sola
     * excepcion: las FK cuadran porque los ids existen a ambos lados, solo que
     * identifican platos distintos. El resultado es que a "Jugo de Maracuya"
     * (id 92 aqui) se le cuelga la receta de "TRUCHA FRITA" (id 92 alla). Nada
     * falla, nada avisa, y la base queda en un estado peor que vacia porque el
     * error ya no se distingue de un dato bueno.
     *
     * Contar ids faltantes no alcanza: dos bases distintas con el mismo rango de
     * ids se solapan casi entero. Lo unico que separa "es mi base" de "es otra"
     * es que los ids compartidos apunten al MISMO plato, o sea el nombre.
     *
     * @param array<int, string> $platosVivos id => nombre normalizado
     */
    private function procedenciaOk(string $ruta, array $platosVivos): bool
    {
        $comunes = 0;
        $iguales = 0;
        $muestra = [];

        foreach ($this->filas($ruta, 'dishes') as $f) {
            $id = (int) $f[0];
            if (!isset($platosVivos[$id])) {
                continue;
            }
            $comunes++;
            if ($this->normalizar($f[1] ?? '') === $platosVivos[$id]) {
                $iguales++;
            } elseif (count($muestra) < 3) {
                $muestra[] = "  id={$id}  dump: " . ($f[1] ?? 'NULL') . "   |   vivo: " . $platosVivos[$id];
            }
        }

        if ($comunes === 0) {
            $this->line('');
            $this->error('ABORTADO: el dump no comparte ni un solo id de plato con esta base.');
            $this->line('  No hay nada que casar. Verifique que --dump y la conexion sean del mismo sistema.');
            return false;
        }

        $pct = round($iguales * 100 / $comunes, 1);
        $this->line(sprintf(
            'Procedencia: %s ids de plato en comun, %s con el mismo nombre (%s%%).',
            number_format($comunes),
            number_format($iguales),
            $pct
        ));

        if ($pct >= self::UMBRAL_PROCEDENCIA) {
            return true;
        }

        if ($this->option('forzar')) {
            $this->warn("  Solo el {$pct}% de los nombres coincide, pero se paso --forzar. Siguiendo.");
            return true;
        }

        $this->line('');
        $this->error("ABORTADO: solo el {$pct}% de los platos con id compartido tiene el mismo nombre.");
        $this->line('');
        $this->line('  El dump NO es de esta base: los ids coinciden por casualidad, pero identifican');
        $this->line('  platos distintos. Restaurarlo le colgaria a cada plato la receta de otro.');
        $this->line('');
        foreach ($muestra as $m) {
            $this->line($m);
        }
        $this->line('');
        $this->line('  Apunte --dump y la conexion a la misma instancia, o use --forzar si de verdad');
        $this->line('  sabe lo que esta haciendo.');

        return false;
    }

    /** Nombres de una tabla como mapa id => nombre normalizado. */
    private function nombresVivos(string $tabla): array
    {
        $out = [];
        foreach (DB::table($tabla)->select('id', 'name')->cursor() as $r) {
            $out[(int) $r->id] = $this->normalizar((string) $r->name);
        }
        return $out;
    }

    /** Mayusculas, sin tildes y sin espacios de sobra, para comparar nombres. */
    private function normalizar(?string $s): string
    {
        $s = trim((string) $s);
        if ($s === '') {
            return '';
        }
        $sinTildes = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($sinTildes !== false) {
            $s = $sinTildes;
        }
        return preg_replace('/\s+/', ' ', mb_strtoupper($s, 'UTF-8')) ?? '';
    }

    /** ids de una tabla como mapa id => true, para lookup O(1). */
    private function idsVivos(string $tabla): array
    {
        $out = [];
        foreach (DB::table($tabla)->select('id')->cursor() as $r) {
            $out[(int) $r->id] = true;
        }
        return $out;
    }

    /**
     * Recorre el dump y devuelve, una por una, las filas de la tabla pedida ya
     * partidas en campos. No carga el archivo en memoria.
     *
     * Un dump de phpMyAdmin pone cada fila en su propia linea, pero un campo de
     * texto con salto de linea adentro rompe eso; por eso se acumula hasta que
     * la tupla cierra de verdad (parentesis balanceado fuera de comillas).
     *
     * @return \Generator<int, array<int, string|null>>
     */
    private function filas(string $ruta, string $tabla): \Generator
    {
        $fh = fopen($ruta, 'r');
        if ($fh === false) {
            return;
        }

        $actual = null;   // tabla del INSERT que se esta leyendo
        $buffer = '';

        while (($linea = fgets($fh)) !== false) {
            if ($buffer === '') {
                if (str_starts_with($linea, 'INSERT INTO `')) {
                    preg_match('/^INSERT INTO `([^`]+)`/', $linea, $m);
                    $actual = in_array($m[1] ?? '', self::TABLAS, true) ? $m[1] : null;

                    // phpMyAdmin suele cortar la linea despues de VALUES, pero
                    // no siempre: la primera tupla puede venir pegada aca.
                    $pos   = strpos($linea, ') VALUES');
                    $resto = $pos === false ? '' : ltrim(substr($linea, $pos + 8));
                    if ($resto === '' || $resto[0] !== '(') {
                        continue;
                    }
                    $linea = $resto;
                } elseif ($linea === '' || $linea[0] !== '(') {
                    // Cualquier cosa que no sea una tupla cierra el INSERT en curso.
                    if (trim($linea) !== '') {
                        $actual = null;
                    }
                    continue;
                }

                if ($actual === null) {
                    continue;
                }
            }

            $buffer .= $linea;
            $tupla = $this->tuplaCompleta($buffer);
            if ($tupla === null) {
                continue;   // campo con salto de linea adentro: seguir acumulando
            }
            $buffer = '';

            if ($actual === $tabla) {
                yield $this->campos($tupla);
            }
        }

        fclose($fh);
    }

    /**
     * Si $s empieza una tupla y esta cerrada, devuelve su contenido sin los
     * parentesis externos. Si todavia esta abierta (comilla sin cerrar),
     * devuelve null para que el llamador siga acumulando.
     */
    private function tuplaCompleta(string $s): ?string
    {
        $len    = strlen($s);
        $cadena = false;
        $escape = false;

        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ($escape) { $escape = false; continue; }
            if ($cadena) {
                if ($c === '\\') { $escape = true;  continue; }
                if ($c === "'")  { $cadena = false; continue; }
                continue;
            }
            if ($c === "'") { $cadena = true; continue; }
            if ($c === ')') { return substr($s, 1, $i - 1); }
        }

        return null;
    }

    /**
     * Parte el contenido de una tupla en campos, respetando comillas. NULL sale
     * como null; el resto como string (numeros y fechas se pasan tal cual, el
     * driver los castea al insertar).
     *
     * @return array<int, string|null>
     */
    private function campos(string $tupla): array
    {
        $out    = [];
        $cur    = '';
        $cadena = false;
        $escape = false;
        $len    = strlen($tupla);

        for ($i = 0; $i < $len; $i++) {
            $c = $tupla[$i];
            if ($escape) { $cur .= $c; $escape = false; continue; }
            if ($cadena) {
                if ($c === '\\') { $escape = true;  $cur .= $c; continue; }
                if ($c === "'")  { $cadena = false; $cur .= $c; continue; }
                $cur .= $c;
                continue;
            }
            if ($c === "'") { $cadena = true; $cur .= $c; continue; }
            if ($c === ',') { $out[] = $this->limpiar($cur); $cur = ''; continue; }
            $cur .= $c;
        }
        $out[] = $this->limpiar($cur);

        return $out;
    }

    private function limpiar(string $v): ?string
    {
        $v = trim($v);
        if ($v === '' || strcasecmp($v, 'NULL') === 0) {
            return null;
        }
        if (strlen($v) >= 2 && $v[0] === "'" && substr($v, -1) === "'") {
            return stripcslashes(substr($v, 1, -1));
        }
        return $v;
    }

    private function humano(int $bytes): string
    {
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($u) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $u[$i];
    }
}
