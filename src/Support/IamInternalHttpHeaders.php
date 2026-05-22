<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Support;

final class IamInternalHttpHeaders
{
    /**
     * @return array<string, string>
     */
    public static function build(): array
    {
        $token = trim((string) config('rbac.internal.token', config('rbac.internal_token', '')));
        if ($token === '') {
            $token = trim((string) config('rbac.fallback.internal_token', ''));
        }

        $tokenHeader = (string) config('rbac.internal.token_header', 'X-Internal-Token');
        $sourceHeader = (string) config('rbac.internal.source_header', 'X-Internal-Source');
        $callerSource = trim((string) config('rbac.internal.caller_source', ''));

        $headers = [$tokenHeader => $token];
        if ($callerSource !== '') {
            $headers[$sourceHeader] = $callerSource;
        }

        return $headers;
    }
}
