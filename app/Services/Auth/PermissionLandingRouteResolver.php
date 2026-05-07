<?php

namespace App\Services\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class PermissionLandingRouteResolver
{
    public function resolve(?Authenticatable $user): ?string
    {
        if (!$user || !method_exists($user, 'getAllPermissions')) {
            return null;
        }

        $permissions = $user->getAllPermissions()
            ->filter(fn ($permission) => $permission->contexts->contains('value', 'web'))
            ->pluck('name')
            ->filter()
            ->unique()
            ->values();

        foreach ($permissions as $permissionName) {
            $routeName = $this->resolveRouteNameFromPermission($permissionName);

            if ($routeName !== null) {
                return $routeName;
            }
        }

        return null;
    }

    protected function resolveRouteNameFromPermission(string $permissionName): ?string
    {
        $candidates = [$permissionName];

        if (Str::contains($permissionName, '.')) {
            $segments = explode('.', $permissionName);
            $action = array_pop($segments);
            $resource = implode('.', $segments);

            if (in_array($action, ['store', 'update', 'destroy', 'edit', 'show'], true)) {
                $candidates[] = "{$resource}.index";
            }

            if (in_array($action, ['store', 'update', 'destroy'], true)) {
                $candidates[] = "{$resource}.create";
            }
        }

        foreach (array_unique($candidates) as $candidate) {
            if ($this->isNavigableRoute($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function isNavigableRoute(string $routeName): bool
    {
        if (!Route::has($routeName)) {
            return false;
        }

        $route = Route::getRoutes()->getByName($routeName);

        if (!$route || count($route->parameterNames()) > 0) {
            return false;
        }

        $methods = $route->methods();

        return in_array('GET', $methods, true) || in_array('HEAD', $methods, true);
    }
}
