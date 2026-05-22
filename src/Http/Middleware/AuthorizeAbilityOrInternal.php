<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Mmtech\Rbac\Support\InternalServiceRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeAbilityOrInternal
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        if (InternalServiceRequest::isTrusted($request)) {
            return $next($request);
        }

        if (! Gate::allows($ability)) {
            return new JsonResponse(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
