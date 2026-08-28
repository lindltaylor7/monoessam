<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

class CheckRoutePermission
{
    /**
     * Segmentos exentos de cualquier comprobación.
     */
    private const EXEMPT_SEGMENTS = ['dashboard', 'settings', 'profile'];

    /**
     * Segmentos que NO tienen fila propia en `permissions` y que, sin este mapa, quedarían
     * abiertos a cualquier usuario autenticado: el middleware deja pasar todo segmento sin
     * permiso registrado. Basta con tener uno de los permisos listados.
     *
     * La lista de cada segmento sale de auditar qué pantallas lo consumen. Son maestros y
     * endpoints de búsqueda que se llaman desde varios módulos, así que restringirlos a un
     * único permiso rompería flujos reales (p. ej. `dishes` lo usan Alimentos, Planificación
     * y Ciclos).
     *
     * Aplica a lectura y escritura. Solo restringe: antes no había comprobación alguna.
     */
    private const SEGMENT_PERMISSION_ALIASES = [
        'purchase-orders'      => ['orders'],
        'atwater'              => ['nutritional', 'food', 'cycles'],
        'cafes'                => ['management', 'headcount', 'sales'],
        'mines'                => ['management'],
        'units'                => ['management'],
        'dishes'               => ['food', 'planning', 'cycles'],
        'dish-recipes'         => ['food', 'cycles'],
        'dish-categories'      => ['food', 'planning'],
        'delete-dish-category' => ['food'],
        'levels'               => ['food'],
        'equipment-dispatches' => ['equipments', 'logistics', 'inventory', 'store'],
        'generalreport'        => ['reportsales'],
        'satisfaction'         => ['management'],
        'areas'                => ['headcount'],
        'guards'               => ['headcount'],
        'periods'              => ['headcount'],
        'headquarters'         => ['businesses'],
    ];

    /**
     * Segmentos que SÍ tienen permiso propio pero a los que escriben pantallas de otros
     * módulos. Solo se consulta en métodos de escritura.
     *
     * Se mantiene aparte del mapa anterior a propósito: si se aplicara también a GET, daría
     * acceso de lectura al módulo entero a quien hoy no lo tiene (un usuario de `pos` podría
     * abrir el padrón de Comensales). Separándolos, el cambio solo puede restringir: la
     * lectura sigue exigiendo el permiso propio del segmento, y la escritura pasa de no
     * comprobar nada a exigir al menos uno de estos permisos.
     */
    private const WRITE_PERMISSION_ALIASES = [
        'dinners'     => ['dinners', 'pos', 'sales'],
        'dealerships' => ['dealerships', 'businesses'],
        'equipments'  => ['equipments', 'inventory', 'store'],
        'inventory'   => ['inventory', 'clothes'],
        'ingredients' => ['ingredients', 'food'],
        'mercantiles' => ['mercantiles', 'management'],
        'permissions' => ['permissions', 'users'],
        'providers'   => ['providers', 'logistics'],
        'roles'       => ['roles', 'headcount'],
        'services'    => ['services', 'sales'],
        'staff'       => ['staff', 'headcount'],
        'users'       => ['users', 'headcount'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $segment = $request->segment(1);

        if (! $segment || in_array($segment, self::EXEMPT_SEGMENTS, true)) {
            return $next($request);
        }

        $isWrite = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

        $permissions = Permission::whereIn('route_name', $this->allowedRouteNames($segment, $isWrite))->get();

        // Fail-open heredado: un segmento sin ningún permiso registrado sigue abierto.
        if ($permissions->isEmpty()) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            if ($request->user()->hasPermissionTo($permission->name)) {
                return $next($request);
            }
        }

        if ($isWrite) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }

        return Inertia::render('Unauthorized', [
            'routeName' => $segment,
        ])->toResponse($request)->setStatusCode(403);
    }

    /**
     * `route_name` que dan acceso a este segmento. Por defecto, el propio segmento.
     */
    private function allowedRouteNames(string $segment, bool $isWrite): array
    {
        if ($isWrite && isset(self::WRITE_PERMISSION_ALIASES[$segment])) {
            return self::WRITE_PERMISSION_ALIASES[$segment];
        }

        if (isset(self::SEGMENT_PERMISSION_ALIASES[$segment])) {
            return self::SEGMENT_PERMISSION_ALIASES[$segment];
        }

        return [$segment];
    }
}
