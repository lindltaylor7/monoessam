<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa el recetario de Tiburon desde el CSV plano que produce SSMS.
 *
 * POR QUE EXISTE, SI YA HAY UN DishRecipesImport
 *
 * El importador de la pantalla web no puede leer este archivo, y no por un
 * detalle de configuracion: son tres incompatibilidades a la vez.
 *
 *   1. El CSV NO trae fila de encabezado. DishRecipesImport declara
 *      WithHeadingRow con headingRow() = 1, asi que se come la primera linea
 *      de datos como cabecera y despues busca las claves 'cnomplato' /
 *      'cnomprod' / 'ncantbas', que nunca existen. Toda fila sale descartada.
 *      El sintoma es el peor posible: termina sin error y sin escribir nada.
 *
 *   2. El delimitador es ';' y maatwebsite espera ','. La linea entera entra
 *      como una sola columna.
 *
 *   3. El archivo esta en CP1252, no UTF-8 (verificado: mb_check_encoding
 *      falla, y la enie llega como el byte 0xD1 suelto).
 *
 * Y encima WithChunkReading reabre y reparsea el archivo completo en cada
 * bloque: con chunkSize 2,000 sobre 169,752 filas son 85 pasadas. Por HTTP eso
 * monopoliza el servidor embebido de PHP, que es de un solo hilo, y deja la
 * aplicacion entera sin responder mientras corre.
 *
 * Este comando lee el archivo con fgets en dos pasadas y escribe por lotes.
 *
 * QUE ESPERA DEL ARCHIVO
 *
 * Cuatro columnas separadas por ';', sin encabezado, en el orden que deja el
 * ORDER BY de database/legacy/02_exportar_recetas.sql:
 *
 *     nCodPlato ; cNomPlato ; cNomProd ; nCantBas
 *     57 ; POLLO AL ROMERO ; Sal de Mesa MARINA ; 0.00500
 *
 * POR QUE EMPAREJA POR nCodPlato Y NO POR NOMBRE
 *
 * Aqui esta la diferencia de fondo con DishRecipesImport, que usa firstOrCreate
 * por nombre. Esa decision fue correcta cuando dishes.id era un autoincremento
 * sin relacion con el sistema viejo: emparejar por id habria colgado cada
 * receta de un plato ajeno (el bug de la infusion de menta con esparragos).
 *
 * Pero el catalogo vigente YA tiene los ids de Tiburon: dishes.id = nCodPlato.
 * Verificado contra el archivo el 30-09, plato por plato:
 *
 *     16,849 platos distintos en el CSV
 *     16,849 encontrados por id en dishes                (0 faltantes)
 *     16,849 con el nombre identico ignorando espacios   (0 diferencias)
 *
 * Con esa correspondencia, el id es la identidad mas fuerte que hay y el
 * nombre pasa a ser la verificacion, no la clave. Importa porque 385 de esos
 * nombres difieren en espacios ('P.FEstofado' en la base, 'P.F Estofado' en el
 * archivo): emparejar por nombre daria 385 platos duplicados, cada uno con
 * media receta. El comando compara los nombres ignorando espacios y aborta si
 * encuentra una discrepancia real, porque eso significaria que el archivo y el
 * catalogo no son del mismo origen y seguir mezclaria recetas entre platos.
 *
 * Los insumos SI van por nombre: ingredients no tiene los codigos de Tiburon,
 * y el export solo trae cNomProd. Son 902 nombres distintos.
 *
 * POR QUE DOS PASADAS
 *
 * dish_recipe_ingredients tiene FK a dish_recipes, asi que las cabeceras deben
 * existir antes de las lineas. La primera pasada solo recoge los ids de plato y
 * los nombres de insumo (16,849 enteros y 902 cadenas: nada de memoria), crea
 * de golpe los insumos que faltan y las 16,849 cabeceras, y carga el mapa
 * dish_id -> dish_recipes.id. La segunda escribe las lineas por lotes.
 *
 * Leer 11 MB dos veces es mas barato que las 85 pasadas que hacia el otro
 * camino, y la memoria queda plana.
 *
 * POR QUE ES REPETIBLE Y NO ACUMULA
 *
 * Esta es la diferencia con DishRecipesImport, que suma sobre lo que ya habia y
 * por eso necesita recetas:limpiar antes de cada corrida.
 *
 * El CSV viene con ORDER BY nCodPlato, asi que todas las lineas de un plato son
 * contiguas (verificado: 16,849 bloques contiguos para 16,849 platos). El
 * comando acumula el bloque completo de un plato en memoria y solo entonces lo
 * escribe, con upsert contra el unico (dish_recipe_id, ingredient_id). Como el
 * valor que manda es el total ya cerrado del plato, reescribir la fila es
 * correcto: correr el comando dos veces deja exactamente el mismo resultado.
 *
 * Dentro del bloque, un par plato-insumo repetido se SUMA. Son 12 casos en el
 * archivo y es el comportamiento legitimo: el detalle de Tiburon lista el mismo
 * producto en pasos distintos de la preparacion, y quedarse con el ultimo
 * perderia cantidad.
 *
 * USO
 *
 *     php artisan recetario:importar-csv "C:/Users/Jair/Desktop/Resultados.csv" --simulacro
 *     php artisan recetario:importar-csv "C:/Users/Jair/Desktop/Resultados.csv"
 */
