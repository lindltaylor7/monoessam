<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

class CheckRoutePermission
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod('GET') || !$request->user()) {
            return $next($request);
        }

        $segment = $request->segment(1);

        if (!$segment || in_array($segment, ['dashboard', 'settings', 'profile'])) {
            return $next($request);
        }

        $permission = Permission::where('route_name', $segment)->first();

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
