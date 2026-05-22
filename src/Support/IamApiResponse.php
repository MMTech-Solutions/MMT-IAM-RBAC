<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Support;

final class IamApiResponse
{
    /**
     * Extracts the IAM API `data` envelope (or a flat payload without meta keys).
     *
     * @return array<string, mixed>
     */
    public static function extractDataPayload(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }

        unset($decoded['success'], $decoded['message'], $decoded['meta']);

        return $decoded;
    }
}