class ImportarRecetarioCsv extends Command
{
    protected $signature = 'recetario:importar-csv
        {archivo : Ruta al CSV exportado de SQL Server}
        {--nivel=MASTER : Nombre del nivel donde cae el recetario}
        {--encoding=CP1252 : Codificacion de origen del archivo}
        {--lote=2000 : Filas por lote de escritura}
        {--simulacro : Lee, valida y reporta sin escribir nada}';

    protected $description = 'Importa el recetario de Tiburon desde el CSV plano (;, sin encabezado, CP1252) emparejando por nCodPlato';

    /** Nombre normalizado de insumo => ingredients.id */
    private array $insumos = [];

    /** dish_id => dish_recipes.id del nivel elegido */
    private array $recetas = [];

    public function handle(): int
    {
        $ruta = $this->argument('archivo');

        if (! is_readable($ruta)) {
            $this->error("No se puede leer el archivo: {$ruta}");

            return self::FAILURE;
        }

        $simulacro = (bool) $this->option('simulacro');

        if ($simulacro) {
            $this->warn('SIMULACRO: no se escribe nada.');
        }

        // --- Pasada 1: inventario y validacion -----------------------------

        $this->info('Pasada 1/2: leyendo el archivo y validando contra el catalogo...');

        $inventario = $this->inventariar($ruta);

        if ($inventario === null) {
            return self::FAILURE;
        }

        [$idsPlato, $nombresInsumo, $lineas, $descartadas] = $inventario;

        $this->table(['Concepto', 'Valor'], [
            ['Lineas leidas', number_format($lineas)],
            ['Lineas descartadas', number_format($descartadas)],
            ['Platos distintos', number_format(count($idsPlato))],
            ['Insumos distintos', number_format(count($nombresInsumo))],
        ]);

        if ($simulacro) {
            $this->validarSimulacro($nombresInsumo, $idsPlato);

            return self::SUCCESS;
        }

        // --- Preparacion: insumos y cabeceras ------------------------------

        $levelId = $this->levelId();

        $insumosNuevos = $this->prepararInsumos($nombresInsumo);
        $this->line("Insumos dados de alta: {$insumosNuevos}");

        $recetasNuevas = $this->prepararRecetas($idsPlato, $levelId);
        $this->line("Cabeceras de receta creadas: {$recetasNuevas}");

        // --- Pasada 2: escritura -------------------------------------------

        $this->info('Pasada 2/2: escribiendo las lineas de receta...');

        [$escritas, $totales] = $this->escribir($ruta);

        $this->actualizarTotales($totales);

        $this->newLine();
        $this->info('Importacion terminada.');
        $this->table(['Concepto', 'Valor'], [
            ['Lineas de receta escritas', number_format($escritas)],
            ['Recetas con total actualizado', number_format(count($totales))],
            ['Insumos nuevos', number_format($insumosNuevos)],
            ['Cabeceras nuevas', number_format($recetasNuevas)],
        ]);

        return self::SUCCESS;
    }

