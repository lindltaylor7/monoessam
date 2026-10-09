<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Genera los 8,127 ciclos de BdTiburonR3 en menu_cycles.
 *
 * Ubicacion sugerida: app/Console/Commands/GenerarCiclosMenu.php
 *
 * REQUISITOS PREVIOS
 *   1. La migracion 04 aplicada (menu_cycles con meal_type, legacy_key, etc.)
 *   2. mig_tiburon cargada y 09_poblar_mapeos.sql ejecutado
 *   3. Las columnas de ids nuevos completas:
 *        map_dish_category.dish_category_id
 *        map_dish.dish_id
 *        map_serviceable.serviceable_id
 *      El comando verifica esto antes de escribir nada y aborta si falta.
 *
 * USO
 *   php artisan migracion:generar-ciclos --dry-run --limit=3
 *   php artisan migracion:generar-ciclos --solo=305-305-2-354
 *   php artisan migracion:generar-ciclos
 *   php artisan migracion:generar-ciclos --rehacer
 *
 * ES IDEMPOTENTE: hace upsert por legacy_key. Se puede cortar y reanudar.
 */
class GenerarCiclosMenu extends Command
{
    protected $signature = 'migracion:generar-ciclos
        {--dry-run        : No escribe nada; muestra el JSON del primer ciclo}
        {--limit=0        : Procesar solo N ciclos (0 = todos)}
        {--solo=          : Un unico legacy_key, ej 305-305-2-354}
        {--rehacer        : Reprocesa tambien los ya marcados como generados}
        {--stg=mig_tiburon : Nombre del esquema de staging}';

    protected $description = 'Genera menu_cycles.cycle_data desde el ciclico de BdTiburonR3';

    /** Version del esquema del JSON. Subir si cambia la forma. */
    private const SCHEMA_VERSION = 1;

    private string $stg;
    private array $catCurso  = [];   // nTipPlato   => ['id'=>, 'familia'=>, 'migrar'=>]
    private array $catOpcion = [];   // nTipPlato   => [opcion => dish_category_id]
    private array $catPlato  = [];   // nCodPlato   => dish_id|null
    private array $catMeal   = [];   // nTipCatMnu  => meal_type
    private array $catServ   = [];   // "campo|base"=> ['serviceable_id'=>, 'tipo'=>, 'comedor'=>]

    public function handle(): int
    {
        $this->stg = $this->option('stg');

        $this->info('Generador de ciclos  BdTiburonR3 -> menu_cycles');
        $this->line('');

        if (!$this->cargarCatalogos())   return self::FAILURE;
        if (!$this->verificarPendientes()) return self::FAILURE;

        $ciclos = $this->ciclosAProcesar();
        $total  = $ciclos->count();

        if ($total === 0) {
            $this->warn('No hay ciclos por procesar. Usa --rehacer para reprocesar los ya generados.');
            return self::SUCCESS;
        }

        $this->info("Ciclos a procesar: {$total}");
        $this->line('');

        $barra = $this->output->createProgressBar($total);
        $barra->start();

        $ok = 0; $conError = 0; $slotsTotal = 0; $vaciosTotal = 0;

        foreach ($ciclos as $ciclo) {
            try {
                $resultado = $this->generarUno($ciclo);

                if ($resultado === null) {   // ciclo sin slots utiles
                    $conError++;
                    $barra->advance();
                    continue;
                }

                $slotsTotal  += $resultado['slots'];
                $vaciosTotal += $resultado['vacios'];

                if ($this->option('dry-run')) {
                    $barra->clear();
                    $this->line('');
                    $this->info("DRY RUN  legacy_key = {$ciclo->legacy_key}");
                    $this->line("  nombre      : {$resultado['name']}");
                    $this->line("  meal_type   : {$resultado['meal_type']}");
                    $this->line("  days        : {$resultado['days']}");
                    $this->line("  serviceable : " . ($resultado['serviceable_id'] ?? 'NULL (plantilla)'));
                    $this->line("  is_current  : " . ($ciclo->is_current ? 'si' : 'no'));
                    $this->line("  slots       : {$resultado['slots']}  (vacios: {$resultado['vacios']})");
                    $this->line('');
                    $this->line(json_encode(
                        $resultado['cycle_data'],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ));
                    $this->line('');
                    return self::SUCCESS;   // en dry-run solo mostramos uno
                }

                $this->guardar($ciclo, $resultado);
                $ok++;

            } catch (\Throwable $e) {
                $conError++;
                DB::connection()->table("{$this->stg}.map_ciclo")
                    ->where('legacy_key', $ciclo->legacy_key)
                    ->update([
                        'estado_json'   => 'error',
                        'error_detalle' => mb_substr($e->getMessage(), 0, 500),
                    ]);
            }

            $barra->advance();
        }

        $barra->finish();
        $this->line('');
        $this->line('');
        $this->resumen($ok, $conError, $slotsTotal, $vaciosTotal);

        return $conError > 0 ? self::FAILURE : self::SUCCESS;
    }

