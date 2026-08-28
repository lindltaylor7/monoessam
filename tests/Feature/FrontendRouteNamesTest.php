<?php

use Illuminate\Support\Facades\Route;

/**
 * Ziggy resuelve route('nombre') en el navegador: un nombre inexistente no falla al
 * compilar, revienta en runtime cuando el usuario pulsa el boton. Este test recorre
 * resources/js y comprueba que todo nombre pasado a route() esta registrado.
 */
test('every route() name used in the frontend exists in the router', function () {
    $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js';

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
    $used = [];

    foreach ($files as $file) {
        if (! $file->isFile()) {
            continue;
        }
        if (! in_array($file->getExtension(), ['vue', 'ts', 'js'], true)) {
            continue;
        }

        $src = file_get_contents($file->getPathname());

        if (preg_match_all("/\broute\(\s*['\"]([A-Za-z0-9_.\-]+)['\"]/", $src, $m)) {
            $relative = str_replace($base . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

            foreach ($m[1] as $name) {
                $used[$name][] = $relative;
            }
        }
    }

    $this->assertNotEmpty($used, 'No se encontro ninguna llamada a route(); revisa el escaneo.');

    $missing = [];
    foreach ($used as $name => $where) {
        if (! Route::has($name)) {
            $missing[] = $name . '  <- ' . implode(', ', array_unique($where));
        }
    }

    $this->assertSame(
        [],
        $missing,
        "Nombres de ruta usados en el frontend que no existen en el router:\n" . implode("\n", $missing)
    );
});
