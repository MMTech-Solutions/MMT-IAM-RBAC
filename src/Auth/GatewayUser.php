<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Auth;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\Access\Authorizable;

final class GatewayUser implements Authenticatable, AuthorizableContract
{
    use AuthenticatableTrait;
    use Authorizable;

    /**
     * @param  array<string, mixed>  $gatewayUserInfo
     */
    public function __construct(
        private readonly string $id,
        public readonly array $gatewayUserInfo = []
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }
}