    /**
     * Primera pasada. Devuelve [idsPlato, nombresInsumo, lineas, descartadas],
     * o null si el archivo no cuadra con el catalogo.
     *
     * La validacion de nombres vive aqui, antes de escribir una sola fila: si
     * el archivo no corresponde al catalogo hay que saberlo ahora y no a mitad
     * de la carga, con medio recetario colgado del plato equivocado.
     */
    private function inventariar(string $ruta): ?array
    {
        $catalogo = DB::table('dishes')->pluck('name', 'id');

        $idsPlato = [];
        $nombresInsumo = [];
        $lineas = 0;
        $descartadas = 0;
        $sinPlato = [];
        $discrepancias = [];

        $h = fopen($ruta, 'rb');
        $barra = $this->output->createProgressBar();
        $barra->start();

        while (($linea = fgets($h)) !== false) {
            $fila = $this->parsear($linea);
            if ($fila === null) {
                $descartadas++;
                continue;
            }

            [$codPlato, $nombrePlato, $nombreInsumo] = $fila;
            $lineas++;

            if (! isset($catalogo[$codPlato])) {
                $sinPlato[$codPlato] = $nombrePlato;
            } elseif (! isset($idsPlato[$codPlato]) && $nombrePlato !== '') {
                // El nombre se compara una sola vez por plato, no por linea, y
                // solo si el archivo trae uno: ver el comentario de parsear()
                // sobre los 4 platos sin nombre en el origen.
                if ($this->sinEspacios($catalogo[$codPlato]) !== $this->sinEspacios($nombrePlato)) {
                    $discrepancias[$codPlato] = [$catalogo[$codPlato], $nombrePlato];
                }
            }

            $idsPlato[$codPlato] = true;
            $nombresInsumo[$this->normalizar($nombreInsumo)] = $nombreInsumo;

            if ($lineas % 20000 === 0) {
                $barra->advance(20000);
            }
        }

        fclose($h);
        $barra->finish();
        $this->newLine(2);

        if ($sinPlato !== []) {
            $this->error(sprintf(
                '%s codigos de plato del archivo no existen en dishes. No se importa nada.',
                number_format(count($sinPlato))
            ));
            $this->avisarMuestra($sinPlato, fn ($nombre, $cod) => "  nCodPlato {$cod}: {$nombre}");
            $this->line('Restaura el catalogo de platos antes de importar el recetario.');

            return null;
        }

        if ($discrepancias !== []) {
            $this->error(sprintf(
                '%s platos tienen un nombre que no coincide con el catalogo. No se importa nada.',
                number_format(count($discrepancias))
            ));
            $this->avisarMuestra(
                $discrepancias,
                fn ($par, $cod) => "  nCodPlato {$cod}:\n    catalogo [{$par[0]}]\n    archivo  [{$par[1]}]"
            );
            $this->line('El archivo y el catalogo no parecen del mismo origen: importar mezclaria recetas entre platos.');

            return null;
        }

        return [$idsPlato, $nombresInsumo, $lineas, $descartadas];
    }

