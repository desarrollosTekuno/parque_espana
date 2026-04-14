<?php

namespace App\Http\Responses;

use App\Services\Auth\PermissionLandingRouteResolver;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function __construct(
        protected PermissionLandingRouteResolver $permissionLandingRouteResolver
    ) {
    }

    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $routeName = $this->permissionLandingRouteResolver->resolve($request->user()) ?? 'unauthorized';

        return redirect()->intended(route($routeName));
    }
}
