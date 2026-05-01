<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class ResolveGatewayUserInfo
{
    private const DEFAULT_USERINFO_HEADER = 'X-Userinfo';

    private const DEFAULT_INTERNAL_HEADER = 'X-Internal-Gateway';

    public function handle(Request $request, Closure $next): Response
    {
        $internalHeader = (string) config('rbac.gateway.internal_header', self::DEFAULT_INTERNAL_HEADER);
        $internalSecret = (string) config('rbac.gateway.internal_secret', 'apisix');

        if ($internalSecret === '' || $request->header($internalHeader) !== $internalSecret) {
            $this->maybeLog('Internal gateway header is missing');

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $userinfoHeader = (string) config('rbac.gateway.userinfo_header', self::DEFAULT_USERINFO_HEADER);
        $value = $request->header($userinfoHeader);
        if ($value === null || trim((string) $value) === '') {
            $this->maybeLog('Userinfo header is missing');

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $payload = self::base64UrlDecode(trim((string) $value));
        if ($payload === null) {
            $this->maybeLog('Userinfo header is not a valid JWT');

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded) || empty($decoded['sub']) || (is_string($decoded['sub']) && trim($decoded['sub']) === '')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('gateway_auth_user_info', $decoded);

        return $next($request);
    }

    private function maybeLog(string $message): void
    {
        if ((bool) config('rbac.gateway.log_missing_headers', false)) {
            Log::info($message);
        }
    }

    private static function base64UrlDecode(string $input): ?string
    {
        $base64 = strtr($input, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode($base64, true);

        return $decoded !== false ? $decoded : null;
    }
}

