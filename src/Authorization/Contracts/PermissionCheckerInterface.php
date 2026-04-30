<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Authorization\Contracts;

interface PermissionCheckerInterface
{
    public function userCan(string $sub, string $ability, ?string $surface = null): bool;
}