    /* ====================================================================
       CATALOGOS
       ==================================================================== */

    private function cargarCatalogos(): bool
    {
        foreach (DB::table("{$this->stg}.map_dish_category")->get() as $r) {
            $this->catCurso[(int) $r->nTipPlato] = [
                'id'      => $r->dish_category_id,
                'familia' => $r->familia,
                'migrar'  => (int) $r->migrar,
                'desc'    => $r->descripcion,
            ];
        }
        /* El destino modela la opcion como una categoria aparte:
           'FONDO (Aderezo) 01' = 109 y 'FONDO (ADEREZO) 02' = 161.
           map_curso_opcion resuelve cada par (curso, opcion) a su categoria
           cuando la variante existe; cuando no, cae a la base y el numero de
           opcion queda igual guardado en el JSON. */
        if ($this->tabla_existe('map_curso_opcion')) {
            foreach (DB::table("{$this->stg}.map_curso_opcion")->get() as $r) {
                if ($r->dish_category_id !== null) {
                    $this->catOpcion[(int) $r->nTipPlato][(int) $r->opcion] = (int) $r->dish_category_id;
                }
            }
        }

        foreach (DB::table("{$this->stg}.map_dish")->get() as $r) {
            $this->catPlato[(int) $r->nCodPlato] = $r->dish_id;
        }
        foreach (DB::table("{$this->stg}.map_meal_type")->where('migrar', 1)->get() as $r) {
            $this->catMeal[(int) $r->nTipCatMnu] = $r->meal_type;
        }
        foreach (DB::table("{$this->stg}.map_serviceable")->get() as $r) {
            $this->catServ[$r->nCodCampo . '|' . $r->nTipBasProd] = [
                'serviceable_id' => $r->serviceable_id,
                'tipo'           => $r->tipo_base,
                'comedor'        => $r->comedor,
                'campamento'     => $r->campamento,
            ];
        }

        $variantes = 0;
        foreach ($this->catOpcion as $curso => $ops) {
            foreach ($ops as $op => $id) {
                if ($op > 1 && $id !== ($this->catCurso[$curso]['id'] ?? null)) $variantes++;
            }
        }

        $this->line(sprintf(
            '  catalogos: %d cursos, %d platos, %d meal_types, %d comedores',
            count($this->catCurso), count($this->catPlato),
            count($this->catMeal), count($this->catServ)
        ));
        $this->line(sprintf(
            '  opciones con categoria propia (FONDO ... 02 y similares): %d',
            $variantes
        ));

        return true;
    }

    /** map_curso_opcion es opcional: si no esta, se usa la categoria base. */
    private function tabla_existe(string $tabla): bool
    {
        return count(DB::select(
            "SELECT 1 FROM information_schema.tables
              WHERE table_schema = ? AND table_name = ? LIMIT 1",
            [$this->stg, $tabla]
        )) > 0;
    }

