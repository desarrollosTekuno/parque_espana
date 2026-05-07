<?php

namespace App\Http\Responses;

use App\Services\Auth\PermissionLandingRouteResolver;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function __construct(
        protected PermissionLandingRouteResolver $permissionLandingRouteResolver
    ) {
    }

    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $routeName = $this->permissionLandingRouteResolver->resolve($request->user()) ?? 'unauthorized';

        return redirect()->intended(route($routeName));
    }
}
