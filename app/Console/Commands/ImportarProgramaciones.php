<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa las programaciones semanales de BdTiburonR3 a weekly_programs,
 * weekly_program_items y daily_portions.
 *
 * ORIGEN (cargado en el staging desde localhost\MSSQL08 con bcp)
 *   stg_prog_cab  <- PlaMProgSem   cabecera: comedor, meal, ciclo, fechas
 *   stg_prog_det  <- PlaDProgSem   un plato por (fecha, curso, subplato)
 *   stg_prog_rac  <- PlaDProgRac   raciones por dia
 *
 * MAPEOS (los mismos de los ciclos)
 *   comedor   map_serviceable.cafe_id  (las plantillas BASE Master... no tienen
 *             cafe y weekly_programs.cafe_id es NOT NULL: se omiten)
 *   categoria map_curso_sub (nTipPlato, nTipSubPla), la misma que usa cycle_data
 *   plato     map_dish.dish_id
 *
 * MEAL_TYPE
 *   La pantalla guarda 'Almuerzo', no 'almuerzo' como map_meal_type. Y los
 *   reportes buscan las raciones con fecha . '_' . meal_type, asi que items y
 *   daily_portions tienen que llevar EXACTAMENTE el mismo texto o salen en 0.
 *
 * USO
 *   php artisan migracion:importar-programaciones --dry-run
 *   php artisan migracion:importar-programaciones --solo=31799
 *   php artisan migracion:importar-programaciones --desde=2024-01-01 --user=1
 *
 * ES IDEMPOTENTE: upsert por weekly_programs.legacy_key (= nNumProgsem); los
 * items y raciones de esa programacion se reescriben completos.
 */
class ImportarProgramaciones extends Command
{
    protected $signature = 'migracion:importar-programaciones
        {--desde=2024-01-01 : Solo programaciones con dFecIni desde esta fecha}
        {--estados=A,U      : cEstado de cabecera a importar}
        {--user=1           : users.id al que se asignan (el origen solo tiene UsuAdmin)}
        {--status=aprobado  : weekly_programs.status de lo importado}
        {--solo=            : Un unico nNumProgsem}
        {--limit=0          : Procesar solo N programaciones (0 = todas)}
        {--dry-run          : No escribe; muestra la primera programacion}
        {--stg=mig_tiburon  : Esquema de staging}';

    protected $description = 'Importa las programaciones semanales de BdTiburonR3';

    /** nTipCatMnu => meal_type con la capitalizacion de la pantalla de planificacion. */
    private const MEAL = [
        1  => 'Desayuno',
        2  => 'Almuerzo',
        3  => 'Cena',
        4  => 'Refrigerio',
        5  => 'Coffee Break',
        6  => 'Refrigerio Cuna',
        7  => 'Desayuno (Dieta)',
        8  => 'Almuerzo (Dieta)',
        9  => 'Cena (Dieta)',
        22 => 'Almuerzo Ejecutivo',
    ];

    private string $stg;
    private array $cafe  = [];   // "campo|base" => cafe_id
    private array $cat   = [];   // "curso|sub"  => dish_category_id
    private array $plato = [];   // nCodPlato    => dish_id
    private int $porcCorregidos = 0;

