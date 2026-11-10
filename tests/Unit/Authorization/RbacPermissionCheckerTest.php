<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Authorization;

use Illuminate\Container\Container;
use Mmtech\Rbac\Authorization\Contracts\RoleSnapshotStoreInterface;
use Mmtech\Rbac\Authorization\Contracts\SnapshotFallbackInterface;
use Mmtech\Rbac\Authorization\Contracts\SnapshotStoreInterface;
use Mmtech\Rbac\Authorization\RbacPermissionChecker;
use Mmtech\Rbac\Kafka\RbacSnapshotMessage;
use PHPUnit\Framework\TestCase;

final class RbacPermissionCheckerTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_user_roles_returns_roles_from_store(): void
    {
        $snapshot = RbacSnapshotMessage::snapshot(
            messageKey: 'rbac:v1:snapshot:u1:customer_app',
            sub: 'u1',
            surface: 'customer_app',
            rev: 1,
            permissions: ['a'],
            updatedAt: null,
            roles: [['id' => 'rid-1', 'name' => 'customer']]
        );

        $store = $this->createMock(SnapshotStoreInterface::class);
        $store->expects($this->once())->method('getSnapshot')->with('u1', 'customer_app')->willReturn($snapshot);
        $store->expects($this->never())->method('upsertSnapshot');

        $fallback = $this->createMock(SnapshotFallbackInterface::class);
        $fallback->expects($this->never())->method('fetchSnapshot');

        $roleStore = $this->createMock(RoleSnapshotStoreInterface::class);
        $roleStore->expects($this->never())->method('getPermissions');

        $checker = new RbacPermissionChecker($store, $fallback, $roleStore);

        self::assertSame([['id' => 'rid-1', 'name' => 'customer']], $checker->userRoles('u1', 'customer_app'));
    }

    public function test_user_roles_reuses_snapshot_cache_after_user_can(): void
    {
        $snapshot = RbacSnapshotMessage::snapshot(
            messageKey: 'rbac:v1:snapshot:u1:customer_app',
            sub: 'u1',
            surface: 'customer_app',
            rev: 1,
            permissions: ['perm.one'],
            updatedAt: null,
            roles: [['id' => 'r1', 'name' => 'admin']]
        );

        $store = $this->createMock(SnapshotStoreInterface::class);
        $store->expects($this->once())->method('getSnapshot')->willReturn($snapshot);

        $fallback = $this->createMock(SnapshotFallbackInterface::class);
        $fallback->expects($this->never())->method('fetchSnapshot');

        $roleStore = $this->createMock(RoleSnapshotStoreInterface::class);
        $roleStore->expects($this->never())->method('getPermissions');

        $checker = new RbacPermissionChecker($store, $fallback, $roleStore);

        self::assertTrue($checker->userCan('u1', 'perm.one', 'customer_app'));
        self::assertSame([['id' => 'r1', 'name' => 'admin']], $checker->userRoles('u1', 'customer_app'));
    }

    public function test_user_can_allows_via_role_catalog_when_user_permissions_empty(): void
    {
        $snapshot = RbacSnapshotMessage::snapshot(
            messageKey: 'rbac:v1:snapshot:u1:customer_app',
            sub: 'u1',
            surface: 'customer_app',
            rev: 1,
            permissions: [],
            updatedAt: null,
            roles: [['id' => 'role-1', 'name' => 'stage']]
        );

        $store = $this->createMock(SnapshotStoreInterface::class);
        $store->method('getSnapshot')->willReturn($snapshot);

        $fallback = $this->createMock(SnapshotFallbackInterface::class);
        $fallback->expects($this->never())->method('fetchSnapshot');

        $roleStore = $this->createMock(RoleSnapshotStoreInterface::class);
        $roleStore->expects($this->once())
            ->method('getPermissions')
            ->with('role-1', 'customer_app')
            ->willReturn(['kyc.stage.view', 'orders.read']);

        $checker = new RbacPermissionChecker($store, $fallback, $roleStore);

        self::assertTrue($checker->userCan('u1', 'orders.read', 'customer_app'));
        self::assertFalse($checker->userCan('u1', 'missing.perm', 'customer_app'));
    }

    public function test_user_can_still_allows_legacy_flattened_permissions_without_role_lookup(): void
    {
        $snapshot = RbacSnapshotMessage::snapshot(
            messageKey: 'rbac:v1:snapshot:u1:customer_app',
            sub: 'u1',
            surface: 'customer_app',
            rev: 1,
            permissions: ['legacy.perm'],
            updatedAt: null,
            roles: [['id' => 'role-1', 'name' => 'stage']]
        );

        $store = $this->createMock(SnapshotStoreInterface::class);
        $store->method('getSnapshot')->willReturn($snapshot);

        $fallback = $this->createMock(SnapshotFallbackInterface::class);

        $roleStore = $this->createMock(RoleSnapshotStoreInterface::class);
        $roleStore->expects($this->never())->method('getPermissions');

        $checker = new RbacPermissionChecker($store, $fallback, $roleStore);

        self::assertTrue($checker->userCan('u1', 'legacy.perm', 'customer_app'));
    }
}
