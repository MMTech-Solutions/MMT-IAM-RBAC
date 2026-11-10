<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Kafka;

use Junges\Kafka\Contracts\ConsumerMessage;
use Mmtech\Rbac\Kafka\RbacRoleSnapshotMessageParser;
use PHPUnit\Framework\TestCase;

final class RbacRoleSnapshotMessageParserTest extends TestCase
{
    public function test_parse_role_snapshot(): void
    {
        $parser = new RbacRoleSnapshotMessageParser;
        $message = $this->makeMessage(
            'rbac:v1:role:role-uuid:customer_app',
            json_encode([
                'kind' => 'role',
                'rev' => 3,
                'role_id' => 'role-uuid',
                'surface' => 'customer_app',
                'permissions' => ['orders.read', 'kyc.view'],
                'updated_at' => '2026-11-10T12:00:00+00:00',
            ], JSON_THROW_ON_ERROR)
        );

        $parsed = $parser->parse($message);

        self::assertNotNull($parsed);
        self::assertFalse($parsed->isTombstone);
        self::assertSame('role-uuid', $parsed->roleId);
        self::assertSame('customer_app', $parsed->surface);
        self::assertSame(3, $parsed->rev);
        self::assertSame(['orders.read', 'kyc.view'], $parsed->permissions);
    }

    public function test_parse_tombstone(): void
    {
        $parser = new RbacRoleSnapshotMessageParser;
        $message = $this->makeMessage('rbac:v1:role:role-uuid:admin_panel', null);

        $parsed = $parser->parse($message);

        self::assertNotNull($parsed);
        self::assertTrue($parsed->isTombstone);
        self::assertSame('role-uuid', $parsed->roleId);
        self::assertSame('admin_panel', $parsed->surface);
    }

    public function test_is_role_snapshot_key(): void
    {
        self::assertTrue(RbacRoleSnapshotMessageParser::isRoleSnapshotKey('rbac:v1:role:abc:customer_app'));
        self::assertFalse(RbacRoleSnapshotMessageParser::isRoleSnapshotKey('rbac:v1:snapshot:abc:customer_app'));
    }

    private function makeMessage(string $key, ?string $body): ConsumerMessage
    {
        return new class($key, $body) implements ConsumerMessage
        {
            public function __construct(
                private readonly string $key,
                private readonly ?string $body
            ) {}

            public function getKey(): mixed
            {
                return $this->key;
            }

            public function getBody(): mixed
            {
                return $this->body;
            }

            public function getTopicName(): ?string
            {
                return 'iam.rbac.snapshots.v1';
            }

            public function getPartition(): ?int
            {
                return 0;
            }

            public function getHeaders(): ?array
            {
                return null;
            }

            public function getMessageIdentifier(): string
            {
                return 'test-msg';
            }

            public function getOffset(): ?int
            {
                return 0;
            }

            public function getTimestamp(): ?int
            {
                return null;
            }
        };
    }
}
