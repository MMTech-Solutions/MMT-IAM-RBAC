<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Kafka;

use Junges\Kafka\Contracts\ConsumerMessage;

final class RbacSnapshotMessageParser
{
    public function parse(ConsumerMessage $message): ?RbacSnapshotMessage
    {
        $key = $this->normalizeString($message->getKey());
        if ($key === null) {
            return null;
        }

        [$sub, $surface] = $this->extractSubAndSurfaceFromKey($key);
        if ($sub === null || $surface === null) {
            return null;
        }

        $body = $message->getBody();
        if ($body === null) {
            return RbacSnapshotMessage::tombstone($key, $sub, $surface);
        }

        if (is_string($body)) {
            $decoded = json_decode($body, true);
        } elseif (is_array($body)) {
            $decoded = $body;
        } else {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $rev = $decoded['rev'] ?? null;
        if (! is_int($rev) && ! (is_string($rev) && ctype_digit(trim($rev)))) {
            return null;
        }

        $permissions = $decoded['permissions'] ?? null;
        if (! is_array($permissions)) {
            return null;
        }

        /** @var list<string> $normalizedPermissions */
        $normalizedPermissions = array_values(array_filter(
            array_map(fn ($permission): ?string => $this->normalizeString($permission), $permissions),
            static fn (?string $permission): bool => $permission !== null
        ));

        $payloadSub = $this->normalizeString($decoded['sub'] ?? null) ?? $sub;
        $payloadSurface = $this->normalizeString($decoded['surface'] ?? null) ?? $surface;
        $updatedAt = $this->normalizeString($decoded['updated_at'] ?? null);

        return RbacSnapshotMessage::snapshot(
            messageKey: $key,
            sub: $payloadSub,
            surface: $payloadSurface,
            rev: (int) $rev,
            permissions: $normalizedPermissions,
            updatedAt: $updatedAt
        );
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function extractSubAndSurfaceFromKey(string $key): array
    {
        if (preg_match('/^rbac:v1:snapshot:([^:]+):([^:]+)$/', $key, $matches) !== 1) {
            return [null, null];
        }

        return [$this->normalizeString($matches[1]), $this->normalizeString($matches[2])];
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}

