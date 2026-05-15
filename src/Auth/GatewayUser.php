<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Auth;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Mmtech\Rbac\Authorization\Contracts\PermissionCheckerInterface;
use Mmtech\Rbac\Support\SurfaceResolver;

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

    /**
     * Roles for the current gateway user on the resolved RBAC surface (same source as {@see self::can()}).
     *
     * @return list<array{id: string, name: string}>
     */
    public function rbacRoles(?string $surface = null): array
    {
        $resolvedSurface = $surface ?? SurfaceResolver::resolve(request());

        return app(PermissionCheckerInterface::class)->userRoles((string) $this->getAuthIdentifier(), $resolvedSurface);
    }

    /**
     * First role entry for the surface, if any (convenience when a single role is expected).
     *
     * @return array{id: string, name: string}|null
     */
    public function rbacRole(?string $surface = null): ?array
    {
        $roles = $this->rbacRoles($surface);

        return $roles[0] ?? null;
    }
}
