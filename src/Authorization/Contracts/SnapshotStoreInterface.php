<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Authorization\Contracts;

use Mmtech\Rbac\Kafka\RbacSnapshotMessage;

interface SnapshotStoreInterface
{
    public function getSnapshot(string $sub, string $surface): ?RbacSnapshotMessage;

    public function upsertSnapshot(RbacSnapshotMessage $snapshot): void;

    public function deleteSnapshot(string $sub, string $surface): void;
}

