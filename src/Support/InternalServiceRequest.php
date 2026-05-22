<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class InternalServiceRequest
{
    public const TRUSTED_ATTRIBUTE = 'trusted_internal_service_request';

    public const SOURCE_ATTRIBUTE = 'internal_service_source';

    public static function isTrusted(Request $request): bool
    {
        return $request->attributes->get(self::TRUSTED_ATTRIBUTE) === true;
    }

    public static function source(Request $request): ?string
    {
        $value = $request->attributes->get(self::SOURCE_ATTRIBUTE);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    public static function markTrusted(Request $request, string $source): void
    {
        $request->attributes->set(self::TRUSTED_ATTRIBUTE, true);
        $request->attributes->set(self::SOURCE_ATTRIBUTE, trim($source));
    }

    /**
     * When optional: no token header means "not an internal attempt" (returns null).
     * When required: missing/invalid credentials return an error response.
     */
    public static function resolveTrusted(Request $request, bool $optional = true): ?Response
    {
        $tokenHeader = (string) config('rbac.internal.token_header', 'X-Internal-Token');
        $sourceHeader = (string) config('rbac.internal.source_header', 'X-Internal-Source');
        $tokenValue = $request->header($tokenHeader);

        if (! is_string($tokenValue) || trim($tokenValue) === '') {
            return $optional ? null : self::forbidden();
        }

        $expected = config('rbac.internal.token', config('rbac.internal_token'));
        if (! is_string($expected) || trim($expected) === '') {
            return self::notConfigured();
        }

        if (! hash_equals(trim($expected), trim($tokenValue))) {
            return self::forbidden();
        }

        $sourceValue = $request->header($sourceHeader);
        if (! is_string($sourceValue) || trim($sourceValue) === '') {
            return self::forbidden();
        }

        self::markTrusted($request, $sourceValue);

        return null;
    }

    private static function notConfigured(): Response
    {
        return new JsonResponse([
            'success' => false,
            'message' => 'RBAC internal endpoint is not configured.',
        ], 503);
    }

    private static function forbidden(): Response
    {
        return new JsonResponse([
            'success' => false,
            'message' => 'Forbidden.',
        ], 403);
    }
}
