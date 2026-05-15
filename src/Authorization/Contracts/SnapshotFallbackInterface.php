<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Authorization\Contracts;

use Mmtech\Rbac\Kafka\RbacSnapshotMessage;

interface SnapshotFallbackInterface
{
    public function fetchSnapshot(string $sub, string $surface): ?RbacSnapshotMessage;
}