    public function handle(): int
    {
        $this->stg = $this->option('stg');

        $this->info('Programaciones  BdTiburonR3 -> weekly_programs');
        $this->line('');

        if (!DB::getSchemaBuilder()->hasColumn('weekly_programs', 'legacy_key')) {
            $this->error('Falta weekly_programs.legacy_key: correr php artisan migrate.');
            return self::FAILURE;
        }
        if (!DB::table('users')->where('id', (int) $this->option('user'))->exists()) {
            $this->error("No existe users.id = {$this->option('user')}.");
            return self::FAILURE;
        }

        $this->cargarCatalogos();

        $cabeceras = $this->cabecerasAProcesar();
        $total = $cabeceras->count();
        if ($total === 0) {
            $this->warn('No hay programaciones que cumplan el filtro.');
            return self::SUCCESS;
        }
        $this->info("Programaciones a procesar: {$total}");

        $barra = $this->output->createProgressBar($total);
        $barra->start();

        $ok = 0; $omitidas = []; $items = 0; $raciones = 0;

        foreach ($cabeceras as $cab) {
            try {
                $r = $this->armar($cab);
            } catch (\Throwable $e) {
                $omitidas[] = "{$cab->nNumProgsem}: {$e->getMessage()}";
                $barra->advance();
                continue;
            }

            if ($this->option('dry-run')) {
                $barra->clear();
                $this->mostrar($cab, $r);
                return self::SUCCESS;
            }

            $this->guardar($cab, $r);
            $ok++;
            $items    += count($r['items']);
            $raciones += count($r['portions']);
            $barra->advance();
        }

        $barra->finish();
        $this->line('');
        $this->line('');
        $this->table(['concepto', 'cantidad'], [
            ['programaciones cargadas', number_format($ok)],
            ['items (platos por dia)',  number_format($items)],
            ['dias con raciones',       number_format($raciones)],
            ['% > 999.99 guardados 100', number_format($this->porcCorregidos)],
            ['omitidas',                number_format(count($omitidas))],
        ]);

        if ($omitidas) {
            $this->warn('Omitidas (las 10 primeras):');
            foreach (array_slice($omitidas, 0, 10) as $o) $this->line("   {$o}");
        }

        return $omitidas ? self::FAILURE : self::SUCCESS;
    }

    private function cargarCatalogos(): void
    {
        foreach (DB::table("{$this->stg}.map_serviceable")->where('tipo_base', 'comedor')
                     ->whereNotNull('cafe_id')->get() as $r) {
            $this->cafe[$r->nCodCampo . '|' . $r->nTipBasProd] = (int) $r->cafe_id;
        }
        foreach (DB::table("{$this->stg}.map_curso_sub")->whereNotNull('dish_category_id')->get() as $r) {
            $this->cat[$r->nTipPlato . '|' . $r->nTipsubpla] = (int) $r->dish_category_id;
        }
        foreach (DB::table("{$this->stg}.map_dish")->whereNotNull('dish_id')->get() as $r) {
            $this->plato[(int) $r->nCodPlato] = (int) $r->dish_id;
        }

        $this->line(sprintf('  catalogos: %d comedores, %d pares curso/subplato, %d platos',
            count($this->cafe), count($this->cat), count($this->plato)));
        $this->line('');
    }

    private function cabecerasAProcesar()
    {
        $q = DB::table("{$this->stg}.stg_prog_cab as c")
            ->join("{$this->stg}.map_serviceable as ms", function ($j) {
                $j->on('ms.nCodCampo', '=', 'c.nCodCampo')->on('ms.nTipBasProd', '=', 'c.nTipBasProd');
            })
            ->where('ms.tipo_base', 'comedor')     // plantillas y basura fuera
            ->select('c.*');

        if ($solo = $this->option('solo')) {
            $q->where('c.nNumProgsem', (int) $solo);
        } else {
            $estados = array_filter(array_map('trim', explode(',', $this->option('estados'))));
            $q->where('c.dFecIni', '>=', $this->option('desde'))->whereIn('c.cEstado', $estados);
        }

        if (($n = (int) $this->option('limit')) > 0) $q->limit($n);

        return $q->orderBy('c.dFecIni')->orderBy('c.nNumProgsem')->get();
    }