    /**
     * Segunda pasada. Acumula el bloque contiguo de cada plato y lo escribe
     * cerrado, lo que hace la corrida repetible (ver el docblock de la clase).
     *
     * Devuelve [lineas escritas, mapa recipe_id => total acumulado].
     */
    private function escribir(string $ruta): array
    {
        $lote = max(1, (int) $this->option('lote'));
        $ahora = now();

        $escritas = 0;
        $totales = [];
        $buffer = [];

        $platoActual = null;
        $acumulado = [];

        $h = fopen($ruta, 'rb');
        $barra = $this->output->createProgressBar();
        $barra->start();
        $leidas = 0;

        $cerrarPlato = function () use (&$platoActual, &$acumulado, &$buffer, &$totales, $ahora) {
            if ($platoActual === null || $acumulado === []) {
                return;
            }

            $recipeId = $this->recetas[$platoActual];
            $totales[$recipeId] = array_sum($acumulado);

            foreach ($acumulado as $ingredientId => $cantidad) {
                $buffer[] = [
                    'dish_recipe_id' => $recipeId,
                    'ingredient_id' => $ingredientId,
                    'gross_weight' => $cantidad,
                    // Sin dato de merma en el origen no se puede descontar
                    // nada; dejar el neto en 0 haria ver la receta como si no
                    // rindiera.
                    'net_weight' => $cantidad,
                    'solid_waste' => 0,
                    'liquid_waste' => 0,
                    'calories' => 0,
                    'cost' => 0,
                    'unit_price' => 0,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }

            $acumulado = [];
        };

        while (($linea = fgets($h)) !== false) {
            $fila = $this->parsear($linea);
            if ($fila === null) {
                continue;
            }

            [$codPlato, , $nombreInsumo, $cantidad] = $fila;

            if ($codPlato !== $platoActual) {
                $cerrarPlato();
                $platoActual = $codPlato;
            }

            $ingredientId = $this->insumos[$this->normalizar($nombreInsumo)];

            // Par repetido dentro del mismo plato: se suma a proposito.
            $acumulado[$ingredientId] = ($acumulado[$ingredientId] ?? 0) + $cantidad;

            if (count($buffer) >= $lote) {
                $escritas += $this->volcar($buffer);
                $buffer = [];
            }

            if (++$leidas % 20000 === 0) {
                $barra->advance(20000);
            }
        }

        $cerrarPlato();
        fclose($h);

        if ($buffer !== []) {
            $escritas += $this->volcar($buffer);
        }

        $barra->finish();
        $this->newLine(2);

        return [$escritas, $totales];
    }

    /**
     * Escribe un lote con upsert contra dri_recipe_ingredient_unique. Las
     * cantidades que llegan son el total cerrado del plato, asi que pisar la
     * fila existente es lo correcto.
     */
    private function volcar(array $filas): int
    {
        DB::table('dish_recipe_ingredients')->upsert(
            $filas,
            ['dish_recipe_id', 'ingredient_id'],
            ['gross_weight', 'net_weight', 'updated_at']
        );

        return count($filas);
    }

    /**
     * Totales por receta, en lotes con un solo UPDATE ... CASE por lote. Un
     * update por receta serian 16,849 viajes a la base.
     */
    private function actualizarTotales(array $totales): void
    {
        if ($totales === []) {
            return;
        }

        $this->info('Actualizando totales de las cabeceras...');

        foreach (array_chunk($totales, 1000, true) as $bloque) {
            $casos = '';
            $ids = [];
            $bindings = [];

            foreach ($bloque as $recipeId => $total) {
                $casos .= ' WHEN ? THEN ?';
                $bindings[] = $recipeId;
                $bindings[] = $total;
                $ids[] = $recipeId;
            }

            $marcas = implode(',', array_fill(0, count($ids), '?'));

            DB::update(
                "UPDATE dish_recipes
                    SET total_gross_weight = CASE id{$casos} END,
                        total_net_weight   = CASE id{$casos} END,
                        updated_at         = ?
                  WHERE id IN ({$marcas})",
                array_merge($bindings, $bindings, [now()], $ids)
            );
        }
    }

    /**
     * Da de alta los insumos que falten y deja $this->insumos completo.
     *
     * El mapa se arma normalizando el nombre porque el emparejamiento tiene
     * que ser insensible a mayusculas y a espacios de mas, igual que lo es la
     * collation utf8mb4_unicode_ci de la columna. ingredients tiene nombres
     * repetidos (1,993 filas para 1,963 nombres al 30-09); ante un duplicado
     * gana el id mas bajo, que es una regla arbitraria pero estable: la misma
     * corrida dos veces elige el mismo.
     */
    private function prepararInsumos(array $nombresInsumo): int
    {
        $this->cargarInsumos();

        $faltantes = [];
        $ahora = now();

        foreach ($nombresInsumo as $clave => $nombre) {
            if (! isset($this->insumos[$clave])) {
                $faltantes[] = [
                    'name' => $nombre,
                    'description' => 'Importado del recetario de Tiburon',
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }

        foreach (array_chunk($faltantes, 500) as $bloque) {
            DB::table('ingredients')->insert($bloque);
        }

        if ($faltantes !== []) {
            $this->cargarInsumos();
        }

        return count($faltantes);
    }

    private function cargarInsumos(): void
    {
        $this->insumos = [];

        foreach (DB::table('ingredients')->orderBy('id')->get(['id', 'name']) as $fila) {
            $clave = $this->normalizar($fila->name);
            if (! isset($this->insumos[$clave])) {
                $this->insumos[$clave] = (int) $fila->id;
            }
        }
    }

    /**
     * Crea las cabeceras que falten para (dish_id, nivel) y deja
     * $this->recetas completo.
     */
    private function prepararRecetas(array $idsPlato, int $levelId): int
    {
        $existentes = DB::table('dish_recipes')
            ->where('level_id', $levelId)
            ->pluck('id', 'dish_id');

        $nuevas = [];
        $ahora = now();

        foreach (array_keys($idsPlato) as $dishId) {
            if (isset($existentes[$dishId])) {
                $this->recetas[$dishId] = (int) $existentes[$dishId];
                continue;
            }

            $nuevas[] = [
                'dish_id' => $dishId,
                'level_id' => $levelId,
                'total_gross_weight' => 0,
                'total_waste_weight' => 0,
                'total_calories' => 0,
                'total_cost' => 0,
                'total_net_weight' => 0,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        foreach (array_chunk($nuevas, 500) as $bloque) {
            DB::table('dish_recipes')->insert($bloque);
        }

        if ($nuevas !== []) {
            $todas = DB::table('dish_recipes')->where('level_id', $levelId)->pluck('id', 'dish_id');
            foreach ($todas as $dishId => $recipeId) {
                $this->recetas[$dishId] = (int) $recipeId;
            }
        }

        return count($nuevas);
    }

    /**
     * levels.id del nivel pedido. La busqueda ignora mayusculas porque la
     * tabla guarda 'MASTER' y la opcion se escribe como sea.
     */
    private function levelId(): int
    {
        $nivel = (string) $this->option('nivel');

        $fila = DB::table('levels')->whereRaw('LOWER(name) = ?', [mb_strtolower($nivel)])->first();

        if ($fila === null) {
            $this->warn("El nivel '{$nivel}' no existe: se crea.");

            return (int) DB::table('levels')->insertGetId(['name' => mb_strtoupper($nivel)]);
        }

        return (int) $fila->id;
    }

    /**
     * Una linea del CSV -> [codPlato, nombrePlato, nombreInsumo, cantidad],
     * o null si no sirve.
     *
     * Se parte con explode y no con fgetcsv a proposito: 1,284 lineas del
     * archivo traen una comilla doble suelta dentro del texto y fgetcsv la
     * interpretaria como apertura de campo entrecomillado, tragandose las
     * lineas siguientes hasta la proxima comilla. Verificado que ninguna de
     * las 169,752 lineas tiene un ';' dentro de un campo (todas parten en
     * exactamente 4), asi que explode es seguro aqui y fgetcsv no lo es.
     */
    private function parsear(string $linea): ?array
    {
        $linea = rtrim($linea, "\r\n");
        if ($linea === '') {
            return null;
        }

        $partes = explode(';', $linea, 4);
        if (count($partes) < 4) {
            return null;
        }

        $codPlato = (int) trim($partes[0]);
        if ($codPlato <= 0) {
            return null;
        }

        $nombrePlato = trim($this->aUtf8($partes[1]));
        $nombreInsumo = trim($this->aUtf8($partes[2]));

        // El nombre del insumo SI es obligatorio: es la clave con la que se
        // resuelve ingredient_id y sin el no hay nada que emparejar.
        //
        // El del plato NO, porque el plato se identifica por nCodPlato. Hay 4
        // platos sin nombre en el origen (3271, 8772, 8781 y 14087), y lo
        // tienen vacio tanto en el CSV como en dishes, donde CHAR_LENGTH(name)
        // da 0. Exigirlo aqui tiraba sus 15 lineas de receta, entre ellas las
        // 12 completas del plato 14087. El nombre es la verificacion, no la
        // clave: si viene vacio no hay nada que verificar, pero la linea sirve.
        if ($nombreInsumo === '') {
            return null;
        }

        return [$codPlato, $nombrePlato, $nombreInsumo, $this->aNumero($partes[3])];
    }

    private function aUtf8(string $s): string
    {
        $origen = (string) $this->option('encoding');

        if (strtoupper($origen) === 'UTF-8') {
            return $s;
        }

        return mb_convert_encoding($s, 'UTF-8', $origen);
    }

    /**
     * Cantidad de una linea.
     *
     * EL ARCHIVO TRAE CUATRO FORMATOS NUMERICOS DISTINTOS
     *
     * Medido sobre el export del 30-09, agrupando por forma:
     *
     *     9.99999       168,116 lineas    0.36200
     *     999.999         1,433 lineas    600.951
     *     9.999.999         168 lineas    2.200.000
     *     99.999.999         35 lineas    30.000.000
     *
     * Las tres ultimas llevan separador de miles de punto. Que el punto final
     * es decimal y no otro separador de miles se comprueba en las colas: no
     * son siempre '000' sino valores reales ('.951', '.880', '.714'), o sea
     * tres decimales. La regla que cubre los cuatro casos es entonces: el
     * ULTIMO punto es el separador decimal y los anteriores son de miles.
     *
     * Con un solo punto no hay nada que decidir y se toma como decimal, que es
     * lo correcto tanto para 0.36200 como para 600.951.
     *
     * OJO CON LA ESCALA DE LAS 1,636 LINEAS QUE NO SON DEL PRIMER FORMATO
     *
     * Esto parsea lo que el archivo dice, literalmente, y hasta ahi llega su
     * responsabilidad. Pero esas cantidades no son comparables con las del
     * primer formato: el mismo insumo 'Agua Natural de Cano' aparece con
     * 0.36200 en SOPA DE ESPINACA y con 30.000.000 en 'Refresco de Muna'.
     * Ningun factor unico reconcilia las dos escalas.
     *
     * La explicacion esta en una columna que el CSV no trae: nTipUndBas, el
     * tipo de unidad base. El SELECT de database/legacy/02_exportar_recetas.sql
     * exporta nueve columnas y este archivo solo tiene cuatro
     * (nCodPlato, cNomPlato, cNomProd, nCantBas), asi que la unidad de cada
     * linea se perdio en el export y con ella la unica forma de normalizar.
     *
     * Para el 99% del recetario (las 168,116 lineas del primer formato, todas
     * a escala de kilos por racion) eso no estorba. Para el 1% restante hay
     * que reexportar con las nueve columnas antes de darle valor al numero.
     */
    private function aNumero(string $v): float
    {
        $v = trim($v);
        if ($v === '') {
            return 0.0;
        }

        $s = preg_replace('/[^0-9,.\-]/', '', $v);

        // Coma como separador decimal (un Excel con configuracion regional
        // distinta): se normaliza a punto. Con mas de una coma es separador de
        // miles y se quita.
        if (substr_count($s, ',') === 1 && substr_count($s, '.') === 0) {
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        // Separadores de miles de punto: se quitan todos menos el ultimo, que
        // es el decimal.
        if (substr_count($s, '.') > 1) {
            $corte = strrpos($s, '.');
            $s = str_replace('.', '', substr($s, 0, $corte)) . substr($s, $corte);
        }

        return is_numeric($s) ? (float) $s : 0.0;
    }

    /** Clave de emparejamiento de insumos: sin mayusculas ni espacios de mas. */
    private function normalizar(string $s): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)), 'UTF-8');
    }

    /** Comparacion de nombres de plato: ver el docblock de la clase. */
    private function sinEspacios(?string $s): string
    {
        return mb_strtolower(preg_replace('/\s+/u', '', (string) $s), 'UTF-8');
    }

    private function avisarMuestra(array $items, callable $formato, int $max = 5): void
    {
        $i = 0;
        foreach ($items as $clave => $valor) {
            if ($i++ >= $max) {
                $this->line(sprintf('  ... y %s mas', number_format(count($items) - $max)));
                break;
            }
            $this->line($formato($valor, $clave));
        }
    }

    /** Reporte extra del simulacro: que haria sin hacerlo. */
    private function validarSimulacro(array $nombresInsumo, array $idsPlato): void
    {
        $this->cargarInsumos();

        $nuevos = 0;
        foreach (array_keys($nombresInsumo) as $clave) {
            if (! isset($this->insumos[$clave])) {
                $nuevos++;
            }
        }

        $levelId = DB::table('levels')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $this->option('nivel'))])
            ->value('id');

        $cabecerasExistentes = $levelId === null ? 0 : DB::table('dish_recipes')
            ->where('level_id', $levelId)
            ->whereIn('dish_id', array_keys($idsPlato))
            ->count();

        $this->info('Validacion correcta: todos los platos del archivo existen y los nombres coinciden.');
        $this->table(['Se haria', 'Valor'], [
            ['Insumos a dar de alta', number_format($nuevos)],
            ['Cabeceras de receta a crear', number_format(count($idsPlato) - $cabecerasExistentes)],
            ['Cabeceras que ya existen', number_format($cabecerasExistentes)],
        ]);
    }
}
