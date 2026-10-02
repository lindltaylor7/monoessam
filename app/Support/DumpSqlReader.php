<?php

namespace App\Support;

use Generator;

/**
 * Lee filas de un dump SQL de phpMyAdmin sin cargarlo en memoria.
 *
 * POR QUE EXISTE
 *
 * Las importaciones que reenganchan datos de un respaldo contra la base viva
 * (recetas:restaurar, dosificaciones:importar, precios:importar) necesitan todas
 * lo mismo: recorrer un .sql de 38 MB y sacar las filas de unas pocas tablas.
 * El parser estaba copiado en cada comando; esta clase es ese codigo una sola
 * vez. `RestaurarRecetasDesdeDump` todavia tiene su copia propia a proposito:
 * devuelve campos por posicion y no por nombre, y es codigo con el que ya se
 * recupero data real, asi que no se toca sin motivo.
 *
 * QUE RESUELVE QUE UN explode() NO
 *
 *   1. Un INSERT de phpMyAdmin pone cada fila en su propia linea, pero un campo
 *      de texto con un salto de linea adentro rompe eso. Se acumula hasta que la
 *      tupla cierra de verdad: parentesis balanceado FUERA de comillas.
 *   2. Las comas dentro de un string no separan campos ('Aji, Rojo' es un campo).
 *   3. NULL es null, no la cadena "NULL".
 *   4. Los nombres de columna se leen de la cabecera del propio INSERT, no se
 *      asumen. Eso es lo que permite copiar solo las columnas que la tabla viva
 *      tambien tiene, y sobrevivir a una migracion aplicada en un solo lado.
 *
 * USO
 *
 *     $lector = new DumpSqlReader($ruta);
 *
 *     // varias tablas en UNA sola pasada por el archivo
 *     [$provs, $unidades] = $lector->leerTodas(['providers', 'measurement_units']);
 *
 *     // o en streaming, cuando la tabla no cabe comoda en memoria
 *     foreach ($lector->filas('ingredients') as $fila) { ... }
 */
class DumpSqlReader
{
    public function __construct(private readonly string $ruta) {}

    public function existe(): bool
    {
        return is_file($this->ruta);
    }

    public function tamano(): int
    {
        return $this->existe() ? (int) filesize($this->ruta) : 0;
    }

    /**
     * Carga varias tablas de una sola pasada por el archivo.
     *
     * Una pasada por tabla serian 38 MB de lectura por cada una; las tablas que
     * se traen asi son catalogos de unos miles de filas, asi que entran de sobra
     * en memoria y conviene pagar el I/O una sola vez.
     *
     * @param  array<int, string>  $tablas
     * @return array<string, array<int, array<string, string|null>>> en el mismo orden pedido
     */
    public function leerTodas(array $tablas): array
    {
        $out = array_fill_keys($tablas, []);

        foreach ($this->recorrer($tablas) as [$tabla, $fila]) {
            $out[$tabla][] = $fila;
        }

        return $out;
    }

    /**
     * Devuelve las filas de UNA tabla, una por una.
     *
     * @return Generator<int, array<string, string|null>>
     */
    public function filas(string $tabla): Generator
    {
        foreach ($this->recorrer([$tabla]) as [, $fila]) {
            yield $fila;
        }
    }

    /**
     * Motor comun: recorre el archivo una vez y emite [tabla, fila] para cada
     * fila de las tablas pedidas.
     *
     * @param  array<int, string>  $tablas
     * @return Generator<int, array{0: string, 1: array<string, string|null>}>
     */
    private function recorrer(array $tablas): Generator
    {
        $fh = @fopen($this->ruta, 'r');
        if ($fh === false) {
            return;
        }

        $actual   = null;   // tabla del INSERT que se esta leyendo, o null si no interesa
        $columnas = [];
        $buffer   = '';

        while (($linea = fgets($fh)) !== false) {
            if ($buffer === '') {
                if (str_starts_with($linea, 'INSERT INTO `')) {
                    preg_match('/^INSERT INTO `([^`]+)` \(([^)]*)\) VALUES/', $linea, $m);
                    $tabla    = $m[1] ?? '';
                    $actual   = in_array($tabla, $tablas, true) ? $tabla : null;
                    $columnas = $actual === null ? [] : $this->columnas($m[2] ?? '');

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

            $campos = $this->campos($tupla);
            if (count($campos) === count($columnas)) {
                yield [$actual, array_combine($columnas, $campos)];
            }
        }

        fclose($fh);
    }

    /**
     * Parte la lista de columnas de un INSERT ("`id`, `name`, ...") en nombres.
     *
     * @return array<int, string>
     */
    private function columnas(string $lista): array
    {
        preg_match_all('/`([^`]+)`/', $lista, $m);

        return $m[1];
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

    /**
     * Mayusculas, sin acentos (ni los rotos) y sin nada que no sea A-Z0-9, para
     * comparar nombres entre el dump y la base viva.
     *
     * El legado maltrato los nombres de cuatro formas: padding de CHAR
     * ('QUESO FRESCO        '), mayusculas inconsistentes, puntuacion suelta
     * ('Queso Fresco EXTRA- "El Valle"') y tildes rotas ('Limon' quedo con bytes
     * C3B2, 'o grave', en lugar de C3B3, 'o aguda').
     *
     * Se usa un mapa explicito y no iconv //TRANSLIT porque la libiconv de
     * Windows produce "LIM`ON" para la o grave y "LIM'ON" para la o aguda: deja
     * distintos justo los dos casos que hay que unificar. Aqui las dos caen en
     * "LIMON".
     */
    public static function normalizar(?string $s): string
    {
        $s = mb_strtoupper(trim((string) $s), 'UTF-8');

        $s = strtr($s, [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ñ' => 'N', 'Ç' => 'C', 'Ý' => 'Y',
        ]);

        $s = preg_replace('/[^A-Z0-9]+/', ' ', $s) ?? '';

        return trim(preg_replace('/\s+/', ' ', $s) ?? '');
    }

    public static function humano(int $bytes): string
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
