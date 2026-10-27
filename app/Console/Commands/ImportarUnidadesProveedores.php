<?php

namespace App\Console\Commands;

use App\Models\City;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Carga la unidad de medida de compra (SACO, BOLSA, KG, ...) de cada asignación
 * insumo-proveedor de una ciudad, a partir de la "Lista de proveedores" en Excel.
 *
 * FORMATO DEL EXCEL
 *
 * Una sola hoja con bloques por proveedor: una fila con el nombre del proveedor en
 * la columna B (y C vacía), después filas con B = unidad, C = descripción del
 * insumo, D = costo. Hay sub-bloques ("X 1 KG", "X 5 KG") que no son proveedores:
 * sus filas siguen perteneciendo al último proveedor reconocido.
 *
 * DÓNDE SE ESCRIBE
 *
 *  - ingredient_city_providers.measurement_unit_id: es lo que muestra la columna
 *    UM de Proveedores. El catálogo measurement_units se completa con las unidades
 *    que falten, unificando sinónimos (KILOS → KG, UND → UNIDAD, PQTE → PQT, ...).
 *  - ingredients.unit_medida: la usa Órdenes de Compra.
 *
 * El cruce es por proveedor + descripción, y si no, solo por descripción cuando el
 * Excel le da una única unidad. Un mismo producto con unidades distintas en dos
 * proveedores (Romero: GR en uno, KG en otro) se resuelve así por proveedor.
 *
 * QUÉ NO PISA
 *
 * Sin --pisar solo llena valores vacíos.
 *
 * USO
 *
 *     php artisan proveedores:unidades "D:/ruta/Lista de proveedores Lima.xlsx"
 *     php artisan proveedores:unidades "D:/ruta/Lista de proveedores Lima.xlsx" --ejecutar
 */
class ImportarUnidadesProveedores extends Command
{
    protected $signature = 'proveedores:unidades
                            {ruta : Excel con la lista de proveedores (B = unidad, C = descripción)}
                            {--ciudad=LIMA : Ciudad a la que pertenecen los precios del Excel}
                            {--ejecutar : Aplica los cambios. Sin este flag solo informa qué haría}
                            {--pisar : Reemplaza también las unidades ya cargadas}';

    protected $description = 'Carga la unidad de medida de compra de las asignaciones insumo-proveedor desde el Excel de proveedores';

    /** Sinónimos del Excel => unidad canónica del catálogo. */
    private const SINONIMOS = [
        'KILOS' => 'KG', 'KILO' => 'KG', 'KGS' => 'KG',
        'UNID' => 'UNIDAD', 'UND' => 'UNIDAD', 'UN' => 'UNIDAD',
        'PQTE' => 'PQT', 'PAQUETE' => 'PQT',
        'BOT' => 'BOTELLA', 'BT' => 'BOTELLA', 'BTX1LT' => 'BOTELLA',
        'FCO' => 'FRASCO',
        'GL' => 'GLN', 'GALON' => 'GLN',
        'BLS' => 'BOLSA',
        'RESMAS' => 'RESMA',
    ];

