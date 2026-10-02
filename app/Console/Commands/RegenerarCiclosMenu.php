<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reescribe menu_cycles.cycle_data con el formato que lee cycles/Index.vue.
 *
 * POR QUE HACE FALTA
 *
 * La primera carga guardo el JSON transpuesto: un arreglo de dias, y adentro
 * de cada dia la lista de categorias. El front espera lo contrario: un
 * arreglo donde cada fila es una categoria y los dias son claves adentro.
 *
 *   applyStructure() arma las filas desde structure.costs y despues hace
 *     savedCycleData.find(row => row.dishCategoryId === cost.dish_category_id)
 *
 *   Con el formato viejo esa busqueda no encuentra nada nunca: la tabla se
 *   dibuja completa y todas las celdas salen vacias, sin ningun error.
 *
 * EL CONTRATO (lo que este comando escribe)
 *
 *   [
 *     {
 *       "id": 2039,                        // structure_costs.id -> el renglon
 *       "category": "ENTRADA",
 *       "dishCategoryId": 114,
 *       "costValue": 0.35,                 // piso del semaforo
 *       "costValueMax": 0.42,              // techo del semaforo
 *       "days": {
 *         "1": { "dish_id": 6863, "dish_name": "Cebiche Pescado",
 *                "level_id": null, "calories": "0", "price": "0.00" },
 *         "3": { ... }
 *       },
 *       "legacy": { "curso": 114, "sub": 11401 }
 *     }
 *   ]
 *
 * DECISIONES QUE NO SON OBVIAS
 *
 * 1. Las filas salen de la ESTRUCTURA, no del ciclo. Se emiten todas las
 *    filas del servicio aunque el ciclo no use alguna. Asi el boton "Copiar
 *    ciclo" (que usa cycle_data tal cual, sin pasar por applyStructure)
 *    muestra la estructura completa.
 *
 * 2. Una posicion vacia se representa por AUSENCIA de esa clave de dia.
 *    No es una perdida: el front hace normalizeDays() y el store() hace
 *    array_filter(), o sea que una entrada vacia se descartaria igual. La
 *    informacion "este dia no tiene plato" se conserva porque la fila existe
 *    (viene de la estructura) y el dia no esta.
 *
 * 3. days = el dia MAYOR, no la cantidad de dias distintos. El front dibuja
 *    columnas 1..days y busca row.days[i]; con dias salteados, contar
 *    esconderia los ultimos. Hoy los 8,123 ciclos son contiguos y da igual,
 *    pero la definicion correcta no depende de eso.
 *
 * 4. dish_category_id sale de map_curso_sub, la MISMA tabla que alimento
 *    structure_costs. No son dos calculos que coinciden: es el mismo dato
 *    leido dos veces. Esa es la garantia de que el cruce del front funcione.
 *
 * 5. calories y price salen de dish_recipes cuando el plato tiene receta.
 *    Los platos migrados no la tienen todavia, asi que van en 0 y el
 *    semaforo de esas filas va a decir "Costos Muy Bajos" hasta que se
 *    carguen los quebrados. No rompe nada.
 */
class RegenerarCiclosMenu extends Command
{
    protected $signature = 'migracion:regenerar-ciclos
        {--stg=mig_tiburon : Nombre del esquema de staging}
        {--limite=0 : Procesar solo los primeros N ciclos (0 = todos)}
        {--solo= : Procesar un unico ciclo por su legacy_key}
        {--dry : No escribe nada: muestra el JSON del primer ciclo y sale}';

    protected $description = 'Reescribe cycle_data con el formato que lee la pantalla de Ciclos';

    private string $stg;

    /** [campo|base|catmnu] => lista de filas de la estructura, en orden de pantalla */
    private array $filas = [];
    /** [campo|base|catmnu] => [curso|sub => posicion dentro de la lista] */
    private array $indice = [];
    /** [nCodPlato viejo] => dish_id nuevo */
    private array $plato = [];
    /** [dish_id] => [name, level_id, calories, price] */
    private array $info = [];

