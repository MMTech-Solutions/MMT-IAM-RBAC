<?php

declare(strict_types=1);

namespace Mmtech\Rcab\Tests\Unit\Kafka;

use Illuminate\Container\Container;
use Junges\Kafka\Contracts\ConsumerMessage;
use Mmtech\Rcab\Authorization\Contracts\SnapshotStoreInterface;
use Mmtech\Rcab\Kafka\Contracts\TopicMessageHandlerInterface;
use Mmtech\Rcab\Kafka\RbacSnapshotMessageParser;
use Mmtech\Rcab\Kafka\TopicHandlerRegistry;
use PHPUnit\Framework\TestCase;

final class TopicHandlerRegistryTest extends TestCase
{
    public function test_topics_to_subscribe_always_contains_fixed_snapshot_topic(): void
    {
        $container = $this->makeContainer();

        $registry = new TopicHandlerRegistry($container);
        $topics = $registry->topicsToSubscribe();

        self::assertContains('iam.rbac.snapshots.v1', $topics);
    }

    public function test_it_dispatches_to_custom_handler_for_configured_topic(): void
    {
        $container = $this->makeContainer();
        $container->singleton(FakeTopicHandler::class, FakeTopicHandler::class);

        $container->make('config')->set('rcab.kafka.handlers', [
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

        $container->instance('config', new class
        {
            /**
             * @var array<string, mixed>
             */
            private array $data = [
                'rcab.kafka.handlers' => [],
                'rcab.kafka.on_unhandled_topic' => 'skip',
            ];

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->data[$key] ?? $default;
            }

            public function set(string $key, mixed $value): void
            {
                $this->data[$key] = $value;
            }
        });

        $container->singleton(SnapshotStoreInterface::class, static fn (): SnapshotStoreInterface => new class implements SnapshotStoreInterface
        {
            public function getSnapshot(string $sub, string $surface): ?\Mmtech\Rcab\Kafka\RbacSnapshotMessage
            {
                return null;
            }

            public function upsertSnapshot(\Mmtech\Rcab\Kafka\RbacSnapshotMessage $snapshot): void {}

            public function deleteSnapshot(string $sub, string $surface): void {}
        });

        $container->singleton(RbacSnapshotMessageParser::class, RbacSnapshotMessageParser::class);

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
    public function __construct(private readonly string $topicName) {}

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
        return null;
    }

    public function getMessageIdentifier(): string
    {
        return 'test';
    }

    public function getBody(): mixed
    {
        return null;
    }
}

