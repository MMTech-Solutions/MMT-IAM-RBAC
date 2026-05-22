<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mmtech\Rbac\Support\InternalServiceRequest;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTrustedInternalServiceRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $error = InternalServiceRequest::resolveTrusted($request, optional: true);
        if ($error !== null) {
            return $error;
        }

        if (InternalServiceRequest::isTrusted($request) && (bool) config('rbac.internal.log_trusted_requests', false)) {
            Log::info('Trusted internal service request', [
                'source' => InternalServiceRequest::source($request),
                'path' => $request->path(),
            ]);
        }

        return $next($request);
    }
}
