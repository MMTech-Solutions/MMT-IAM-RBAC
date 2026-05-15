<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Kafka;

use Mmtech\Rbac\Kafka\RbacSnapshotRoleListNormalizer;
use PHPUnit\Framework\TestCase;

final class RbacSnapshotRoleListNormalizerTest extends TestCase
{
    public function test_from_mixed_accepts_objects_and_strings(): void
    {
        $normalized = RbacSnapshotRoleListNormalizer::fromMixed([
            ['id' => '  uuid-1 ', 'name' => ' admin '],
            'legacy',
            ['not' => 'shape'],
            ['id' => '', 'name' => ''],
        ]);

        self::assertSame([
            ['id' => 'uuid-1', 'name' => 'admin'],
            ['id' => '', 'name' => 'legacy'],
        ], $normalized);
    }

    public function test_from_mixed_non_array_returns_empty(): void
    {
        self::assertSame([], RbacSnapshotRoleListNormalizer::fromMixed(null));
        self::assertSame([], RbacSnapshotRoleListNormalizer::fromMixed('x'));
    }
}
