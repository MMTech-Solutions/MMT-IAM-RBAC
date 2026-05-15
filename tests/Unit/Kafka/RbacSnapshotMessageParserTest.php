<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Kafka;

use Junges\Kafka\Contracts\ConsumerMessage;
use Mmtech\Rbac\Kafka\RbacSnapshotMessageParser;
use PHPUnit\Framework\TestCase;

final class RbacSnapshotMessageParserTest extends TestCase
{
    public function test_parse_includes_roles_with_id_and_name(): void
    {
        $parser = new RbacSnapshotMessageParser;
        $message = $this->makeMessage(
            'rbac:v1:snapshot:user-uuid:customer_app',
            json_encode([
                'rev' => 1,
                'permissions' => ['orders.read'],
                'roles' => [
                    ['id' => 'role-uuid-1', 'name' => 'customer'],
                    ['id' => 'role-uuid-2', 'name' => 'vip'],
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $parsed = $parser->parse($message);

        self::assertNotNull($parsed);
        self::assertFalse($parsed->isTombstone);
        self::assertSame(['orders.read'], $parsed->permissions);
        self::assertSame([
            ['id' => 'role-uuid-1', 'name' => 'customer'],
            ['id' => 'role-uuid-2', 'name' => 'vip'],
        ], $parsed->roles);
    }

    public function test_parse_legacy_string_roles_normalizes_to_empty_id(): void
    {
        $parser = new RbacSnapshotMessageParser;
        $message = $this->makeMessage(
            'rbac:v1:snapshot:user-uuid:customer_app',
            json_encode([
                'rev' => 1,
                'permissions' => ['orders.read'],
                'roles' => ['customer', 'vip'],
            ], JSON_THROW_ON_ERROR)
        );

        $parsed = $parser->parse($message);

        self::assertNotNull($parsed);
        self::assertSame([
            ['id' => '', 'name' => 'customer'],
            ['id' => '', 'name' => 'vip'],
        ], $parsed->roles);
    }

    public function test_parse_omitted_roles_defaults_to_empty_list(): void
    {
        $parser = new RbacSnapshotMessageParser;
        $message = $this->makeMessage(
            'rbac:v1:snapshot:user-uuid:customer_app',
            json_encode([
                'rev' => 2,
                'permissions' => ['x'],
            ], JSON_THROW_ON_ERROR)
        );

        $parsed = $parser->parse($message);

        self::assertNotNull($parsed);
        self::assertSame([], $parsed->roles);
    }

    public function test_parse_tombstone_yields_null_roles(): void
    {
        $parser = new RbacSnapshotMessageParser;
        $message = $this->makeMessage('rbac:v1:snapshot:user-uuid:customer_app', null);

        $parsed = $parser->parse($message);

        self::assertNotNull($parsed);
        self::assertTrue($parsed->isTombstone);
        self::assertNull($parsed->roles);
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
