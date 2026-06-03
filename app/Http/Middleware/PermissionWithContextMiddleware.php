<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

class PermissionWithContextMiddleware extends PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
     public function handle($request, Closure $next, $permission, $guard = null)
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        $allPermissions = $user->getAllPermissions();
        $allPermissions->load('contexts');

        $perm = $allPermissions->firstWhere('name', $permission);

        if (!$perm || !$perm->contexts->contains('value', 'web')) {
            abort(403);
        }

        return $next($request);
    }
}
