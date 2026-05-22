<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mmtech\Rbac\Support\InternalServiceRequest;
use Symfony\Component\HttpFoundation\Response;

final class VerifyInternalRbacToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (InternalServiceRequest::isTrusted($request)) {
            return $next($request);
        }

        $error = InternalServiceRequest::resolveTrusted($request, optional: false);
        if ($error !== null) {
            return $error;
        }

        return $next($request);
    }
}
