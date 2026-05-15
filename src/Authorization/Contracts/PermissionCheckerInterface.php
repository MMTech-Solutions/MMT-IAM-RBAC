<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Authorization\Contracts;

interface PermissionCheckerInterface
{
    public function userCan(string $sub, string $ability, ?string $surface = null): bool;

    /**
     * Effective roles for the user on the given surface (from materialized snapshot / fallback).
     *
     * @return list<array{id: string, name: string}>
     */
    public function userRoles(string $sub, ?string $surface = null): array;
}

