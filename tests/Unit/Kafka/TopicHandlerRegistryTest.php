<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Kafka;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Junges\Kafka\Contracts\ConsumerMessage;
use Mmtech\Rbac\Authorization\Contracts\RoleSnapshotStoreInterface;
use Mmtech\Rbac\Authorization\Contracts\SnapshotStoreInterface;
use Mmtech\Rbac\Kafka\Contracts\TopicMessageHandlerInterface;
use Mmtech\Rbac\Kafka\Handlers\RbacSnapshotTopicHandler;
use Mmtech\Rbac\Kafka\RbacRoleSnapshotMessageParser;
use Mmtech\Rbac\Kafka\RbacSnapshotMessageParser;
use Mmtech\Rbac\Kafka\TopicHandlerRegistry;
use PHPUnit\Framework\TestCase;

final class TopicHandlerRegistryTest extends TestCase
{
    public function test_topics_to_subscribe_always_contains_default_snapshot_topic(): void
    {
        $container = $this->makeContainer();

        $registry = new TopicHandlerRegistry($container);
        $topics = $registry->topicsToSubscribe();

        self::assertContains(RbacSnapshotTopicHandler::TOPIC, $topics);
    }

    public function test_topics_to_subscribe_uses_configured_snapshot_topic(): void
    {
        $container = $this->makeContainer();
        $container->make('config')->set(
            'rbac.consumer.topic',
            'fxlivecapital.iam.rbac.snapshots.v1'
        );

        $registry = new TopicHandlerRegistry($container);
        $topics = $registry->topicsToSubscribe();

        self::assertContains('fxlivecapital.iam.rbac.snapshots.v1', $topics);
        self::assertNotContains(RbacSnapshotTopicHandler::TOPIC, $topics);
    }

    public function test_it_dispatches_to_custom_handler_for_configured_topic(): void
    {
        $container = $this->makeContainer();
        $container->singleton(FakeTopicHandler::class, FakeTopicHandler::class);

        $container->make('config')->set('rbac.consumer.handlers', [
            'custom.events.v1' => FakeTopicHandler::class,
        ]);

        $registry = new TopicHandlerRegistry($container);
        $message = new FakeConsumerMessage('custom.events.v1');
        $registry->handle($message);

        /** @var FakeTopicHandler $handler */
        $handler = $container->make(FakeTopicHandler::class);
        self::assertTrue($handler->called);
    }

    private function makeContainer(): Container
    {
        $container = new Container();
        Container::setInstance($container);

        $config = new class implements ConfigRepository
        {
            /**
             * @var array<string, mixed>
             */
            private array $data = [
                'rbac.consumer.topic' => RbacSnapshotTopicHandler::TOPIC,
                'rbac.consumer.handlers' => [],
                'rbac.consumer.on_unhandled_topic' => 'skip',
            ];

            public function has($key): bool
            {
                return array_key_exists($key, $this->data);
            }

            public function get($key, $default = null): mixed
            {
                return $this->data[$key] ?? $default;
            }

            public function all(): array
            {
                return $this->data;
            }

            public function set($key, $value = null): void
            {
                if (is_array($key)) {
                    foreach ($key as $k => $v) {
                        $this->data[$k] = $v;
                    }

                    return;
                }

                $this->data[$key] = $value;
            }

            public function prepend($key, $value): void
            {
                $array = $this->get($key, []);
                array_unshift($array, $value);
                $this->set($key, $array);
            }

            public function push($key, $value): void
            {
                $array = $this->get($key, []);
                $array[] = $value;
                $this->set($key, $array);
            }
        };

        $container->instance('config', $config);
        $container->instance(ConfigRepository::class, $config);

        $container->singleton(SnapshotStoreInterface::class, static fn (): SnapshotStoreInterface => new class implements SnapshotStoreInterface
        {
            public function getSnapshot(string $sub, string $surface): ?\Mmtech\Rbac\Kafka\RbacSnapshotMessage
            {
                return null;
            }

            public function upsertSnapshot(\Mmtech\Rbac\Kafka\RbacSnapshotMessage $snapshot): void {}

            public function deleteSnapshot(string $sub, string $surface): void {}
        });

        $container->singleton(RoleSnapshotStoreInterface::class, static fn (): RoleSnapshotStoreInterface => new class implements RoleSnapshotStoreInterface
        {
            public function getPermissions(string $roleId, string $surface): ?array
            {
                return null;
            }

            public function upsertSnapshot(\Mmtech\Rbac\Kafka\RbacRoleSnapshotMessage $snapshot): void {}

            public function deleteSnapshot(string $roleId, string $surface): void {}
        });

        $container->singleton(RbacSnapshotMessageParser::class, RbacSnapshotMessageParser::class);
        $container->singleton(RbacRoleSnapshotMessageParser::class, RbacRoleSnapshotMessageParser::class);

        return $container;
    }
}

final class FakeTopicHandler implements TopicMessageHandlerInterface
{
    public bool $called = false;

    public function topic(): string
    {
        return 'custom.events.v1';
    }

    public function handle(ConsumerMessage $message): void
    {
        $this->called = true;
    }
}

final class FakeConsumerMessage implements ConsumerMessage
{
    /**
     * @param  array<string, mixed>|null  $headers
     */
    public function __construct(
        private readonly string $topicName,
        private readonly mixed $body = null,
        private readonly ?array $headers = null,
    ) {}

    public function getOffset(): ?int
    {
        return 0;
    }

    public function getTimestamp(): ?int
    {
        return null;
    }

    public function getKey(): mixed
    {
        return null;
    }

    public function getTopicName(): ?string
    {
        return $this->topicName;
    }

    public function getPartition(): ?int
    {
        return null;
    }

    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    public function getMessageIdentifier(): string
    {
        return 'test';
    }

    public function getBody(): mixed
    {
        return $this->body;
    }
}
