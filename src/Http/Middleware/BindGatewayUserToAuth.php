<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mmtech\Rbac\Auth\GatewayUser;
use Symfony\Component\HttpFoundation\Response;

final class BindGatewayUserToAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $info = $request->attributes->get('gateway_auth_user_info');
        if (! is_array($info) || empty($info['sub'])) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $sub = trim((string) $info['sub']);
        if ($sub === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = new GatewayUser(id: $sub, gatewayUserInfo: $info);
        $guard = (string) config('rbac.auth.guard', 'web');

        Auth::shouldUse($guard);
        Auth::guard($guard)->setUser($user);

        return $next($request);
    }
}