    private function armar(object $cab): array
    {
        $mealType = self::MEAL[(int) $cab->nTipCatMnu] ?? null;
        if ($mealType === null) throw new \RuntimeException("nTipCatMnu {$cab->nTipCatMnu} sin meal_type");

        $cafeId = $this->cafe[$cab->nCodCampo . '|' . $cab->nTipBasProd] ?? null;
        if ($cafeId === null) throw new \RuntimeException("comedor {$cab->nCodCampo}|{$cab->nTipBasProd} sin cafe_id");

        // Solo filas vigentes y con plato: dish_id es NOT NULL, una posicion
        // vacia no tiene representacion en weekly_program_items.
        $det = DB::table("{$this->stg}.stg_prog_det")
            ->where('nNumProgsem', $cab->nNumProgsem)
            ->where('cEstado', 'A')->where('nCodPlato', '<>', 0)
            ->orderBy('dFecProgSem')->orderBy('nOrden')->orderBy('nTipPlato')->orderBy('nTipSubPla')
            ->get();

        if ($det->isEmpty()) throw new \RuntimeException('sin detalle vigente');

        $items = [];
        foreach ($det as $d) {
            $catId = $this->cat[$d->nTipPlato . '|' . $d->nTipSubPla] ?? null;
            if ($catId === null) throw new \RuntimeException("par {$d->nTipPlato}|{$d->nTipSubPla} sin categoria");
            $dishId = $this->plato[(int) $d->nCodPlato] ?? null;
            if ($dishId === null) throw new \RuntimeException("plato {$d->nCodPlato} sin dish_id");

            // percentage es decimal(5,2). 100-300% es legitimo (dos jugos por
            // comensal); 5040% son errores de digitacion del origen (su nNumRac
            // da 21,924 raciones para 435 comensales): se guardan como 100.
            $pct = (float) $d->nPorcDistri;
            if ($pct > 999.99) {
                $pct = 100.0;
                $this->porcCorregidos++;
            }

            $items[] = [
                'date'             => $d->dFecProgSem,
                'meal_type'        => $mealType,
                'dish_category_id' => $catId,
                'dish_id'          => $dishId,
                'percentage'       => $pct,
            ];
        }

        // Raciones por dia. 16 dias del origen estan repetidos con el mismo
        // estado: se toma el mayor.
        $portions = DB::table("{$this->stg}.stg_prog_rac")
            ->where('nNumProgSem', $cab->nNumProgsem)->where('cEstado', 'A')
            ->groupBy('dFecProg')->orderBy('dFecProg')
            ->selectRaw('dFecProg AS date, MAX(nNumRac) AS n')
            ->get()
            ->map(fn($p) => ['date' => $p->date, 'meal_type' => $mealType, 'portions_count' => (int) $p->n])
            ->all();

        return ['meal_type' => $mealType, 'cafe_id' => $cafeId, 'items' => $items, 'portions' => $portions];
    }

    private function guardar(object $cab, array $r): void
    {
        DB::transaction(function () use ($cab, $r) {
            $now = now();
            $datos = [
                'cafe_id'      => $r['cafe_id'],
                'structure_id' => null,
                'meal_type'    => $r['meal_type'],
                'start_date'   => $cab->dFecIni,
                'end_date'     => $cab->dFecFin,
                'status'       => $this->option('status'),
                'user_id'      => (int) $this->option('user'),
                'created_at'   => $cab->dFecReg ?? $now,   // el listado ordena por created_at
                'updated_at'   => $now,
            ];

            $legacyKey = (string) $cab->nNumProgsem;
            $id = DB::table('weekly_programs')->where('legacy_key', $legacyKey)->value('id');

            if ($id) {
                DB::table('weekly_programs')->where('id', $id)->update($datos);
                DB::table('weekly_program_items')->where('weekly_program_id', $id)->delete();
                DB::table('daily_portions')->where('weekly_program_id', $id)->delete();
            } else {
                $id = DB::table('weekly_programs')->insertGetId($datos + ['legacy_key' => $legacyKey]);
            }

            $conPrograma = fn(array $row) => $row + [
                'weekly_program_id' => $id, 'created_at' => $now, 'updated_at' => $now,
            ];
            foreach (array_chunk(array_map($conPrograma, $r['items']), 500) as $lote) {
                DB::table('weekly_program_items')->insert($lote);
            }
            if ($r['portions']) {
                DB::table('daily_portions')->insert(array_map($conPrograma, $r['portions']));
            }
        });
    }

    private function mostrar(object $cab, array $r): void
    {
        $this->line('');
        $this->info("DRY RUN  nNumProgsem = {$cab->nNumProgsem}  (cEstado {$cab->cEstado})");
        $this->line("  cafe_id    : {$r['cafe_id']}");
        $this->line("  meal_type  : {$r['meal_type']}");
        $this->line("  fechas     : {$cab->dFecIni} .. {$cab->dFecFin}  (ciclo {$cab->nNumSem})");
        $this->line('  items      : ' . count($r['items']));
        $this->line('');
        $this->table(['fecha', 'raciones'], array_map(fn($p) => [$p['date'], $p['portions_count']], $r['portions']));
        $this->table(['fecha', 'categoria', 'dish_id', '%'],
            array_map(fn($i) => [$i['date'], $i['dish_category_id'], $i['dish_id'], $i['percentage']],
                array_slice($r['items'], 0, 15)));
    }
}
