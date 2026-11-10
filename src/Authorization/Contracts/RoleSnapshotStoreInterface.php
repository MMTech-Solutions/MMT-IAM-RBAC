<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Authorization\Contracts;

use Mmtech\Rbac\Kafka\RbacRoleSnapshotMessage;

interface RoleSnapshotStoreInterface
{
    /**
     * @return list<string>|null  Null when no materialized role snapshot exists
     */
    public function getPermissions(string $roleId, string $surface): ?array;

    public function upsertSnapshot(RbacRoleSnapshotMessage $snapshot): void;

    public function deleteSnapshot(string $roleId, string $surface): void;
}