    public function handle(): int
    {
        $ruta = (string) $this->argument('ruta');
        if (! is_file($ruta)) {
            $this->error("No existe el archivo: {$ruta}");

            return self::FAILURE;
        }

        $ciudad = City::whereRaw('UPPER(name) = ?', [mb_strtoupper((string) $this->option('ciudad'))])->first();
        if (! $ciudad) {
            $this->error('No existe la ciudad ' . $this->option('ciudad'));

            return self::FAILURE;
        }

        $proveedores = DB::table('providers')->get(['id', 'name'])
            ->mapWithKeys(fn ($p) => [self::normalizar($p->name) => (int) $p->id])
            ->sortByDesc(fn ($id, $nombre) => mb_strlen($nombre));

        [$porProveedor, $porNombre] = $this->leerExcel($ruta, $proveedores->all());

        $asignaciones = DB::table('ingredient_city_providers as icp')
            ->join('ingredients as i', 'i.id', '=', 'icp.ingredient_id')
            ->where('icp.city_id', $ciudad->id)
            ->get(['icp.id', 'icp.provider_id', 'icp.measurement_unit_id', 'i.id as ingredient_id', 'i.name', 'i.unit_medida']);

        $pisar = (bool) $this->option('pisar');
        $catalogo = DB::table('measurement_units')->get(['id', 'name', 'abbreviation']);
        $idsUnidad = [];
        foreach ($catalogo as $u) {
            foreach ([$u->abbreviation, $u->name] as $clave) {
                $clave = self::unidad($clave);
                if ($clave !== '') {
                    $idsUnidad[$clave] ??= (int) $u->id;
                }
            }
        }

        $planAsignacion = [];   // icp.id => unidad
        $planInsumo = [];       // ingredient_id => unidad
        $sinCruce = [];
        $ambiguos = [];

        foreach ($asignaciones as $a) {
            $nombre = self::normalizar($a->name);
            $unidad = $porProveedor[$a->provider_id][$nombre] ?? null;

            if ($unidad === null) {
                $opciones = $porNombre[$nombre] ?? [];
                if (count($opciones) > 1) {
                    $ambiguos[] = [$a->name, implode(' / ', array_keys($opciones))];

                    continue;
                }
                $unidad = array_key_first($opciones);
            }

            if ($unidad === null) {
                $sinCruce[] = [$a->name];

                continue;
            }

            if ($pisar || $a->measurement_unit_id === null) {
                $planAsignacion[$a->id] = $unidad;
            }
            if ($pisar || $a->unit_medida === null || $a->unit_medida === '') {
                $planInsumo[$a->ingredient_id] ??= $unidad;
            }
        }

        $nuevas = array_values(array_diff(array_unique(array_merge($planAsignacion, $planInsumo)), array_keys($idsUnidad)));

        $this->table(['Ciudad ' . $ciudad->name, 'Filas'], [
            ['Asignaciones de la ciudad', $asignaciones->count()],
            ['UM de asignación a cargar', count($planAsignacion)],
            ['unit_medida de insumo a cargar', count($planInsumo)],
            ['Unidades nuevas en el catálogo', count($nuevas) . ($nuevas ? ' (' . implode(', ', $nuevas) . ')' : '')],
            ['Sin cruce con el Excel', count($sinCruce)],
            ['Unidad ambigua (no se tocan)', count($ambiguos)],
        ]);

        if ($ambiguos) {
            $this->warn('Insumos con más de una unidad en el Excel y sin proveedor que la desambigüe:');
            $this->table(['Insumo', 'Unidades'], $ambiguos);
        }
        if ($sinCruce && $this->output->isVerbose()) {
            $this->table(['Sin cruce'], $sinCruce);
        }

        if (! $this->option('ejecutar')) {
            $this->warn('SIMULACRO: no se escribió nada. Agrega --ejecutar para aplicarlo.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($nuevas, &$idsUnidad, $planAsignacion, $planInsumo) {
            $ahora = now();
            foreach ($nuevas as $unidad) {
                $idsUnidad[$unidad] = (int) DB::table('measurement_units')->insertGetId([
                    'name' => $unidad, 'abbreviation' => $unidad, 'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
            foreach ($planAsignacion as $id => $unidad) {
                DB::table('ingredient_city_providers')->where('id', $id)
                    ->update(['measurement_unit_id' => $idsUnidad[$unidad], 'updated_at' => $ahora]);
            }
            foreach ($planInsumo as $id => $unidad) {
                DB::table('ingredients')->where('id', $id)->update(['unit_medida' => $unidad, 'updated_at' => $ahora]);
            }
        });

        $this->info('Listo: ' . count($planAsignacion) . ' asignaciones y ' . count($planInsumo) . ' insumos actualizados.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $proveedores  nombre normalizado => id, del más largo al más corto
     * @return array{0: array<int, array<string, string>>, 1: array<string, array<string, true>>}
     */
    private function leerExcel(string $ruta, array $proveedores): array
    {
        $hoja = IOFactory::load($ruta)->getSheet(0);
        $porProveedor = [];
        $porNombre = [];
        $actual = null;
        $presentacion = '';

        foreach ($hoja->rangeToArray('A1:E' . $hoja->getHighestRow(), null, true, false) as $fila) {
            $b = trim((string) $fila[1]);
            $c = self::normalizar($fila[2]);

            if ($b !== '' && $c === '') {
                // Cabecera de bloque: "MARPIER (FRUTAS)" pertenece a MARPIER. Los
                // sub-bloques "X 1 KG" / "X 5 KG" conservan el proveedor y listan los
                // insumos sin la presentación, que en la base sí va en el nombre.
                $cabecera = self::normalizar($b);
                if (preg_match('/^X \d+(\.\d+)? KG$/u', $cabecera)) {
                    $presentacion = ' ' . $cabecera;

                    continue;
                }
                $presentacion = '';
                foreach ($proveedores as $nombre => $id) {
                    if ($nombre !== '' && str_starts_with($cabecera, $nombre)) {
                        $actual = $id;
                        break;
                    }
                }

                continue;
            }

            $unidad = self::unidad($b);
            if ($c === '' || $unidad === '' || $unidad === 'UNID. MEDIDA') {
                continue;
            }

            $c .= $presentacion;
            if ($actual !== null) {
                $porProveedor[$actual][$c] ??= $unidad;
            }
            $porNombre[$c][$unidad] = true;
        }

        return [$porProveedor, $porNombre];
    }

    private static function normalizar(mixed $texto): string
    {
        $texto = mb_strtoupper(trim((string) $texto));
        $texto = strtr($texto, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U']);

        return preg_replace('/\s+/u', ' ', $texto);
    }

    private static function unidad(mixed $texto): string
    {
        $u = rtrim(self::normalizar($texto), '.');

        return self::SINONIMOS[$u] ?? $u;
    }
}
