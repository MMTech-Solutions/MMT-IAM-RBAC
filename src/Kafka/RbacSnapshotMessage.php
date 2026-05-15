<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Kafka;

final class RbacSnapshotMessage
{
    /**
     * @param  list<string>|null  $permissions
     * @param  list<array{id: string, name: string}>|null  $roles
     */
    private function __construct(
        public readonly string $messageKey,
        public readonly string $sub,
        public readonly string $surface,
        public readonly ?int $rev,
        public readonly ?array $permissions,
        public readonly ?array $roles,
        public readonly ?string $updatedAt,
        public readonly bool $isTombstone
    ) {}

    /**
     * @param  list<string>  $permissions
     * @param  list<array{id: string, name: string}>  $roles
     */
    public static function snapshot(
        string $messageKey,
        string $sub,
        string $surface,
        int $rev,
        array $permissions,
        ?string $updatedAt,
        array $roles = []
    ): self {
        return new self(
            messageKey: $messageKey,
            sub: $sub,
            surface: $surface,
            rev: $rev,
            permissions: $permissions,
            roles: $roles,
            updatedAt: $updatedAt,
            isTombstone: false
        );
    }

    public static function tombstone(string $messageKey, string $sub, string $surface): self
    {
        return new self(
            messageKey: $messageKey,
            sub: $sub,
            surface: $surface,
            rev: null,
            permissions: null,
            roles: null,
            updatedAt: null,
            isTombstone: true
        );
    }
}
