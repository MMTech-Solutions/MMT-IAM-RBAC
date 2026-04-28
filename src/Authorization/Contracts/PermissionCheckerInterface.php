<?php

declare(strict_types=1);

namespace Mmtech\Rcab\Authorization\Contracts;

interface PermissionCheckerInterface
{
    public function userCan(string $sub, string $ability, ?string $surface = null): bool;
}

