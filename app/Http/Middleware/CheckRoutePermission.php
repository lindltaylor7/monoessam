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
     * Segmentos que no tienen fila propia en `permissions` pero pertenecen al dominio de
     * otro permiso. Sin este mapeo quedarían abiertos a cualquier usuario autenticado,
     * porque un segmento sin permiso registrado no se bloquea.
     *
     * `purchase-orders` (show/destroy de una orden de compra) es el mismo dominio que
     * `orders` (permiso "Ordenes", id 41), solo que colgado de otro prefijo.
     */
    private const SEGMENT_PERMISSION_ALIASES = [
        'purchase-orders' => 'orders',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod('GET') || !$request->user()) {
            return $next($request);
        }

        $segment = $request->segment(1);

        if (!$segment || in_array($segment, ['dashboard', 'settings', 'profile'])) {
            return $next($request);
        }

        $routeName = self::SEGMENT_PERMISSION_ALIASES[$segment] ?? $segment;

        $permission = Permission::where('route_name', $routeName)->first();

        if (!$permission) {
            return $next($request);
        }

        if ($request->user()->hasPermissionTo($permission->name)) {
            return $next($request);
        }

        return Inertia::render('Unauthorized', [
            'routeName' => $segment,
        ])->toResponse($request)->setStatusCode(403);
    }
}