    public function handle(): int
    {
        $this->stg = (string) $this->option('stg');

        $this->line('');
        $this->info('Regenerando cycle_data con el formato de la pantalla de Ciclos');
        $this->line('  staging: ' . $this->stg);
        $this->line('');

        if (!$this->cargarCatalogos()) {
            return self::FAILURE;
        }

        $ciclos = $this->ciclosAProcesar();
        $total  = $ciclos->count();

        if ($total === 0) {
            $this->error('No hay ciclos para procesar.');
            return self::FAILURE;
        }
        $this->line("Ciclos a procesar: {$total}");
        $this->line('');

        if ($this->option('dry')) {
            return $this->ensayo($ciclos->first());
        }

        $barra = $this->output->createProgressBar($total);
        $barra->start();

        $ok = 0; $err = 0; $slots = 0; $vacios = 0; $errores = [];

        foreach ($ciclos as $c) {
            try {
                $r = $this->armar($c);
                if ($r === null) {
                    throw new \RuntimeException('el ciclo no produjo ninguna fila');
                }

                DB::table('menu_cycles')
                    ->where('id', $c->menu_cycle_id)
                    ->update([
                        'cycle_data' => json_encode($r['filas'], JSON_UNESCAPED_UNICODE),
                        'days'       => $r['days'],
                        'updated_at' => now(),
                    ]);

                DB::table("{$this->stg}.map_ciclo")
                    ->where('legacy_key', $c->legacy_key)
                    ->update(['estado_json' => 'cargado', 'error_detalle' => null]);

                $ok++; $slots += $r['slots']; $vacios += $r['vacios'];
            } catch (\Throwable $e) {
                $err++;
                $errores[] = $c->legacy_key . ': ' . $e->getMessage();
                DB::table("{$this->stg}.map_ciclo")
                    ->where('legacy_key', $c->legacy_key)
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

        $this->table(
            ['concepto', 'cantidad'],
            [
                ['ciclos reescritos', number_format($ok)],
                ['ciclos con error',  number_format($err)],
                ['platos escritos',   number_format($slots)],
                ['posiciones vacias', number_format($vacios)],
            ]
        );

        if ($errores) {
            $this->line('');
            $this->warn('Errores (primeros 15):');
            foreach (array_slice($errores, 0, 15) as $e) {
                $this->line('   ' . $e);
            }
        }

        $this->verificar();

        return $err === 0 ? self::SUCCESS : self::FAILURE;
    }

    /* ====================================================================
       CATALOGOS
       ==================================================================== */

    private function cargarCatalogos(): bool
    {
        /* --- las filas de cada estructura, en el orden en que se ven ----- */
        $filas = DB::table("{$this->stg}.plan_structure_cost")
            ->select('nCodCampo', 'nTipBasProd', 'nTipCatMnu', 'curso', 'sub',
                     'structure_cost_id', 'dish_category_id', 'nombre',
                     'costo_total', 'costo_total_sup')
            ->whereNotNull('structure_cost_id')
            ->orderBy('structure_cost_id')
            ->get();

        foreach ($filas as $f) {
            $k = $f->nCodCampo . '|' . $f->nTipBasProd . '|' . $f->nTipCatMnu;
            $this->filas[$k][] = [
                'id'             => (int) $f->structure_cost_id,
                'category'       => (string) $f->nombre,
                'dishCategoryId' => (int) $f->dish_category_id,
                'costValue'      => (float) ($f->costo_total ?? 0),
                'costValueMax'   => (float) ($f->costo_total_sup ?? 0),
                'legacy'         => ['curso' => (int) $f->curso, 'sub' => (int) $f->sub],
            ];
            $this->indice[$k][$f->curso . '|' . $f->sub] = count($this->filas[$k]) - 1;
        }

        $this->line('  estructuras cargadas: ' . number_format(count($this->filas))
                  . ' (' . number_format($filas->count()) . ' renglones)');

        /* --- platos: id viejo -> id nuevo -------------------------------- */
        foreach (DB::table("{$this->stg}.map_dish")
                   ->whereNotNull('dish_id')->select('nCodPlato', 'dish_id')->get() as $p) {
            $this->plato[(int) $p->nCodPlato] = (int) $p->dish_id;
        }
        $this->line('  platos mapeados: ' . number_format(count($this->plato)));

        /* --- nombre, receta, calorias y costo de cada plato -------------- */
        $recetas = DB::table('dish_recipes')
            ->selectRaw('dish_id, MIN(id) AS rid')
            ->groupBy('dish_id');

        $ds = DB::table('dishes as d')
            ->leftJoinSub($recetas, 'x', fn ($j) => $j->on('x.dish_id', '=', 'd.id'))
            ->leftJoin('dish_recipes as r', 'r.id', '=', 'x.rid')
            ->select('d.id', 'd.name', 'r.level_id', 'r.total_calories', 'r.total_cost')
            ->get();

        $conReceta = 0;
        foreach ($ds as $d) {
            $this->info[(int) $d->id] = [
                'name'     => (string) ($d->name ?? ''),
                'level_id' => $d->level_id !== null ? (int) $d->level_id : null,
                'calories' => $d->total_calories !== null ? (string) (float) $d->total_calories : '0',
                'price'    => $d->total_cost !== null ? number_format((float) $d->total_cost, 2, '.', '') : '0.00',
            ];
            if ($d->level_id !== null) { $conReceta++; }
        }
        $this->line('  platos en el destino: ' . number_format(count($this->info))
                  . ' (' . number_format($conReceta) . ' con receta)');
        $this->line('');

        if (!$this->filas) {
            $this->error('plan_structure_cost esta vacio o sin structure_cost_id. Corre el 38, 39 y 40.');
            return false;
        }
        return true;
    }

    private function ciclosAProcesar()
    {
        $q = DB::table("{$this->stg}.map_ciclo")
            ->whereNotNull('menu_cycle_id')
            ->select('legacy_key', 'nCodCampo', 'nTipBasProd', 'nTipCatMnu',
                     'nNumSem', 'menu_cycle_id')
            ->orderBy('legacy_key');

        if ($solo = $this->option('solo')) {
            $q->where('legacy_key', $solo);
        }
        if (($lim = (int) $this->option('limite')) > 0) {
            $q->limit($lim);
        }
        return $q->get();
    }

    /* ====================================================================
       ARMADO DE UN CICLO
       ==================================================================== */

    private function armar(object $c): ?array
    {
        $k = $c->nCodCampo . '|' . $c->nTipBasProd . '|' . $c->nTipCatMnu;

        if (!isset($this->filas[$k])) {
            throw new \RuntimeException("el servicio {$k} no tiene estructura");
        }

        /* copia fresca de las filas de la estructura */
        $filas = $this->filas[$k];
        foreach ($filas as $i => $_) {
            $filas[$i]['days'] = [];
        }

        /* comparacion como texto: en el staging todo es VARCHAR y asi usa ix_llave */
        $origen = DB::table("{$this->stg}.stg_ciclo_menu")
            ->where('nCodCampo',   (string) $c->nCodCampo)
            ->where('nTipBasProd', (string) $c->nTipBasProd)
            ->where('nTipCatMnu',  (string) $c->nTipCatMnu)
            ->where('nNumSem',     (string) $c->nNumSem)
            ->where('nTipPlato', '<>', '0')
            ->get();

        $vistos = [];
        $diaMax = 0;
        $slots = 0; $vacios = 0;

        foreach ($origen as $f) {
            $dia = (int) $f->nDia;
            if ($dia <= 0) { continue; }

            $curso = (int) $f->nTipPlato;
            $sub   = (int) $f->nTipsubpla;

            /* los 13 slots repetidos de toda la base traen el mismo plato */
            $clave = "{$dia}|{$curso}|{$sub}";
            if (isset($vistos[$clave])) { continue; }
            $vistos[$clave] = true;

            $pos = $this->indice[$k]["{$curso}|{$sub}"] ?? null;
            if ($pos === null) {
                throw new \RuntimeException("el par {$curso}:{$sub} no tiene renglon en la estructura");
            }

            if ($dia > $diaMax) { $diaMax = $dia; }

            $viejo = (int) $f->nCodPlato;
            if ($viejo === 0) {
                /* posicion vacia: la fila existe, el dia no. Asi lo espera el front. */
                $vacios++;
                continue;
            }

            $dishId = $this->plato[$viejo] ?? null;
            if ($dishId === null) {
                throw new \RuntimeException("el plato {$viejo} no tiene equivalente");
            }
            $i = $this->info[$dishId] ?? null;
            if ($i === null) {
                throw new \RuntimeException("el plato {$dishId} no existe en dishes");
            }

            $filas[$pos]['days'][(string) $dia] = [
                'dish_id'   => $dishId,
                'dish_name' => $i['name'],
                'level_id'  => $i['level_id'],
                'calories'  => $i['calories'],
                'price'     => $i['price'],
            ];
            $slots++;
        }

        if ($diaMax === 0) { return null; }
        if ($diaMax > 31) {
            throw new \RuntimeException("el ciclo tiene {$diaMax} dias y el maximo es 31");
        }

        /* days como objeto JSON aunque quede vacio */
        foreach ($filas as $i => $_) {
            $filas[$i]['days'] = (object) $filas[$i]['days'];
        }

        return ['filas' => $filas, 'days' => $diaMax, 'slots' => $slots, 'vacios' => $vacios];
    }

    /* ====================================================================
       ENSAYO Y VERIFICACION
       ==================================================================== */

    private function ensayo(object $c): int
    {
        $this->warn('ENSAYO: no se escribe nada.');
        $this->line('');
        $this->line("Ciclo: {$c->legacy_key}  (menu_cycle_id {$c->menu_cycle_id})");

        $r = $this->armar($c);
        if ($r === null) {
            $this->error('El ciclo no produjo ninguna fila.');
            return self::FAILURE;
        }

        $this->line("days: {$r['days']}   platos: {$r['slots']}   vacios: {$r['vacios']}");
        $this->line('renglones: ' . count($r['filas']));
        $this->line('');
        $this->line('Primeros 3 renglones del JSON:');
        $this->line(json_encode(array_slice($r['filas'], 0, 3),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }

    private function verificar(): void
    {
        $this->line('');
        $this->info('Verificacion');

        $malos = DB::table('menu_cycles')
            ->whereNotNull('legacy_key')
            ->whereRaw('NOT JSON_VALID(cycle_data)')
            ->count();

        $sinFilas = DB::table('menu_cycles')
            ->whereNotNull('legacy_key')
            ->whereRaw('JSON_LENGTH(cycle_data) = 0')
            ->count();

        $sinEstructura = DB::table('menu_cycles as cy')
            ->whereNotNull('cy.serviceable_id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('structures as st')
                ->whereColumn('st.serviceable_id', 'cy.serviceable_id'))
            ->count();

        $this->table(['control', 'resultado', 'esperado'], [
            ['cycle_data invalido',            $malos,        0],
            ['ciclos sin ningun renglon',      $sinFilas,     0],
            ['ciclos sin estructura',          $sinEstructura, 0],
        ]);

        $this->line('');
        $this->line('Para verlo en la pantalla: Configuracion de Ciclos, eleji');
        $this->line('una mina, unidad, comedor y servicio que tengan ciclos.');
    }
}
