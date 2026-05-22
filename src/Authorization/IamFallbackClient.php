<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Authorization;

use Illuminate\Support\Facades\Http;
use Mmtech\Rbac\Authorization\Contracts\SnapshotFallbackInterface;
use Mmtech\Rbac\Kafka\RbacSnapshotMessage;
use Mmtech\Rbac\Kafka\RbacSnapshotRoleListNormalizer;
use Mmtech\Rbac\Support\IamInternalHttpHeaders;
use RuntimeException;

final class IamFallbackClient implements SnapshotFallbackInterface
{
    public function fetchSnapshot(string $sub, string $surface): ?RbacSnapshotMessage
    {
        if (! (bool) config('rbac.fallback.enabled', true)) {
            return null;
        }

        $baseUrl = trim((string) config('rbac.fallback.base_url', ''));
        if ($baseUrl === '') {
            return null;
        }

        $token = trim((string) config('rbac.fallback.internal_token', ''));
        if ($token === '') {
            return null;
        }

        $timeoutMs = (int) config('rbac.fallback.timeout_ms', 1500);
        $timeoutSeconds = max(1, (int) ceil($timeoutMs / 1000));

        $response = Http::timeout($timeoutSeconds)
            ->acceptJson()
            ->withHeaders(IamInternalHttpHeaders::build())
            ->get(sprintf('%s/api/iam/v1/internal/rbac/users/%s/permissions', rtrim($baseUrl, '/'), $sub), [
                'surface' => $surface,
            ]);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new RuntimeException('IAM fallback request failed with status '.$response->status().'.');
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            throw new RuntimeException('IAM fallback response does not contain a valid data payload.');
        }

        $rev = $data['rev'] ?? null;
        $permissions = $data['permissions'] ?? null;
        if (! is_int($rev) && ! (is_string($rev) && ctype_digit(trim($rev)))) {
            throw new RuntimeException('IAM fallback response has an invalid rev value.');
        }
        if (! is_array($permissions)) {
            throw new RuntimeException('IAM fallback response has an invalid permissions value.');
        }

        /** @var list<string> $normalizedPermissions */
        $normalizedPermissions = array_values(array_filter(
            $permissions,
            static fn ($permission): bool => is_string($permission) && trim($permission) !== ''
        ));

        $normalizedRoles = RbacSnapshotRoleListNormalizer::fromMixed($data['roles'] ?? null);

        return RbacSnapshotMessage::snapshot(
            messageKey: sprintf('rbac:v1:snapshot:%s:%s', $sub, $surface),
            sub: trim((string) ($data['sub'] ?? $sub)),
            surface: trim((string) ($data['surface'] ?? $surface)),
            rev: (int) $rev,
            permissions: $normalizedPermissions,
            updatedAt: isset($data['updated_at']) ? trim((string) $data['updated_at']) : null,
            roles: $normalizedRoles
        );
    }
}

