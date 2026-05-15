<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Auth;

use Illuminate\Container\Container;
use Mmtech\Rbac\Auth\GatewayUser;
use Mmtech\Rbac\Authorization\Contracts\PermissionCheckerInterface;
use PHPUnit\Framework\TestCase;

final class GatewayUserRbacRolesTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_rbac_roles_delegates_to_permission_checker_with_resolved_surface(): void
    {
        $container = new Container;
        Container::setInstance($container);

        $checker = $this->createMock(PermissionCheckerInterface::class);
        $checker->expects($this->once())
            ->method('userRoles')
            ->with('sub-uuid', 'customer_app')
            ->willReturn([['id' => 'role-1', 'name' => 'customer']]);
        $container->instance(PermissionCheckerInterface::class, $checker);

        $user = new GatewayUser('sub-uuid');

        self::assertSame([['id' => 'role-1', 'name' => 'customer']], $user->rbacRoles('customer_app'));
    }

    public function test_rbac_role_returns_first_entry(): void
    {
        $container = new Container;
        Container::setInstance($container);

        $checker = $this->createMock(PermissionCheckerInterface::class);
        $checker->expects($this->once())
            ->method('userRoles')
            ->with('sub', 'customer_app')
            ->willReturn([
                ['id' => 'a', 'name' => 'first'],
                ['id' => 'b', 'name' => 'second'],
            ]);
        $container->instance(PermissionCheckerInterface::class, $checker);

        $user = new GatewayUser('sub');

        self::assertSame(['id' => 'a', 'name' => 'first'], $user->rbacRole('customer_app'));
    }
}