    /**
     * Aborta si faltan ids nuevos. Sin esto el JSON saldria con dish_id null
     * en todos lados y el error solo se veria despues de cargar 8,127 ciclos.
     */
    private function verificarPendientes(): bool
    {
        $problemas = [];

        /* Solo interesan los cursos que el ciclico USA. El catalogo viejo trae
           varios sin una sola fila (BEBIDA ALCOHOLICA, LIMPIEZA, COMBINACION O
           COMBO...) y exigirles categoria bloquearia la carga sin motivo. */
        $cursos = DB::select("
            SELECT COUNT(*) AS n FROM (
              SELECT DISTINCT CAST(c.nTipPlato AS UNSIGNED) AS curso
              FROM {$this->stg}.stg_ciclo_menu c
              WHERE c.nTipPlato <> '0'
            ) u
            JOIN {$this->stg}.map_dish_category m ON m.nTipPlato = u.curso
            WHERE m.migrar = 1 AND m.dish_category_id IS NULL
        ")[0]->n;
        if ($cursos > 0) $problemas[] = "map_dish_category: {$cursos} cursos USADOS sin dish_category_id";

        // solo los platos realmente usados por el ciclico
        $platos = DB::select("
            SELECT COUNT(*) AS n FROM (
              SELECT DISTINCT CAST(c.nCodPlato AS UNSIGNED) AS cod
              FROM {$this->stg}.stg_ciclo_menu c
              WHERE c.nCodPlato <> '0' AND c.nTipPlato <> '0'
            ) u
            JOIN {$this->stg}.map_dish m ON m.nCodPlato = u.cod
            WHERE m.dish_id IS NULL
        ")[0]->n;
        if ($platos > 0) $problemas[] = "map_dish: {$platos} platos usados sin dish_id";

        $comedores = DB::table("{$this->stg}.map_serviceable")
            ->where('tipo_base', 'comedor')->whereNull('serviceable_id')->count();
        if ($comedores > 0) $problemas[] = "map_serviceable: {$comedores} comedores sin serviceable_id";

        $meals = DB::table("{$this->stg}.map_meal_type")
            ->where('migrar', 1)->whereNull('meal_type')->count();
        if ($meals > 0) $problemas[] = "map_meal_type: {$meals} categorias sin meal_type";

        if ($problemas) {
            $this->line('');
            $this->error('Faltan ids del destino. No se escribe nada:');
            foreach ($problemas as $p) $this->line("   - {$p}");
            $this->line('');
            $this->line('  Hay que crear primero las filas en backendlaravel y anotar');
            $this->line('  sus ids en las tablas map_*. Recien despues correr esto.');
            return false;
        }

        $this->line('  ids del destino: completos');
        $this->line('');
        return true;
    }

    private function ciclosAProcesar()
    {
        $q = DB::table("{$this->stg}.map_ciclo")->where('slots', '>', 0);

        if ($solo = $this->option('solo')) {
            $q->where('legacy_key', $solo);
        } elseif (!$this->option('rehacer')) {
            $q->where('estado_json', 'pendiente');
        }

        if (($n = (int) $this->option('limit')) > 0) $q->limit($n);

        return $q->orderBy('nCodCampo')->orderBy('nTipBasProd')
                 ->orderBy('nTipCatMnu')->orderBy('legacy_key')->get();
    }

    /* ====================================================================
       GENERACION DE UN CICLO
       ==================================================================== */

    private function generarUno(object $ciclo): ?array
    {
        // Se comparan como texto: en el staging todo es VARCHAR y asi se usa el
        // indice ix_llave. Comparar int contra varchar forzaria un full scan.
        $filas = DB::table("{$this->stg}.stg_ciclo_menu")
            ->where('nCodCampo',   (string) $ciclo->nCodCampo)
            ->where('nTipBasProd', (string) $ciclo->nTipBasProd)
            ->where('nTipCatMnu',  (string) $ciclo->nTipCatMnu)
            ->where('nNumSem',     (string) $ciclo->nNumSem)
            ->where('nTipPlato', '<>', '0')        // regla 3: descarta el artefacto
            ->get();

        if ($filas->isEmpty()) return null;

        // --- agrupar por dia y curso, deduplicando ------------------------
        // Los 13 slots repetidos de toda la base tienen el MISMO plato
        // (con_platos_diferentes = 0), asi que quedarse con uno no pierde nada.
        $porDia = [];
        $vistos = [];
        foreach ($filas as $f) {
            $dia  = (int) $f->nDia;
            if ($dia <= 0) continue;               // nDia = 0 es otro artefacto

            $curso = (int) $f->nTipPlato;
            $sub   = (int) $f->nTipsubpla;
            $clave = "{$dia}|{$curso}|{$sub}";
            if (isset($vistos[$clave])) continue;
            $vistos[$clave] = true;

            $porDia[$dia][$curso][] = [
                'sub'  => $sub,
                'plato'=> (int) $f->nCodPlato,
            ];
        }

        if (!$porDia) return null;

        ksort($porDia);

        // --- armar los dias ------------------------------------------------
        $daysData = [];
        $slots = 0; $vacios = 0;

        foreach ($porDia as $dia => $cursos) {
            ksort($cursos);
            $listaSlots = [];

            foreach ($cursos as $curso => $items) {
                $info = $this->catCurso[$curso] ?? null;
                if (!$info || !$info['migrar'] || $info['id'] === null) {
                    throw new \RuntimeException("curso {$curso} sin dish_category_id");
                }

                // Orden estable por el subplato original.
                usort($items, fn($a, $b) => $a['sub'] <=> $b['sub']);

                // LA REGLA DE LAS DOS CONVENCIONES.
                // Operativa (nTipPlato > 100): nTipsubpla = nTipPlato*100 + opcion,
                //   asi que la opcion sale del modulo 100.
                // Legacy (1-99): el subplato es una posicion arbitraria del menu
                //   impreso (ADITIVO usa 15 y 16, POSTRE usa 11), no un contador
                //   por curso. Se normaliza al orden de aparicion.
                $posicion = 0;
                $subAnterior = null;

                foreach ($items as $it) {
                    if ($info['familia'] === 'operativa' && $it['sub'] > 0
                        && intdiv($it['sub'], 100) === $curso) {
                        $opcion = $it['sub'] % 100;
                        if ($opcion === 0) $opcion = 1;      // 210 filas con sub = curso*100
                    } else {
                        if ($it['sub'] !== $subAnterior) {   // dense rank
                            $posicion++;
                            $subAnterior = $it['sub'];
                        }
                        $opcion = $posicion;
                    }

                    $dishId = $it['plato'] === 0
                        ? null                                // regla 2: slot vacio
                        : ($this->catPlato[$it['plato']] ?? null);

                    if ($it['plato'] !== 0 && $dishId === null) {
                        throw new \RuntimeException("plato {$it['plato']} sin dish_id");
                    }

                    /* la variante '0N' si existe; si no, la categoria base */
                    $catId = $this->catOpcion[$curso][$opcion] ?? $info['id'];

                    $listaSlots[] = [
                        'dish_category_id' => (int) $catId,
                        'option'           => $opcion,
                        'dish_id'          => $dishId,
                        'legacy_tipplato'  => $curso,
                        'legacy_subplato'  => $it['sub'],
                        'legacy_codplato'  => $it['plato'],
                    ];

                    $slots++;
                    if ($dishId === null) $vacios++;
                }
            }

            $daysData[] = ['day' => $dia, 'slots' => $listaSlots];
        }

        // --- cabecera -------------------------------------------------------
        $mealType = $this->catMeal[(int) $ciclo->nTipCatMnu] ?? null;
        if ($mealType === null) {
            throw new \RuntimeException("nTipCatMnu {$ciclo->nTipCatMnu} sin meal_type");
        }

        $serv = $this->catServ[$ciclo->nCodCampo . '|' . $ciclo->nTipBasProd] ?? null;
        if ($serv === null) {
            throw new \RuntimeException("par {$ciclo->nCodCampo}|{$ciclo->nTipBasProd} sin mapeo");
        }
        if ($serv['tipo'] === 'descartar') return null;

        // Las plantillas (BASE Master, Gold, Platinium...) no son un comedor:
        // van con serviceable_id nulo.
        $serviceableId = $serv['tipo'] === 'plantilla' ? null : $serv['serviceable_id'];

        $etiqueta = $serv['tipo'] === 'plantilla'
            ? 'Plantilla ' . $serv['comedor']
            : trim(($serv['campamento'] ? $serv['campamento'] . ' / ' : '') . $serv['comedor']);

        $name = sprintf('%s - %s - ciclo %s', $etiqueta, $mealType, $ciclo->nNumSem);

        // days: dias reales del ciclo, nunca el default 7.
        $days = count($daysData);

        return [
            'name'           => mb_substr($name, 0, 255),
            'meal_type'      => $mealType,
            'days'           => $days,
            'serviceable_id' => $serviceableId,
            'slots'          => $slots,
            'vacios'         => $vacios,
            'cycle_data'     => [
                'schema_version' => self::SCHEMA_VERSION,
                'meal_type'      => $mealType,
                'days'           => $days,
                'legacy'         => [
                    'campo'  => (int) $ciclo->nCodCampo,
                    'base'   => (int) $ciclo->nTipBasProd,
                    'catmnu' => (int) $ciclo->nTipCatMnu,
                    'numsem' => $ciclo->nNumSem,
                ],
                'days_data'      => $daysData,
            ],
        ];
    }

    /* ====================================================================
       ESCRITURA
       ==================================================================== */

    private function guardar(object $ciclo, array $r): void
    {
        DB::transaction(function () use ($ciclo, $r) {

            $datos = [
                'serviceable_id'       => $r['serviceable_id'],
                'name'                 => $r['name'],
                'meal_type'            => $r['meal_type'],
                'days'                 => $r['days'],
                'legacy_cycle_number'  => ctype_digit((string) $ciclo->nNumSem)
                                            ? (int) $ciclo->nNumSem : null,
                'legacy_key'           => $ciclo->legacy_key,
                'is_current'           => (int) $ciclo->is_current,
                'source_registered_at' => $ciclo->ult_fec_reg,
                'cycle_data'           => json_encode(
                    $r['cycle_data'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                'updated_at'           => now(),
            ];

            // Idempotente: legacy_key es unico, asi que reejecutar actualiza.
            $existente = DB::table('menu_cycles')
                ->where('legacy_key', $ciclo->legacy_key)->first();

            if ($existente) {
                DB::table('menu_cycles')->where('id', $existente->id)->update($datos);
                $id = $existente->id;
            } else {
                $datos['created_at'] = now();
                $id = DB::table('menu_cycles')->insertGetId($datos);
            }

            DB::table("{$this->stg}.map_ciclo")
                ->where('legacy_key', $ciclo->legacy_key)
                ->update([
                    'menu_cycle_id' => $id,
                    'estado_json'   => 'cargado',
                    'error_detalle' => null,
                ]);
        });
    }

    /* ====================================================================
       RESUMEN
       ==================================================================== */

    private function resumen(int $ok, int $err, int $slots, int $vacios): void
    {
        $this->info('Resultado');
        $this->table(
            ['concepto', 'obtenido', 'esperado si se procesa todo'],
            [
                ['ciclos cargados', number_format($ok),     '8,127'],
                ['ciclos con error', number_format($err),   '4 (los que solo tienen artefacto)'],
                ['slots escritos',  number_format($slots),  '876,378'],
                ['slots vacios',    number_format($vacios), '272,425'],
            ]
        );

        if ($err > 0) {
            $this->line('');
            $this->warn('Ciclos con error (los 10 primeros):');
            $filas = DB::table("{$this->stg}.map_ciclo")
                ->where('estado_json', 'error')
                ->select('legacy_key', 'error_detalle')->limit(10)->get();
            foreach ($filas as $f) {
                $this->line("   {$f->legacy_key}: {$f->error_detalle}");
            }
        }

        $this->line('');
        $this->line('Comprobacion en la base, para contrastar contra el diagnostico:');
        $this->line('  SELECT COUNT(*) FROM menu_cycles;                    -- 8123');
        $this->line('  SELECT SUM(is_current) FROM menu_cycles;             -- 165');
        $this->line('  SELECT days, COUNT(*) FROM menu_cycles GROUP BY days;-- 10 dias mayoritario');
    }
}
